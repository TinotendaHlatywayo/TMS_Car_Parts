<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('sale_id');
            $table->string('receipt_number', 64);
            $table->timestamp('issued_at')->useCurrent();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'receipt_number']);
            $table->unique('sale_id');

            $table->foreign(['sale_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('sales')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
