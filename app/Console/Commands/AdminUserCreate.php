<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AdminUserCreate extends Command
{
    protected $signature = 'admin:user-create {--email=admin@magetsi.co.zw} {--name=Admin}';

    protected $description = 'Create or update an admin user';

    public function handle(): int
    {
        $email = $this->option('email');
        $name = $this->option('name');
        $password = $this->secret('Password (leave blank to generate)');

        if (!$password) {
            $password = \Illuminate\Support\Str::password(16);
            $this->line("Generated password: <info>{$password}</info>");
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password]
        );

        $this->info("Admin user saved:");
        $this->table(['Field', 'Value'], [
            ['Name', $user->name],
            ['Email', $user->email],
            ['Password', $password ? '(set)' : '(existing)'],
        ]);

        return self::SUCCESS;
    }
}
