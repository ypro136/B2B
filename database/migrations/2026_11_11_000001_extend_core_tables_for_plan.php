<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('legal_name', 160)->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->string('address')->nullable();
            $table->boolean('partial_payment_allowed')->default(false);
            $table->unsignedInteger('bonus_seats')->default(0);
            $table->timestamp('quota_reset_at')->nullable();
            $table->string('language', 5)->default('en');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('standard_price', 12, 2)->default(0);
            $table->decimal('per_seat_cost', 12, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->unsignedInteger('cutoff_hours')->nullable();
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->timestamp('departure_start')->nullable()->index();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('settled_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->boolean('vat_enabled')->default(true);
            $table->decimal('vat_rate', 5, 2)->default(14);
            $table->string('settlement_status', 20)->default('unsettled')->index();
            $table->timestamp('settled_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 5)->default('en');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('language');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'unit_price',
                'total_amount',
                'settled_amount',
                'currency',
                'vat_enabled',
                'vat_rate',
                'settlement_status',
                'settled_at',
            ]);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'standard_price',
                'per_seat_cost',
                'currency',
                'cutoff_hours',
                'template_id',
                'departure_start',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'tax_number',
                'address',
                'partial_payment_allowed',
                'bonus_seats',
                'quota_reset_at',
                'language',
            ]);
        });
    }
};
