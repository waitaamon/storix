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
        Schema::create(TableNames::containerReturnEntries(), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('container_return_id');
            $table->foreignId('container_id')->index();
            $table->foreignId('dispatch_entry_id')->nullable();
            $table->string('return_condition')->index();
            $table->text('note')->nullable();
            $table->boolean('cross_return')->default(false)->index();
            $table->timestampsTz();
        });
    }
};
