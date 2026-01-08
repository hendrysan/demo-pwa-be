<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestamp('rented_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'book_id']);
            $table->index(['book_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
