<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade'); 
            $table->string('transaction_hash')->unique(); 
            $table->string('from_account_id'); 
            $table->string('to_account_id');
            $table->string('currency', 10)->default('USD');
            $table->decimal('amount', 10, 2); 
            $table->text('description')->nullable(); 
            $table->timestamp('created_date')->nullable();
            $table->timestamp('acknowledged_date')->nullable(); 
            $table->string('external_ref')->nullable();
            $table->string('payment_status')->default('paid'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
