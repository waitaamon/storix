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
        Schema::create(TableNames::dispatchEntries(), function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained(TableNames::containers());
            $table->foreignId('dispatch_id')->constrained(TableNames::dispatches());
            $table->foreignId('received_by')->index()->nullable()->constrained(TableNames::users());
            $table->date('return_date')->nullable()->index();
            $table->string('return_condition')->nullable();
            $table->text('return_note')->nullable();
            $table->timestampsTz();
        });
    }
};
