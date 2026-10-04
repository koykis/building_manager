<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'app:admin {email}';

    protected $description = 'Create or reset the administrator with an interactive password';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email');

            return 1;
        }$password = $this->secret('Password (at least 12 characters)');
        if (strlen((string) $password) < 12) {
            $this->error('Password too short');

            return 1;
        }User::updateOrCreate(['email' => $email], ['name' => 'Administrator', 'password' => $password, 'role' => 'admin', 'active' => true, 'apartment_id' => null, 'locale' => 'el']);
        $this->info('Administrator ready. No resident accounts activated.');

        return 0;
    }
}
