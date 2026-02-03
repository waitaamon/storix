<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('container-movement.tables.dispatch_items', 'container_dispatch_items');
        $dispatchesTable = (string) config('container-movement.tables.dispatches', 'container_dispatches');
        $containersTable = (string) config('container-movement.tables.containers', 'containers');

        Schema::create($tableName, function (Blueprint $table) use ($dispatchesTable, $containersTable): void {
            $table->id();
            $table->foreignId('dispatch_id')->constrained($dispatchesTable)->cascadeOnDelete();
            $table->foreignId('container_id')->constrained($containersTable);
            $table->timestamps();
            $table->softDeletes();

            $table->index('container_id');
            $table->index(['dispatch_id', 'container_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('container-movement.tables.dispatch_items', 'container_dispatch_items'));
    }
};
