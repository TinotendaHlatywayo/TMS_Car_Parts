<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bill_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('supplier_bill_id');
            $table->foreignUlid('payment_account_id')->constrained('payment_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->date('paid_at');
            $table->string('reference', 120)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['supplier_bill_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('supplier_bills')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['branch_id', 'supplier_bill_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bill_payments');
    }
};
