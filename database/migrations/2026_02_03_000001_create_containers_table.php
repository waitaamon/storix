<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('storix.tables.containers', 'containers'), function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('serial')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('storix.tables.containers', 'containers'));
    }
};
