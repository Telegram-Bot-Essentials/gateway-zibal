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
        Schema::create('to_zibal_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('track_id');
            $table->decimal('amount', 65, 30);
            $table->decimal('received_amount', 65, 30)->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('to_zibal_attempts');
    }
};
