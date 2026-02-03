<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('storix.tables.returns', 'container_returns');
        $customerTable = (string) config('storix.customer_table', 'customers');
        $userTable = (string) config('storix.user_table', 'users');

        Schema::create($tableName, function (Blueprint $table) use ($customerTable, $userTable): void {
            $table->id();
            $table->foreignId('customer_id')->constrained($customerTable);
            $table->date('transaction_date')->index();
            $table->foreignId('user_id')->nullable()->constrained($userTable)->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('storix.tables.returns', 'container_returns'));
    }
};
