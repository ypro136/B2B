<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripTemplate;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            Role::SUPER_ADMIN => 'Super Administrator',
            Role::ADMIN => 'Administrator',
            Role::COMPANY_ADMIN => 'Company Administrator',
            Role::DISPATCHER => 'Dispatcher',
            Role::DRIVER => 'Driver',
            Role::CLIENT => 'Client',
        ];

        foreach ($roles as $name => $displayName) {
            Role::query()->updateOrCreate(
                ['name' => $name],
                ['display_name' => $displayName],
            );
        }

        $settings = [
            'booking_cutoff_hours' => ['2', 'integer'],
            'vat_enabled' => ['1', 'boolean'],
            'vat_rate' => ['14', 'decimal'],
            'currency' => ['EGP', 'string'],
            'business_timezone' => ['Africa/Cairo', 'string'],
            'issuer_name' => ['Transportation Sys', 'string'],
            'issuer_tax_number' => ['300-000-000', 'string'],
            'issuer_address' => ['12 Nile Corniche, Cairo', 'string'],
            'invoice_prefix' => ['INV', 'string'],
            'receipt_prefix' => ['RCT', 'string'],
        ];

        foreach ($settings as $key => [$value, $type]) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type],
            );
        }

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'is_active' => true,
            ],
        );

        $superAdmin->roles()->syncWithoutDetaching([
            Role::query()->where('name', Role::SUPER_ADMIN)->value('id'),
        ]);

        $company = Company::query()->updateOrCreate(
            ['name' => 'Acme Logistics'],
            [
                'legal_name' => 'Acme Logistics LLC',
                'tax_number' => '200-111-222',
                'address' => '8 Industrial Road, Cairo',
                'contact' => 'ops@acme.example',
                'seat_limit' => 25,
                'partial_payment_allowed' => true,
                'is_active' => true,
            ],
        );

        $manager = User::query()->firstOrCreate(
            ['email' => 'manager@acme.example'],
            [
                'company_id' => $company->id,
                'name' => 'Acme Manager',
                'password' => 'password',
                'is_active' => true,
            ],
        );

        $manager->roles()->syncWithoutDetaching([
            Role::query()->where('name', Role::COMPANY_ADMIN)->value('id'),
        ]);

        $trips = [
            [
                'route_name' => 'Lagos to Abuja',
                'origin' => 'Lagos',
                'destination' => 'Abuja',
                'departure_datetime' => now()->addDays(3)->setTime(8, 0),
                'total_seats' => 40,
                'standard_price' => 4500,
                'per_seat_cost' => 3200,
            ],
            [
                'route_name' => 'Lagos to Ibadan',
                'origin' => 'Lagos',
                'destination' => 'Ibadan',
                'departure_datetime' => now()->addDays(5)->setTime(14, 30),
                'total_seats' => 24,
                'standard_price' => 2200,
                'per_seat_cost' => 1500,
            ],
        ];

        foreach ($trips as $data) {
            $trip = Trip::query()->updateOrCreate(
                ['route_name' => $data['route_name']],
                [
                    ...$data,
                    'currency' => 'EGP',
                    'cutoff_hours' => null,
                    'status' => Trip::STATUS_SCHEDULED,
                    'created_by' => $superAdmin->id,
                ],
            );

            $trip->ensureSeats();
        }

        TripTemplate::query()->updateOrCreate(
            ['route_name' => 'Cairo to Alexandria'],
            [
                'origin' => 'Cairo',
                'destination' => 'Alexandria',
                'departure_time' => '07:30',
                'weekdays' => '0,1,2,3,4,5,6',
                'total_seats' => 32,
                'standard_price' => 1800,
                'per_seat_cost' => 1200,
                'is_active' => true,
                'created_by' => $superAdmin->id,
            ],
        );
    }
}
