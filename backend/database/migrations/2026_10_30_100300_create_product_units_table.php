<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->ulid('product_id');
            $table->ulid('unit_id');
            $table->decimal('conversion_factor', 18, 6);
            $table->unsignedBigInteger('selling_price_minor');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['id', 'branch_id']);
            $table->unique(['product_id', 'unit_id']);
            $table->index(['branch_id', 'unit_id']);

            $table->foreign(['product_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign(['unit_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
