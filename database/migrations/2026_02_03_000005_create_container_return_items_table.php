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
        $containersTable = (string) config('storix.tables.containers', 'containers');

        Schema::create($tableName, function (Blueprint $table) use ($returnsTable, $containersTable): void {
            $table->id();
            $table->foreignId('return_id')->constrained($returnsTable);
            $table->foreignId('container_id')->index()->constrained($containersTable);
            $table->string('condition_status')->index()->default(ContainerConditionStatus::Good->value);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('storix.tables.return_items', 'container_return_items'));
    }
};
