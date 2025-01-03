<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('gedcom_id')->nullable(); // @I1@, etc.
            $table->string('name');
            $table->string('given_name')->nullable();
            $table->string('surname')->nullable();
            $table->enum('sex', ['M', 'F', 'U'])->default('U');
            $table->date('birth_date')->nullable();
            $table->date('death_date')->nullable();
            $table->timestamps();
            
            // Index for faster lookups
            $table->index(['user_id', 'gedcom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individuals');
    }
}; 