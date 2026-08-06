<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Storix\Support\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropView();
        $this->createEventView();
    }

    public function down(): void
    {
        $this->dropView();
        $this->createCustodyCycleView();
    }

    private function createEventView(): void
    {
        $grammar = DB::connection()->getQueryGrammar();
        $view = $grammar->wrapTable(TableNames::containerMovements());
        $dispatchEntries = $grammar->wrapTable(TableNames::dispatchEntries());
        $dispatches = $grammar->wrapTable(TableNames::dispatches());
        $returnEntries = $grammar->wrapTable(TableNames::containerReturnEntries());
        $returns = $grammar->wrapTable(TableNames::containerReturns());

        DB::statement(<<<SQL
            CREATE VIEW {$view} AS
            SELECT
                'dispatch:' || CAST(dispatch_entries.id AS TEXT) AS id,
                dispatch_entries.container_id AS container_id,
                dispatches.dispatched_at AS movement_date,
                dispatches.customer_id AS customer_id,
                'dispatch' AS document_type,
                dispatches.id AS document_id,
                dispatches.code AS document_code,
                CAST(NULL AS BOOLEAN) AS cross_return
            FROM {$dispatchEntries} AS dispatch_entries
            INNER JOIN {$dispatches} AS dispatches
                ON dispatches.id = dispatch_entries.dispatch_id
            WHERE dispatch_entries.deleted_at IS NULL
                AND dispatches.deleted_at IS NULL
                AND dispatches.state = 'approved'

            UNION ALL

            SELECT
                'return:' || CAST(return_entries.id AS TEXT) AS id,
                return_entries.container_id AS container_id,
                container_returns.transaction_date AS movement_date,
                container_returns.customer_id AS customer_id,
                'return' AS document_type,
                container_returns.id AS document_id,
                container_returns.code AS document_code,
                return_entries.cross_return AS cross_return
            FROM {$returnEntries} AS return_entries
            INNER JOIN {$returns} AS container_returns
                ON container_returns.id = return_entries.container_return_id
            WHERE container_returns.deleted_at IS NULL
                AND container_returns.state = 'approved'
            SQL);
    }

    private function createCustodyCycleView(): void
    {
        $grammar = DB::connection()->getQueryGrammar();
        $view = $grammar->wrapTable(TableNames::containerMovements());
        $dispatchEntries = $grammar->wrapTable(TableNames::dispatchEntries());
        $dispatches = $grammar->wrapTable(TableNames::dispatches());
        $returnEntries = $grammar->wrapTable(TableNames::containerReturnEntries());
        $returns = $grammar->wrapTable(TableNames::containerReturns());

        DB::statement(<<<SQL
            CREATE VIEW {$view} AS
            SELECT
                dispatch_entries.id AS id,
                dispatch_entries.id AS dispatch_entry_id,
                dispatch_entries.container_id AS container_id,
                dispatches.id AS dispatch_id,
                dispatches.code AS dispatch_code,
                dispatches.customer_id AS dispatch_customer_id,
                dispatches.delivery_note_id AS delivery_note_id,
                dispatches.dispatched_by AS dispatched_by,
                dispatches.approved_by AS dispatch_approved_by,
                dispatches.dispatched_at AS dispatched_at,
                dispatches.approved_at AS dispatch_approved_at,
                dispatches.dispatch_note AS dispatch_note,
                posted_returns.container_return_entry_id AS container_return_entry_id,
                posted_returns.container_return_id AS container_return_id,
                posted_returns.return_code AS return_code,
                posted_returns.return_customer_id AS return_customer_id,
                posted_returns.return_prepared_by AS return_prepared_by,
                posted_returns.return_approved_by AS return_approved_by,
                posted_returns.returned_on AS returned_on,
                posted_returns.return_approved_at AS return_approved_at,
                posted_returns.return_condition AS return_condition,
                posted_returns.return_note AS return_note,
                COALESCE(posted_returns.cross_return, false) AS cross_return,
                CASE
                    WHEN posted_returns.container_return_entry_id IS NULL THEN 'outstanding'
                    WHEN posted_returns.return_condition = 'good' THEN 'returned_good'
                    WHEN posted_returns.return_condition = 'damaged' THEN 'returned_damaged'
                    WHEN posted_returns.return_condition = 'lost' THEN 'lost'
                    ELSE 'outstanding'
                END AS status
            FROM {$dispatchEntries} AS dispatch_entries
            INNER JOIN {$dispatches} AS dispatches
                ON dispatches.id = dispatch_entries.dispatch_id
            LEFT JOIN (
                SELECT
                    return_entries.id AS container_return_entry_id,
                    return_entries.dispatch_entry_id AS dispatch_entry_id,
                    container_returns.id AS container_return_id,
                    container_returns.code AS return_code,
                    container_returns.customer_id AS return_customer_id,
                    container_returns.user_id AS return_prepared_by,
                    container_returns.approved_by AS return_approved_by,
                    container_returns.transaction_date AS returned_on,
                    container_returns.approved_at AS return_approved_at,
                    return_entries.return_condition AS return_condition,
                    return_entries.note AS return_note,
                    return_entries.cross_return AS cross_return
                FROM {$returnEntries} AS return_entries
                INNER JOIN {$returns} AS container_returns
                    ON container_returns.id = return_entries.container_return_id
                WHERE container_returns.state = 'approved'
                    AND container_returns.deleted_at IS NULL
            ) AS posted_returns
                ON posted_returns.dispatch_entry_id = dispatch_entries.id
            WHERE dispatch_entries.deleted_at IS NULL
                AND dispatches.deleted_at IS NULL
                AND dispatches.state = 'approved'
            SQL);
    }

    private function dropView(): void
    {
        $view = DB::connection()
            ->getQueryGrammar()
            ->wrapTable(TableNames::containerMovements());

        DB::statement("DROP VIEW IF EXISTS {$view}");
    }
};
