<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_templates', function (Blueprint $table) {
            $table->id();
            $table->string('route_name', 160);
            $table->string('origin', 160);
            $table->string('destination', 160);
            $table->string('departure_time', 5);
            $table->string('weekdays', 20);
            $table->unsignedInteger('total_seats');
            $table->decimal('standard_price', 12, 2)->default(0);
            $table->decimal('per_seat_cost', 12, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('trip_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedInteger('seat_number');
            $table->timestamps();

            $table->unique(['trip_id', 'seat_number']);
        });

        Schema::create('company_trip_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'trip_id']);
        });

        Schema::create('booking_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('trip_seat_id')->constrained('trip_seats')->cascadeOnDelete();
            $table->unsignedInteger('seat_number');
            $table->timestamps();

            $table->unique('trip_seat_id');
            $table->index(['booking_id', 'seat_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_seats');
        Schema::dropIfExists('company_trip_prices');
        Schema::dropIfExists('trip_seats');
        Schema::dropIfExists('trip_templates');
    }
};
