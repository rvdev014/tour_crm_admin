<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ChangePasswordCommand extends Command
{
    protected $signature = 'app:change-password {email} {new_password}';
    protected $description = 'Change the password of a user';
    
    public function handle(): int
    {
        $email = $this->argument('email');
        $newPassword = $this->argument('new_password');
        
        /** @var User $user */
        $user = User::query()->where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email $email not found.");
            return 1;
        }
        
        $user->password = Hash::make($newPassword);
        $user->save();
        
        $this->info("Password for user with email $email has been changed successfully.");
        return 0;
    }
}
