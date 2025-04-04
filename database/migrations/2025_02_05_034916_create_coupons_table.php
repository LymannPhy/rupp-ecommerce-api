<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->string('code')->unique(); 
            $table->decimal('discount_percentage', 5, 2)->nullable(); 
            $table->integer('max_usage')->nullable(); 
            $table->integer('user_limit')->nullable();
            $table->dateTime('start_date')->nullable(); 
            $table->dateTime('end_date')->nullable(); 
            $table->boolean('is_active')->default(true); 
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
