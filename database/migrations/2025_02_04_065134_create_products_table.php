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
            $table->json('multi_images')->nullable(); 
            $table->unsignedBigInteger('views')->default(0)->comment('Number of times the product has been viewed');
            $table->decimal('price', 10, 2); 
            $table->integer('stock')->default(0);
            $table->boolean('is_preorder')->default(false);
            $table->string('color')->nullable(); 
            $table->string('size')->nullable();
            $table->boolean('is_recommended')->default(false); 
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
