<?php

namespace Tests\Feature\Sales;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class SaleHistoryTest extends TestCase
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

    private Unit $unit;

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
        $this->product = $this->product($this->branchA, $this->unit, null, 'HIST-1', 'History Product', null, 1000);
        $this->product->stockLevel()->update(['quantity_on_hand' => '10.0000']);
    }

    private function checkout(int $quantity = 2): string
    {
        return $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => $quantity,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 1000 * $quantity, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_sales_history_lists_branch_sales(): void
    {
        $saleId = $this->checkout();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/sales")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $saleId);

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');
    }

    public function test_authorized_void_reverses_stock_and_is_idempotent(): void
    {
        $saleId = $this->checkout(2);

        $this->assertStockBalance('8.0000');

        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/void", [
                'reason' => 'Customer changed mind',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.payment_status', 'voided')
            ->assertJsonPath('data.void_reason', 'Customer changed mind');

        $this->assertStockBalance('10.0000');

        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/void", [
                'reason' => 'Duplicate void',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.void_reason', 'Customer changed mind');

        $this->assertStockBalance('10.0000');
        $this->assertSame(1, Sale::where('branch_id', $this->branchA->id)->where('status', 'voided')->count());
    }

    public function test_void_requires_permission_and_reason(): void
    {
        $saleId = $this->checkout();

        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/void", [
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/void", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_return_request_is_created_and_duplicate_submission_is_idempotent(): void
    {
        $saleId = $this->checkout(2);
        $sale = Sale::findOrFail($saleId);
        $saleItemId = $sale->items->first()->id;

        $key = (string) Str::uuid();

        $response = $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/returns", [
                'reason' => 'Defective item',
                'items' => [
                    ['sale_item_id' => $saleItemId, 'quantity' => 1],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.restock_decision', 'pending')
            ->assertJsonPath('data.refund_status', 'none')
            ->assertJsonPath('data.items.0.quantity', '1.0000');

        $returnId = $response->json('data.id');

        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/returns", [
                'reason' => 'Defective item',
                'items' => [
                    ['sale_item_id' => $saleItemId, 'quantity' => 1],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $returnId);

        $this->assertSame(1, SaleReturn::where('branch_id', $this->branchA->id)->count());

        $this->withAuthToken($this->manager)
            ->getJson("/api/branches/{$this->branchA->id}/returns")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withAuthToken($this->manager)
            ->getJson("/api/branches/{$this->branchA->id}/returns/{$returnId}")
            ->assertOk()
            ->assertJsonPath('data.id', $returnId);
    }

    public function test_return_quantity_cannot_exceed_sold_quantity(): void
    {
        $saleId = $this->checkout(2);
        $sale = Sale::findOrFail($saleId);
        $saleItemId = $sale->items->first()->id;

        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/returns", [
                'reason' => 'Too many',
                'items' => [
                    ['sale_item_id' => $saleItemId, 'quantity' => 3],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_return_requires_permission(): void
    {
        $saleId = $this->checkout();

        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/sales/{$saleId}/returns", [
                'reason' => 'Not allowed',
                'items' => [],
            ])
            ->assertForbidden();
    }

    public function test_cross_branch_sale_history_and_void_are_isolated(): void
    {
        $saleId = $this->checkout();

        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchB->id}/sales/{$saleId}")
            ->assertNotFound();

        $this->withAuthToken($this->adminB)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchB->id}/sales/{$saleId}/void", [
                'reason' => 'Cross branch',
            ])
            ->assertNotFound();
    }

    private function assertStockBalance(string $expected): void
    {
        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => $expected,
        ]);
    }
}
