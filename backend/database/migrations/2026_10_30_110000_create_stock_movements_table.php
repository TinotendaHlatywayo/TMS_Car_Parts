<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('product_id');
            $table->ulid('unit_id');
            $table->string('movement_type', 20);
            $table->string('source', 40);
            $table->string('source_id', 100)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_quantity', 18, 4);
            $table->decimal('unit_conversion_factor', 18, 6)->default(1);
            $table->decimal('balance_after', 18, 4);
            $table->string('reason', 500);
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->string('opening_marker', 20)->nullable();
            $table->ulid('reverses_movement_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['branch_id', 'idempotency_key']);
            $table->unique(['branch_id', 'product_id', 'opening_marker']);
            $table->unique('reverses_movement_id');
            $table->index(['branch_id', 'product_id', 'created_at']);
            $table->index(['branch_id', 'movement_type', 'created_at']);
            $table->index(['branch_id', 'source', 'source_id']);

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

            $table->foreign('reverses_movement_id')
                ->references('id')
                ->on('stock_movements')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
