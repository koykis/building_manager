<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class LocalAdmin extends Command
{
    protected $signature = 'app:local-admin';

    protected $description = 'Create a local-only admin and save generated credentials in ignored .local';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Local environment only.');

            return 1;
        }$path = base_path('../.local/admin-credentials.json');
        if (User::where('role', 'admin')->exists()) {
            $this->info('Administrator already exists; unchanged.');

            return 0;
        }$email = 'admin@building.local';
        $password = bin2hex(random_bytes(18));
        User::create(['name' => 'Administrator', 'email' => $email, 'password' => $password, 'active' => true, 'role' => 'admin', 'locale' => 'el']);
        file_put_contents($path, json_encode(['email' => $email, 'password' => $password], JSON_PRETTY_PRINT));
        chmod($path, 0600);
        $this->info('Local administrator created. Credentials are in .local/admin-credentials.json.');

        return 0;
    }
}
