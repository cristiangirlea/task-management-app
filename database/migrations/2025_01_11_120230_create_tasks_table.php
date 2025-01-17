<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('title', 255); // Task title (renamed from 'name' to 'title' for clarity)
            $table->text('description')->nullable(); // Optional task description
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending'); // Task status with a default
            $table->unsignedTinyInteger('priority')->default(3); // Priority (1-5, for example), default set to '3'
            $table->dateTime('due_date')->nullable(); // Optional due date for the task
            $table->foreignId('project_id')->constrained()->onDelete('cascade'); // Foreign key to 'projects' table
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Optional assignee
            $table->timestamps(); // Created and updated timestamps
            $table->softDeletes(); // For soft deleting tasks
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
