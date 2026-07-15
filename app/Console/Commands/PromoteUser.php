<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PromoteUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:promote
        {email : The email address of an already-registered user}
        {--role=owner : Target role (user, editor, admin, owner)}
        {--force : Apply the change without an interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change a registered user\'s role from the server CLI (used to bootstrap the first owner).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $roleValue = (string) $this->option('role');
        $role = UserRole::tryFrom($roleValue);

        if ($role === null) {
            $allowed = implode(', ', array_map(static fn (UserRole $r): string => $r->value, UserRole::cases()));
            $this->error("Invalid role \"{$roleValue}\". Allowed roles: {$allowed}.");

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email \"{$email}\". Register the account first, then promote it.");

            return self::FAILURE;
        }

        if ($user->role === $role) {
            $this->info("{$user->email} already has the role \"{$role->value}\". Nothing to do.");

            return self::SUCCESS;
        }

        $this->line("About to change {$user->email} from \"{$user->role->value}\" to \"{$role->value}\".");

        if (! $this->option('force') && ! $this->confirm('Do you want to continue?')) {
            $this->warn('Aborted. No changes were made.');

            return self::FAILURE;
        }

        $oldRole = $user->role;
        $user->role = $role;
        $user->save();

        Log::info('User role updated via CLI', [
            'actor' => 'console:user:promote',
            'target_id' => $user->id,
            'target_email' => $user->email,
            'old_role' => $oldRole->value,
            'new_role' => $role->value,
        ]);

        $this->info("{$user->email} is now \"{$role->value}\".");

        return self::SUCCESS;
    }
}
