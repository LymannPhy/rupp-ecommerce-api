<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid()); 
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('message')->comment('User feedback message');
            $table->enum('type', ['suggestion', 'bug_report', 'complaint', 'other'])->default('other');
            $table->enum('status', ['pending', 'promoted'])->default('pending')->comment('Feedback status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
