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
        // Create products table
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('discount_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->text('description');
            $table->string('image')->nullable(); 
            $table->json('multi_images')->nullable(); 
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 5, 2)->default(0.00); 
            $table->integer('stock')->default(0);
            $table->integer('quantity')->default(1); 
            $table->decimal('glycemic_index', 5, 2)->nullable();
            $table->boolean('is_preorder')->default(false);
            $table->integer('preorder_duration')->nullable(); 
            $table->string('slogan')->nullable();
            $table->text('health_benefits')->nullable();
            $table->string('color')->nullable(); 
            $table->string('size')->nullable();
            $table->date('expiration_date')->nullable(); 
            $table->boolean('is_deleted')->default(false); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
