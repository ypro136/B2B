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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('route_name', 160);
            $table->string('origin', 160);
            $table->string('destination', 160);
            $table->timestampTz('departure_datetime');
            $table->unsignedInteger('total_seats');
            $table->string('status', 20)->default('SCHEDULED');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('departure_datetime');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
