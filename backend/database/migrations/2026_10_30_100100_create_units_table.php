<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->string('symbol', 10)->nullable();
            $table->boolean('allow_decimal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['id', 'branch_id']);
            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
