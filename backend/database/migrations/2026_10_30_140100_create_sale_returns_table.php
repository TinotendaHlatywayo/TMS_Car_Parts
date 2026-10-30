<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('sale_id');
            $table->string('return_number', 64);
            $table->string('status', 20)->default('requested');
            $table->string('restock_decision', 20)->default('pending');
            $table->string('refund_status', 20)->default('none');
            $table->text('reason');
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->foreignId('authorized_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'return_number']);
            $table->unique(['branch_id', 'idempotency_key']);
            $table->unique(['id', 'branch_id']);
            $table->index(['branch_id', 'sale_id', 'status']);

            $table->foreign(['sale_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('sales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
