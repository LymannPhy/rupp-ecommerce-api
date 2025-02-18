<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid()); 
            $table->string('title'); 
            $table->text('content'); 
            $table->string('image')->nullable(); 
            $table->json('youtube_videos')->nullable(); 
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade'); 
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft'); 
            $table->timestamp('published_at')->nullable(); 
            $table->unsignedBigInteger('views')->default(0)->comment('Number of times the blog has been viewed'); 
            $table->boolean('is_deleted')->default(false); 
            $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
