<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;

trait CreatesDomainData
{
    protected function makeRole(string $name): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $name],
            ['display_name' => str_replace('_', ' ', $name)],
        );
    }

    protected function makeAdmin(string $roleName = Role::SUPER_ADMIN): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($this->makeRole($roleName));

        return $user;
    }

    protected function makeCompany(string $name = 'Acme Logistics', int $seatLimit = 20): Company
    {
        return Company::query()->create([
            'name' => $name,
            'contact' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'seat_limit' => $seatLimit,
            'is_active' => true,
        ]);
    }

    protected function makeCompanyUser(Company $company, string $roleName = Role::COMPANY_ADMIN): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $user->roles()->attach($this->makeRole($roleName));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeTrip(array $attributes = []): Trip
    {
        return Trip::query()->create([
            'route_name' => 'Lagos to Abuja',
            'origin' => 'Lagos',
            'destination' => 'Abuja',
            'departure_datetime' => now()->addDays(3),
            'total_seats' => 40,
            'status' => Trip::STATUS_SCHEDULED,
            'created_by' => $attributes['created_by'] ?? $this->makeAdmin()->id,
            ...$attributes,
        ]);
    }
}
