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
            $table->string('name')->unique()->index();
            $table->string('serial')->unique()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
