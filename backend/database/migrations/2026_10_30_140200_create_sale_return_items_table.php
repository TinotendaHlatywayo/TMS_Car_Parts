<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('sale_return_id');
            $table->ulid('sale_item_id');
            $table->ulid('product_id');
            $table->decimal('quantity', 18, 4);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['sale_return_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('sale_returns')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign(['sale_item_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('sale_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign(['product_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
