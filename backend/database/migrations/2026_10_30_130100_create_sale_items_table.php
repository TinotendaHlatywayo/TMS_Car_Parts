<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('sale_id');
            $table->ulid('product_id');
            $table->string('product_name', 180);
            $table->string('sku', 64);
            $table->ulid('unit_id');
            $table->string('unit_code', 20);
            $table->decimal('quantity', 18, 4);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('currency_code', 3);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id', 'branch_id']);

            $table->foreign(['sale_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('sales')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign(['product_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['unit_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
