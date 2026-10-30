<?php

namespace Tests\Feature\Catalogue;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class CatalogueSearchTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branch;

    private User $admin;

    private Category $filters;

    private Category $brakes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branch = $this->branch($this->platform, 'BTA');
        $this->admin = $this->user('Branch Admin');
        $this->membership($this->branch, $this->admin, 'admin');

        $unit = $this->unit($this->branch, 'PCS');
        $this->filters = $this->category($this->branch, 'Filters');
        $this->brakes = $this->category($this->branch, 'Brakes');

        $this->product($this->branch, $unit, $this->filters, 'OF-100', 'Oil Filter', '111111', 1000);
        $this->product($this->branch, $unit, $this->filters, 'OF-200', 'Oil Pump', '222222', 2000);
        $this->product($this->branch, $unit, $this->brakes, 'BP-100', 'Brake Pad', '333333', 3000);
        $this->product($this->branch, $unit, $this->brakes, 'BP-200', 'Brake Disc', '444444', 4000);
        $this->product($this->branch, $unit, $this->brakes, 'OLD-1', 'Oil Filter Old', '555555', 5000)
            ->update(['is_active' => false]);
    }

    public function test_default_list_returns_only_active_products(): void
    {
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 4);
    }

    public function test_search_matches_sku_prefix_name_prefix_and_exact_barcode(): void
    {
        $sku = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?q=OF-")
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['OF-100', 'OF-200'],
            array_column($sku->json('data'), 'sku'),
        );

        $name = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?q=Oil")
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['OF-100', 'OF-200'],
            array_column($name->json('data'), 'sku'),
        );

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?q=333333")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'BP-100');
    }

    public function test_category_and_status_filters_are_combined(): void
    {
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?category_id={$this->filters->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?status=inactive")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'OLD-1');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?status=all")
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    public function test_pagination_meta_and_query_string_are_returned(): void
    {
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?per_page=2&sort=sku&direction=asc")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sku', 'BP-100')
            ->assertJsonPath('data.1.sku', 'BP-200')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 4);
    }

    public function test_invalid_status_and_sort_fall_back_to_safe_defaults(): void
    {
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?status=deleted&sort=sql_injection&direction=sideways")
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_product_payload_includes_category_base_unit_price_and_stock(): void
    {
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/products?q=OF-100")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Oil Filter')
            ->assertJsonPath('data.0.category.name', 'Filters')
            ->assertJsonPath('data.0.base_unit.code', 'PCS')
            ->assertJsonPath('data.0.selling_price_minor', 1000)
            ->assertJsonPath('data.0.selling_price', '10.00')
            ->assertJsonPath('data.0.stock.quantity_on_hand', '0.0000')
            ->assertJsonPath('data.0.alternate_unit_count', 0);
    }
}
