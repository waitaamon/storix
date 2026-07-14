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
        Schema::create(TableNames::dispatches(), function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('dispatched_by')->constrained(TableNames::users())->restrictOnDelete();
            $table->foreignId('delivery_note_id')->constrained(TableNames::deliveryNotes())->restrictOnDelete();
            $table->timestampTz('dispatched_at')->nullable()->index();
            $table->text('dispatch_note')->nullable();
            $table->string('state')->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained(TableNames::users())->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained(TableNames::users())->nullOnDelete();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }
};
