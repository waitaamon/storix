<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WaitAmon\Storix\Support\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TableNames::dispatches(), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('container_id');
            $table->uuid('dispatched_by');
            $table->text('delivery_note')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestampTz('dispatched_at')->index();
            $table->text('dispatched_note')->nullable();

            $table->timestampTz('return_date')->nullable()->index();
            $table->string('return_condition')->nullable();
            $table->text('return_note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('container_id')
                ->references('id')
                ->on(TableNames::containers())
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('customer_id')
                ->references('id')
                ->on(TableNames::customers())
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('dispatched_by')
                ->references('id')
                ->on(TableNames::users())
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('received_by')
                ->references('id')
                ->on(TableNames::users())
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['container_id', 'dispatched_at']);
            $table->index(['customer_id', 'dispatched_at']);
            $table->index(['container_id', 'return_date']);
            $table->index('container_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableNames::dispatches());
    }
};
