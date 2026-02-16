<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WaitAmon\Storix\Support\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TableNames::containers(), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('serial')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('name');
            $table->index('serial');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableNames::containers());
    }
};
