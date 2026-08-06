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
        Schema::create(TableNames::containerReturns(), function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('customer_id')->index();
            $table->foreignId('user_id');
            $table->foreignId('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable()->index();
            $table->text('note')->nullable();
            $table->string('state')->default('draft')->index();
            $table->date('transaction_date')->index();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }
};
