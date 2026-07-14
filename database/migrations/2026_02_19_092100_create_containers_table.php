<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Storix\Support\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TableNames::containers(), function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('serial')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('replacement_cost', 19, 4)->default(0);
            $table->char('replacement_currency', 3)->default('USD');
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }
};
