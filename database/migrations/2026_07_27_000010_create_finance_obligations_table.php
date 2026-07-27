<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->string('party_name')->nullable();
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('installment_amount')->nullable();
            $table->unsignedSmallInteger('installments_total')->nullable();
            $table->unsignedSmallInteger('due_day')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('color', 20)->default('#7C3AED');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_obligation_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->date('paid_date');
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_obligation_payments');
        Schema::dropIfExists('finance_obligations');
    }
};
