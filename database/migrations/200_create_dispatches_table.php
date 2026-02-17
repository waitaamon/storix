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
            $table->foreignId('customer_id')->index()->constrained(TableNames::customers());
            $table->foreignId('dispatched_by')->index()->constrained(TableNames::users());
            $table->string('delivery_note')->nullable();
            $table->date('dispatched_at')->nullable();
            $table->text('dispatched_note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }
};
