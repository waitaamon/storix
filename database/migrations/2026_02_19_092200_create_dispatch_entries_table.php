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
        Schema::create(TableNames::dispatchEntries(), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('container_id')->constrained(TableNames::containers())->restrictOnDelete();
            $table->foreignId('dispatch_id')->constrained(TableNames::dispatches())->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained(TableNames::users())->nullOnDelete();
            $table->date('return_date')->nullable()->index();
            $table->string('return_condition')->nullable()->index();
            $table->text('return_note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }
};
