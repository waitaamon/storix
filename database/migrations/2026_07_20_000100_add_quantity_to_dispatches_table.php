<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Storix\Support\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $table = $grammar->wrapTable(TableNames::dispatches());
        $quantity = $grammar->wrap('quantity');

        $connection->statement(
            "ALTER TABLE {$table} ADD COLUMN {$quantity} INTEGER NOT NULL CHECK ({$quantity} > 0)",
        );
    }
};
