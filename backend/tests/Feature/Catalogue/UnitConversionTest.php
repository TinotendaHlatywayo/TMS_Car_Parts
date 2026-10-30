<?php

namespace Tests\Feature\Catalogue;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class UnitConversionTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');
        $this->admin = $this->user('Branch A Admin');
        $this->membership($this->branchA, $this->admin, 'admin');
    }

    public function test_alternate_unit_is_created_with_explicit_factor_and_price(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-1');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/units", [
                'unit_id' => $box->id,
                'conversion_factor' => '10',
                'selling_price' => '99.50',
            ])
            ->assertCreated()
            ->assertJsonPath('data.unit.code', 'BOX')
            ->assertJsonPath('data.conversion_factor', '10.000000')
            ->assertJsonPath('data.selling_price_minor', 9950)
            ->assertJsonPath('data.selling_price', '99.50')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'product_unit.created']);
    }

    public function test_the_same_unit_cannot_be_configured_twice_for_one_product(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-DUP');
        $this->alternateUnit($product, $box);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/units", [
                'unit_id' => $box->id,
                'conversion_factor' => '12',
                'selling_price' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unit_id');
    }

    public function test_fractional_factor_is_rejected_when_the_base_unit_does_not_allow_decimals(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $kg = $this->unit($this->branchA, 'KG', true);
        $product = $this->product($this->branchA, $pcs, null, 'CONV-2');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/units", [
                'unit_id' => $kg->id,
                'conversion_factor' => '1.5',
                'selling_price' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversion_factor');
    }

    public function test_decimal_base_unit_supports_fractional_conversions(): void
    {
        $kg = $this->unit($this->branchA, 'KG', true);
        $bag = $this->unit($this->branchA, 'BAG', true);
        $product = $this->product($this->branchA, $kg, null, 'CONV-3');
        $this->alternateUnit($product, $bag, '2.5', 500);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $bag->id,
                'to_unit_id' => $kg->id,
                'quantity' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity', '2.5')
            ->assertJsonPath('data.from_factor', '2.5')
            ->assertJsonPath('data.to_factor', '1')
            ->assertJsonPath('data.from_unit.code', 'BAG')
            ->assertJsonPath('data.to_unit.code', 'KG');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $kg->id,
                'to_unit_id' => $bag->id,
                'quantity' => '5',
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity', '2');
    }

    public function test_conversion_through_base_between_two_alternate_units(): void
    {
        $kg = $this->unit($this->branchA, 'KG', true);
        $bag = $this->unit($this->branchA, 'BAG', true);
        $pallet = $this->unit($this->branchA, 'PALLET', true);
        $product = $this->product($this->branchA, $kg, null, 'CONV-4');
        $this->alternateUnit($product, $bag, '2.5', 500);
        $this->alternateUnit($product, $pallet, '100', 20000);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $pallet->id,
                'to_unit_id' => $bag->id,
                'quantity' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity', '40')
            ->assertJsonPath('data.from_unit.code', 'PALLET')
            ->assertJsonPath('data.to_unit.code', 'BAG');
    }

    public function test_fractional_conversion_result_into_a_whole_unit_is_rejected(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-5');
        $this->alternateUnit($product, $box, '10');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $pcs->id,
                'to_unit_id' => $box->id,
                'quantity' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_unit_id');
    }

    public function test_fractional_quantity_from_a_whole_source_unit_is_rejected(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-6');
        $this->alternateUnit($product, $box, '10');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $pcs->id,
                'to_unit_id' => $box->id,
                'quantity' => '0.5',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_same_unit_conversion_is_identity(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-7');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $pcs->id,
                'to_unit_id' => $pcs->id,
                'quantity' => '3',
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity', '3');
    }

    public function test_unknown_or_cross_branch_units_are_rejected(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $foreign = $this->unit($this->branchB, 'BOX');
        $unconfigured = $this->unit($this->branchA, 'LTR', true);
        $product = $this->product($this->branchA, $pcs, null, 'CONV-8');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $pcs->id,
                'to_unit_id' => $foreign->id,
                'quantity' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_unit_id');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $pcs->id,
                'to_unit_id' => $unconfigured->id,
                'quantity' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_unit_id');
    }

    public function test_archived_unit_master_cannot_be_used_for_conversion(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-ARCHIVED-MASTER');
        $this->alternateUnit($product, $box);
        $box->update(['is_active' => false]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $box->id,
                'to_unit_id' => $pcs->id,
                'quantity' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from_unit_id');
    }

    public function test_inactive_alternate_unit_cannot_be_used_for_conversion(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-9');
        $this->alternateUnit($product, $box, '10', 5000, false);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/convert", [
                'from_unit_id' => $box->id,
                'to_unit_id' => $pcs->id,
                'quantity' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from_unit_id');
    }

    public function test_archived_unit_master_prevents_reactivating_a_product_unit(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-REACTIVATE');
        $productUnit = $this->alternateUnit($product, $box, '10', 5000, false);
        $box->update(['is_active' => false]);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/products/{$product->id}/units/{$productUnit->id}", [
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_active');
    }

    public function test_zero_factor_is_rejected_at_validation(): void
    {
        $pcs = $this->unit($this->branchA, 'PCS');
        $box = $this->unit($this->branchA, 'BOX');
        $product = $this->product($this->branchA, $pcs, null, 'CONV-10');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/products/{$product->id}/units", [
                'unit_id' => $box->id,
                'conversion_factor' => '0',
                'selling_price' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversion_factor');
    }
}
