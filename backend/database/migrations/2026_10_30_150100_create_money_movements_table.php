<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('payment_account_id');
            $table->string('movement_type', 30);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->string('direction', 10);
            $table->string('source', 40);
            $table->string('source_id', 100)->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('reason');
            $table->ulid('related_movement_id')->nullable();
            $table->string('transfer_group_id', 64)->nullable();
            $table->ulid('reverses_movement_id')->nullable();
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['branch_id', 'idempotency_key']);
            $table->unique('reverses_movement_id');
            $table->index(['branch_id', 'payment_account_id', 'created_at']);
            $table->index(['branch_id', 'movement_type', 'created_at']);
            $table->index(['branch_id', 'source', 'source_id']);
            $table->index('transfer_group_id');

            $table->foreign(['payment_account_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('payment_accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('related_movement_id')
                ->references('id')
                ->on('money_movements')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('reverses_movement_id')
                ->references('id')
                ->on('money_movements')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_movements');
    }
};
