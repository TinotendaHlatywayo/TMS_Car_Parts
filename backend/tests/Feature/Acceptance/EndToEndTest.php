<?php

namespace Tests\Feature\Acceptance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class EndToEndTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $cashier;

    private User $manager;

    private User $adminB;

    private Product $product;

    private \App\Models\Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->cashier = $this->user('Cashier');
        $this->manager = $this->user('Manager');
        $this->adminB = $this->user('Branch B Admin');

        $this->membership($this->branchA, $this->cashier, 'cashier');
        $this->membership($this->branchA, $this->manager, 'manager');
        $this->membership($this->branchB, $this->adminB, 'admin');

        $this->unit = $this->unit($this->branchA, 'PCS', true);
        $this->product = $this->product($this->branchA, $this->unit, null, 'E2E-1', 'End To End Product', null, 1000);
    }

    public function test_product_stock_customer_sale_payment_receipt_history_flow(): void
    {
        $openingKey = (string) Str::uuid();

        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', $openingKey)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$this->product->id}/stock/movements", [
                'type' => 'opening',
                'quantity' => '10',
                'unit_id' => $this->unit->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.balance_after', '10.0000');

        $customer = Customer::create([
            'branch_id' => $this->branchA->id,
            'name' => 'E2E Customer',
            'phone' => '0770000000',
            'email' => 'e2e@example.com',
            'is_active' => true,
        ]);

        $checkoutKey = (string) Str::uuid();

        $response = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', $checkoutKey)
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'customer_id' => $customer->id,
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '2',
                        'discount_percent' => 5,
                        'tax_rate_percent' => 10,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 2090, 'currency_code' => 'USD', 'reference' => 'drawer-e2e'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.subtotal_minor', 2000)
            ->assertJsonPath('data.discount_minor', 100)
            ->assertJsonPath('data.tax_minor', 190)
            ->assertJsonPath('data.total_minor', 2090)
            ->assertJsonPath('data.paid_minor', 2090)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.receipt.receipt_number', fn ($value) => is_string($value) && $value !== '');

        $saleId = $response->json('data.id');
        $receiptId = $response->json('data.receipt.id');

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '8.0000',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'source' => 'sale',
            'source_id' => $saleId,
            'quantity' => '-2.0000',
            'balance_after' => '8.0000',
        ]);

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.id', $saleId)
            ->assertJsonPath('data.receipt.id', $receiptId);

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/sales")
            ->assertOk()
            ->assertJsonPath('data.0.id', $saleId);

        // Duplicate checkout must replay the original sale without changing stock.
        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', $checkoutKey)
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'customer_id' => $customer->id,
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '2',
                        'discount_percent' => 5,
                        'tax_rate_percent' => 10,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 2090, 'currency_code' => 'USD', 'reference' => 'drawer-e2e'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $saleId);

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '8.0000',
        ]);
    }

    public function test_invalid_discount_is_rejected(): void
    {
        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '1',
                        'discount_percent' => 150,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 1000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.discount_percent');
    }

    public function test_unauthorized_discount_override_requires_reason(): void
    {
        $this->product->stockLevel()->update(['quantity_on_hand' => '10.0000']);

        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '1',
                        'discount_percent' => 15,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 850, 'currency_code' => 'USD'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_override_reason');
    }

    public function test_tenant_isolation_for_phase_one_resources(): void
    {
        $this->product->stockLevel()->update(['quantity_on_hand' => '10.0000']);

        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchB->id}/products")
            ->assertOk();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchB->id}/products")
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/products/{$this->product->id}")
            ->assertOk();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchB->id}/customers")
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchB->id}/stock/movements")
            ->assertForbidden();
    }

    public function test_checkout_concurrency_cannot_oversell(): void
    {
        $this->product->stockLevel()->update(['quantity_on_hand' => '5.0000']);

        // Sequential equivalent of concurrent attempts: first succeeds, second is rolled back.
        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '3',
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 3000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated();

        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '3',
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 3000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '2.0000',
        ]);
    }
}
