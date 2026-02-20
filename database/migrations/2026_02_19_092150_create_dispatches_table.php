<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Storix\Models\States\DispatchDraftState;
use Storix\Support\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TableNames::dispatches(), function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable()->index();
            $table->foreignId('dispatched_by')->index()->constrained(TableNames::users());
            $table->foreignId('delivery_note_id')->index()->constrained(TableNames::deliveryNotes());
            $table->date('dispatched_at')->nullable();
            $table->text('dispatch_note')->nullable();
            $table->string('state')->default(DispatchDraftState::$name);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }
};
