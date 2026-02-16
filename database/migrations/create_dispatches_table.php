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
            $table->foreignId('container_id')->index()->constrained(TableNames::containers());
            $table->bigInteger('customer_id')->index()->unsigned();
            $table->bigInteger('dispatched_by')->index()->unsigned();
            $table->string('delivery_note')->nullable();
            $table->dateTimeTz('dispatched_at')->nullable();
            $table->text('dispatched_note')->nullable();

            $table->bigInteger('received_by')->index()->unsigned()->nullable();
            $table->dateTimeTz('return_date')->nullable()->index();
            $table->string('return_condition')->nullable();
            $table->text('return_note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }
};
