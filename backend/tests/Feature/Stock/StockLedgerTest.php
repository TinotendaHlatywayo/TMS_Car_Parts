<?php

namespace Tests\Feature\Stock;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $storekeeper;

    private User $cashier;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->admin = $this->user('Branch A Admin');
        $this->storekeeper = $this->user('Branch A Storekeeper');
        $this->cashier = $this->user('Branch A Cashier');
        $this->adminB = $this->user('Branch B Admin');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->storekeeper, 'storekeeper');
        $this->membership($this->branchA, $this->cashier, 'cashier');
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_opening_stock_posts_a_durable_movement_and_is_idempotent(): void
    {
        $unit = $this->unit($this->branchA, 'PCS');
        $product = $this->product($this->branchA, $unit, null, 'OPEN-1');
        $key = (string) Str::uuid();

        $response = $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '10',
            'unit_id' => $unit->id,
        ], $key);

        $response
            ->assertCreated()
            ->assertJsonPath('data.movement_type', 'opening')
            ->assertJsonPath('data.source', 'opening')
            ->assertJsonPath('data.source_id', null)
            ->assertJsonPath('data.quantity', '10.0000')
            ->assertJsonPath('data.unit_quantity', '10.0000')
            ->assertJsonPath('data.balance_after', '10.0000')
            ->assertJsonPath('data.reason', 'Opening stock')
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.unit.id', $unit->id)
            ->assertJsonPath('data.actor.id', $this->admin->id)
            ->assertJsonPath('data.is_reversed', false);

        $movementId = $response->json('data.id');

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchA->id,
            'product_id' => $product->id,
            'quantity_on_hand' => '10.0000',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'stock.opening.posted',
            'entity_id' => $movementId,
        ]);

        $replay = $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '10',
            'unit_id' => $unit->id,
        ], $key);

        $replay
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.id', $movementId)
            ->assertJsonPath('data.balance_after', '10.0000');

        $this->assertSame(1, StockMovement::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(1, StockMovement::where('idempotency_key', $key)->count());
        $this->assertStockBalance($product, '10.0000');
    }

    public function test_same_idempotency_key_with_a_different_payload_is_rejected(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);
        $key = (string) Str::uuid();

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '3',
            'unit_id' => $unit->id,
        ], $key)->assertCreated();

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '4',
            'unit_id' => $unit->id,
        ], $key)
            ->assertStatus(409)
            ->assertJsonPath('message', 'The Idempotency-Key has already been used with a different stock posting payload.');

        $this->assertSame(1, StockMovement::where('branch_id', $this->branchA->id)->count());
        $this->assertStockBalance($product, '3.0000');
    }

    public function test_opening_stock_can_only_be_posted_once_per_product(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '3',
            'unit_id' => $unit->id,
        ])->assertCreated();

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '2',
            'unit_id' => $unit->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Opening stock has already been posted for this product; use a correction.');

        $this->assertSame(1, StockMovement::where('branch_id', $this->branchA->id)->count());
        $this->assertStockBalance($product, '3.0000');
    }

    public function test_approved_corrections_change_the_balance_and_cannot_make_it_negative(): void
    {
        $unit = $this->unit($this->branchA, 'PCS', true);
        $product = $this->product($this->branchA, $unit);
        $this->opening($product, $unit, '10');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '-2.5',
            'unit_id' => $unit->id,
            'reason' => 'Stocktake shortage',
        ])
            ->assertCreated()
            ->assertJsonPath('data.movement_type', 'correction')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.quantity', '-2.5000')
            ->assertJsonPath('data.balance_after', '7.5000')
            ->assertJsonPath('data.reason', 'Stocktake shortage');

        $this->storeMovement($this->storekeeper, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '1.5',
            'unit_id' => $unit->id,
            'reason' => 'Stocktake surplus',
        ])
            ->assertCreated()
            ->assertJsonPath('data.balance_after', '9.0000');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '-20',
            'unit_id' => $unit->id,
            'reason' => 'Too much',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Insufficient stock: the posting would make quantity on hand negative.');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '0',
            'unit_id' => $unit->id,
            'reason' => 'No change',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '1',
            'unit_id' => $unit->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertStockBalance($product, '9.0000');
    }

    public function test_alternate_units_are_converted_to_base_units_and_snapshotted(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS', true);
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'BOX-1');
        $this->alternateUnit($product, $box, '12.000000');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '2',
            'unit_id' => $box->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity', '24.0000')
            ->assertJsonPath('data.unit_quantity', '2.0000')
            ->assertJsonPath('data.unit_conversion_factor', '12.000000')
            ->assertJsonPath('data.unit.id', $box->id)
            ->assertJsonPath('data.unit.code', 'BOX');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '-1',
            'unit_id' => $box->id,
            'reason' => 'One box damaged',
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity', '-12.0000')
            ->assertJsonPath('data.balance_after', '12.0000');

        $this->assertStockBalance($product, '12.0000');
    }

    public function test_reversal_mirrors_the_original_movement_and_can_only_happen_once(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);
        $opening = $this->opening($product, $unit, '10');
        $correction = $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '-3',
            'unit_id' => $unit->id,
            'reason' => 'Damaged stock',
        ])->assertCreated()->json('data.id');

        $key = (string) Str::uuid();
        $reversal = $this->reverseMovement($this->admin, $this->branchA, $correction, 'Correction was entered in error', $key)
            ->assertCreated()
            ->assertJsonPath('data.movement_type', 'reversal')
            ->assertJsonPath('data.source', 'reversal')
            ->assertJsonPath('data.source_id', $correction)
            ->assertJsonPath('data.quantity', '3.0000')
            ->assertJsonPath('data.balance_after', '10.0000')
            ->assertJsonPath('data.reverses_movement_id', $correction)
            ->assertJsonPath('data.reverses.id', $correction);

        $reversalId = $reversal->json('data.id');

        $this->reverseMovement($this->admin, $this->branchA, $correction, 'Correction was entered in error', $key)
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.id', $reversalId);

        $this->reverseMovement($this->admin, $this->branchA, $correction, 'Duplicate reversal', (string) Str::uuid())
            ->assertStatus(409)
            ->assertJsonPath('message', 'This stock movement has already been reversed.');

        $this->reverseMovement($this->admin, $this->branchA, $reversalId, 'Reverse the reversal', (string) Str::uuid())
            ->assertStatus(409)
            ->assertJsonPath('message', 'A reversal movement cannot itself be reversed.');

        $this->assertSame($opening, StockMovement::findOrFail($opening)->id);
        $this->assertStockBalance($product, '10.0000');
    }

    public function test_reversal_that_would_make_stock_negative_is_rejected(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);
        $this->opening($product, $unit, '10');

        $correction = $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '5',
            'unit_id' => $unit->id,
            'reason' => 'Surplus',
        ])->assertCreated()->json('data.id');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '-12',
            'unit_id' => $unit->id,
            'reason' => 'Sale-ready stock reduction',
        ])->assertCreated();

        $this->assertStockBalance($product, '3.0000');

        $this->reverseMovement($this->admin, $this->branchA, $correction, 'Reverse surplus', (string) Str::uuid())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Insufficient stock: the posting would make quantity on hand negative.');

        $this->assertStockBalance($product, '3.0000');
    }

    public function test_stock_posting_rolls_back_completely_when_the_outer_transaction_fails(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);
        $beforeMovements = StockMovement::count();

        try {
            DB::transaction(function () use ($product, $unit): void {
                app(StockLedger::class)->postOpening(
                    $this->branchA,
                    $product,
                    '7',
                    $unit->id,
                    null,
                    $this->admin,
                    (string) Str::uuid(),
                );

                throw new RuntimeException('Force outer rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force outer rollback', $exception->getMessage());
        }

        $this->assertSame($beforeMovements, StockMovement::count());
        $this->assertStockBalance($product, '0.0000');
    }

    public function test_sale_posting_service_is_sale_ready_and_cannot_oversell(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);
        $this->opening($product, $unit, '5');
        $key = (string) Str::uuid();

        $sale = app(StockLedger::class)->postSale(
            $this->branchA,
            $product,
            '2',
            $unit->id,
            'sale-100',
            $this->cashier,
            $key,
        );

        $this->assertSame('sale', $sale->movement->movement_type);
        $this->assertSame('sale', $sale->movement->source);
        $this->assertSame('sale-100', $sale->movement->source_id);
        $this->assertSame('-2.0000', (string) $sale->movement->quantity);
        $this->assertSame('3.0000', (string) $sale->movement->balance_after);
        $this->assertFalse($sale->replayed);

        $replay = app(StockLedger::class)->postSale(
            $this->branchA,
            $product,
            '2',
            $unit->id,
            'sale-100',
            $this->cashier,
            $key,
        );

        $this->assertTrue($replay->replayed);
        $this->assertSame($sale->movement->id, $replay->movement->id);

        try {
            app(StockLedger::class)->postSale(
                $this->branchA,
                $product,
                '4',
                $unit->id,
                'sale-101',
                $this->cashier,
                (string) Str::uuid(),
            );
            $this->fail('Expected overselling to be rejected.');
        } catch (ConflictHttpException $exception) {
            $this->assertSame('Insufficient stock: the posting would make quantity on hand negative.', $exception->getMessage());
        }

        $this->assertStockBalance($product, '3.0000');
        $this->assertSame(2, StockMovement::where('branch_id', $this->branchA->id)->count());
    }

    public function test_cashier_can_view_ledger_but_cannot_post_and_storekeeper_can_post(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);

        $this->getJson("/api/branches/{$this->branchA->id}/stock/levels")
            ->assertUnauthorized();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/stock/levels")
            ->assertOk();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/stock/movements")
            ->assertOk();

        $this->storeMovement($this->cashier, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '1',
            'unit_id' => $unit->id,
        ])->assertForbidden();

        $this->storeMovement($this->storekeeper, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '1',
            'unit_id' => $unit->id,
        ])->assertCreated();
    }

    public function test_cross_branch_stock_data_is_not_readable_or_mutable(): void
    {
        $unitA = $this->unit($this->branchA);
        $unitB = $this->unit($this->branchB, 'PCS-B');
        $productA = $this->product($this->branchA, $unitA, null, 'A-1');
        $productB = $this->product($this->branchB, $unitB, null, 'B-1');

        $movementB = $this->storeMovement($this->adminB, $this->branchB, $productB, [
            'type' => 'opening',
            'quantity' => '8',
            'unit_id' => $unitB->id,
        ])->assertCreated()->json('data.id');

        $this->storeMovement($this->admin, $this->branchA, $productB, [
            'type' => 'opening',
            'quantity' => '2',
            'unit_id' => $unitB->id,
        ])->assertNotFound();

        $this->storeMovement($this->admin, $this->branchA, $productA, [
            'type' => 'opening',
            'quantity' => '2',
            'unit_id' => $unitB->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from_unit_id');

        $this->reverseMovement($this->admin, $this->branchA, $movementB, 'Cross branch reversal', (string) Str::uuid())
            ->assertNotFound();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/stock/movements")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchB->id}/stock/movements")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $movementB);
    }

    public function test_ledger_requires_an_idempotency_key_and_rejects_unsupported_types(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/stock/movements", [
                'type' => 'opening',
                'quantity' => '1',
                'unit_id' => $unit->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'sale',
            'quantity' => '1',
            'unit_id' => $unit->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_stock_movements_are_rejected_for_products_that_do_not_track_stock(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit, null, 'SERV-1', 'Service', null, 1000, false);

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '1',
            'unit_id' => $unit->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_archived_products_reject_new_opening_but_allow_corrections(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit);
        $this->opening($product, $unit, '4');
        $product->update(['is_active' => false]);

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => '1',
            'unit_id' => $unit->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'correction',
            'quantity' => '-1',
            'unit_id' => $unit->id,
            'reason' => 'Archive cleanup',
        ])->assertCreated();

        $this->assertStockBalance($product, '3.0000');
    }

    private function storeMovement(
        User $user,
        Branch $branch,
        Product $product,
        array $payload,
        ?string $idempotencyKey = null,
    ) {
        return $this->withAuthToken($user)
            ->withHeader('Idempotency-Key', $idempotencyKey ?? (string) Str::uuid())
            ->postJson("/api/branches/{$branch->id}/products/{$product->id}/stock/movements", $payload);
    }

    private function reverseMovement(
        User $user,
        Branch $branch,
        string $movementId,
        string $reason,
        string $idempotencyKey,
    ) {
        return $this->withAuthToken($user)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/branches/{$branch->id}/stock/movements/{$movementId}/reverse", [
                'reason' => $reason,
            ]);
    }

    private function opening(Product $product, Unit $unit, string $quantity): string
    {
        return $this->storeMovement($this->admin, $this->branchA, $product, [
            'type' => 'opening',
            'quantity' => $quantity,
            'unit_id' => $unit->id,
        ])->assertCreated()->json('data.id');
    }

    private function assertStockBalance(Product $product, string $expected): void
    {
        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $product->branch_id,
            'product_id' => $product->id,
            'quantity_on_hand' => $expected,
        ]);
    }
}
