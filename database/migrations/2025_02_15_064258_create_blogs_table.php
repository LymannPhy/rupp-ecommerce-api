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
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['published', 'unpublished'])->default('unpublished');
            $table->timestamp('published_at')->nullable(); 
            $table->unsignedBigInteger('views')->default(0)->comment('Number of times the blog has been viewed'); 
            $table->boolean('is_awarded')->nullable()->default(false);
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('award_type', ['best_content', 'most_viewed', 'most_liked'])->nullable();
            $table->enum('award_rank', ['1', '2', '3'])->nullable();
            $table->boolean('is_deleted')->default(false); 
            $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
