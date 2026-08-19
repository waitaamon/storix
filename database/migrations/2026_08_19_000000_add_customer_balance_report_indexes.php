<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Storix\Support\TableNames;

return new class extends Migration
{
    private const string DISPATCH_CUSTOMER_INDEX = 'storix_d_state_deleted_customer_idx';

    private const string DISPATCH_ENTRY_INDEX = 'storix_de_dispatch_deleted_idx';

    private const string RETURN_CUSTOMER_INDEX = 'storix_cr_state_deleted_customer_idx';

    private const string RETURN_ENTRY_INDEX = 'storix_cre_return_idx';

    public function up(): void
    {
        Schema::table(TableNames::dispatches(), function (Blueprint $table): void {
            $table->index(['state', 'deleted_at', 'customer_id'], self::DISPATCH_CUSTOMER_INDEX);
        });

        Schema::table(TableNames::dispatchEntries(), function (Blueprint $table): void {
            $table->index(['dispatch_id', 'deleted_at'], self::DISPATCH_ENTRY_INDEX);
        });

        Schema::table(TableNames::containerReturns(), function (Blueprint $table): void {
            $table->index(['state', 'deleted_at', 'customer_id'], self::RETURN_CUSTOMER_INDEX);
        });

        Schema::table(TableNames::containerReturnEntries(), function (Blueprint $table): void {
            $table->index('container_return_id', self::RETURN_ENTRY_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table(TableNames::dispatches(), function (Blueprint $table): void {
            $table->dropIndex(self::DISPATCH_CUSTOMER_INDEX);
        });

        Schema::table(TableNames::dispatchEntries(), function (Blueprint $table): void {
            $table->dropIndex(self::DISPATCH_ENTRY_INDEX);
        });

        Schema::table(TableNames::containerReturns(), function (Blueprint $table): void {
            $table->dropIndex(self::RETURN_CUSTOMER_INDEX);
        });

        Schema::table(TableNames::containerReturnEntries(), function (Blueprint $table): void {
            $table->dropIndex(self::RETURN_ENTRY_INDEX);
        });
    }
};
