<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('category', 80);
            $table->string('description', 500);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->date('incurred_date');
            $table->date('paid_date')->nullable();
            $table->foreignUlid('payment_account_id')->nullable()->constrained('payment_accounts')->cascadeOnUpdate()->nullOnDelete();
            $table->string('status', 20)->default('incurred');
            $table->string('attachment_path', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['id', 'branch_id']);
            $table->index(['branch_id', 'status', 'incurred_date']);
            $table->index(['branch_id', 'category', 'incurred_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
