<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');                            // standard, guideline, form, template, research, other
            $table->enum('visibility', ['public', 'members_only'])->default('members_only');

            // File (Cloudinary)
            $table->string('file_url');
            $table->string('file_public_id');
            $table->string('file_name');                           // Original filename
            $table->string('file_type')->nullable();               // pdf, docx, xlsx, etc.
            $table->unsignedBigInteger('file_size')->nullable();   // Bytes

            $table->integer('download_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
