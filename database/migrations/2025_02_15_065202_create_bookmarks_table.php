<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            $table->foreignId('blog_id')->constrained('blogs')->onDelete('cascade'); 
            $table->timestamps();

            $table->unique(['user_id', 'blog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
