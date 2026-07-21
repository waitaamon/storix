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
        Schema::table(TableNames::dispatches(), function (Blueprint $table): void {
            $table->index('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table(TableNames::dispatches(), function (Blueprint $table): void {
            $table->dropIndex(['approved_at']);
        });
    }
};
