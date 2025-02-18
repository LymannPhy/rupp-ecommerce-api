<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('uuid')->primary(); 
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned()->comment('1-5 scale');
            $table->text('review')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
