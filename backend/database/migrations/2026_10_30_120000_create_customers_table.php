<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('phone', 40)->nullable();
            $table->string('email', 180)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'is_active', 'name']);
            $table->index(['branch_id', 'phone']);
            $table->index(['branch_id', 'email']);

            $table->unique(['id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
