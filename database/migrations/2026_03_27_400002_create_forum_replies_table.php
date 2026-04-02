<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->foreignId('parent_id')->nullable()->constrained('forum_replies')->cascadeOnDelete(); // Threaded replies
            $table->timestamps();
            $table->softDeletes();

            $table->index('topic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_replies');
    }
};
