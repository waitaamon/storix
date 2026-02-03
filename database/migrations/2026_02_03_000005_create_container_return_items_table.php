<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Storix\Enums\ContainerConditionStatus;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('storix.tables.return_items', 'container_return_items');
        $returnsTable = (string) config('storix.tables.returns', 'container_returns');
        $dispatchItemsTable = (string) config('storix.tables.dispatch_items', 'container_dispatch_items');
        $containersTable = (string) config('storix.tables.containers', 'containers');

        Schema::create($tableName, function (Blueprint $table) use ($returnsTable, $dispatchItemsTable, $containersTable): void {
            $table->id();
            $table->foreignId('return_id')->constrained($returnsTable)->cascadeOnDelete();
            $table->foreignId('dispatch_item_id')->unique()->constrained($dispatchItemsTable);
            $table->foreignId('container_id')->constrained($containersTable);
            $table->string('condition_status')->default(ContainerConditionStatus::Good->value);
            $table->text('notes')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index('container_id');
            $table->index('condition_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('storix.tables.return_items', 'container_return_items'));
    }
};
