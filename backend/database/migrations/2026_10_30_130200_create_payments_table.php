<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('sale_id');
            $table->string('method', 40);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->string('reference', 120)->nullable();
            $table->string('status', 20)->default('recorded');
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['sale_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('sales')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['branch_id', 'method', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
