<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('platform_id')->constrained('platforms')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('timezone', 64)->default('Africa/Harare');
            $table->string('currency_code', 3)->default('USD');
            $table->string('host')->nullable()->index();
            $table->json('settings')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();
            $table->index(['platform_id', 'code']);
            $table->index(['platform_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
