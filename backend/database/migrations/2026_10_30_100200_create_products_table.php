<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->ulid('category_id')->nullable();
            $table->ulid('base_unit_id');
            $table->string('sku', 64);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('barcode', 64)->nullable();
            $table->unsignedBigInteger('selling_price_minor')->default(0);
            $table->string('currency_code', 3);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['id', 'branch_id']);
            $table->unique(['branch_id', 'sku']);
            $table->unique(['branch_id', 'barcode']);
            $table->index(['branch_id', 'is_active', 'name']);
            $table->index(['branch_id', 'category_id', 'is_active']);

            $table->foreign(['category_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['base_unit_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
