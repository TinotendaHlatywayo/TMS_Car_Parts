<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['id', 'branch_id']);
            $table->unique(['branch_id', 'slug']);
            $table->index(['branch_id', 'is_active', 'name']);

            $table->foreign(['parent_id', 'branch_id'])
                ->references(['id', 'branch_id'])
                ->on('categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
