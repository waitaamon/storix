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
        Schema::table(TableNames::containerReturns(), function (Blueprint $table): void {
            $table->index(
                ['customer_id', 'state', 'transaction_date'],
                'storix_cr_customer_state_date_idx',
            );
        });

        Schema::table(TableNames::containerReturnEntries(), function (Blueprint $table): void {
            $table->index(
                ['container_return_id', 'container_id'],
                'storix_cre_return_container_idx',
            );
            $table->unique('dispatch_entry_id', 'storix_cre_dispatch_entry_uidx');
        });
    }

    public function down(): void
    {
        Schema::table(TableNames::containerReturnEntries(), function (Blueprint $table): void {
            $table->dropUnique('storix_cre_dispatch_entry_uidx');
            $table->dropIndex('storix_cre_return_container_idx');
        });

        Schema::table(TableNames::containerReturns(), function (Blueprint $table): void {
            $table->dropIndex('storix_cr_customer_state_date_idx');
        });
    }
};
