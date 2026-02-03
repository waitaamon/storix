<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('storix.tables.dispatch_items', 'container_dispatch_items');
        $dispatchesTable = (string) config('storix.tables.dispatches', 'container_dispatches');
        $containersTable = (string) config('storix.tables.containers', 'containers');

        Schema::create($tableName, function (Blueprint $table) use ($dispatchesTable, $containersTable): void {
            $table->id();
            $table->foreignId('dispatch_id')->index()->constrained($dispatchesTable);
            $table->foreignId('container_id')->index()->constrained($containersTable);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('storix.tables.dispatch_items', 'container_dispatch_items'));
    }
};
