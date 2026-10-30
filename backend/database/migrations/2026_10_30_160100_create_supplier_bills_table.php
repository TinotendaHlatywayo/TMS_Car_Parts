<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('supplier_name', 180);
            $table->string('bill_number', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->date('bill_date');
            $table->date('due_date');
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->string('status', 20)->default('open');
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['id', 'branch_id']);
            $table->unique(['branch_id', 'supplier_name', 'bill_number']);
            $table->index(['branch_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bills');
    }
};
