<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('customer_id')->nullable();
            $table->string('sale_number', 64);
            $table->string('status', 20)->default('posted');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'sale_number']);
            $table->unique(['branch_id', 'idempotency_key']);
            $table->unique(['id', 'branch_id']);
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['branch_id', 'payment_status', 'created_at']);

            $table->foreign(['customer_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('customers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
