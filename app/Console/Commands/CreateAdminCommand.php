<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create
        {email : The administrator email address}
        {--name= : Display name (defaults from the email)}
        {--password= : Password (omit to be prompted securely)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a super administrator account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with this email address already exists.');

            return self::FAILURE;
        }

        $password = $this->option('password');

        if ($password === null) {
            $password = $this->secret('Password');
        }

        if (! is_string($password) || strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $name = $this->option('name')
            ?: Str::headline(str_replace('.', ' ', Str::before($email, '@')));

        $role = Role::query()->firstOrCreate(
            ['name' => Role::SUPER_ADMIN],
            ['display_name' => 'Super Administrator'],
        );

        $admin = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $admin->roles()->attach($role);

        $this->info("Super administrator {$email} created.");

        return self::SUCCESS;
    }
}
