<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'user:create
                            {name : Full name}
                            {email : Email address (must be unique)}
                            {--password= : Password (min 8 characters); omit to type it interactively}
                            {--generate-password : Create a random password and print it}
                            {--super-admin : Grant super admin}
                            {--project=* : Attach to projects as id:role (role: admin or user); id alone defaults role to user}';

    protected $description = 'Create a new local (email/password) user';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $name = trim($this->argument('name'));

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email \"{$email}\" already exists.");

            return Command::FAILURE;
        }

        $projectsToAttach = $this->parseProjectAttachments();
        if ($projectsToAttach === null) {
            return Command::FAILURE;
        }

        $password = $this->resolvePassword();
        if ($password === null) {
            return Command::FAILURE;
        }

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return Command::FAILURE;
        }

        $superAdmin = (bool) $this->option('super-admin');

        $user = DB::transaction(function () use ($name, $email, $password, $superAdmin, $projectsToAttach) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'super_admin' => $superAdmin,
            ]);

            if (! $user->isSuperAdmin() && $projectsToAttach !== []) {
                $user->projects()->attach($projectsToAttach);
            }

            return $user;
        });

        $this->info("Created user {$user->email} (ID {$user->id}).");
        if ($this->option('generate-password')) {
            $this->warn('Store this password securely; it cannot be shown again:');
            $this->line($password);
        }
        if ($user->requiresInAppTwoFactor() && ! $user->hasConfirmedTwoFactor()) {
            $this->comment('They must enroll two-factor authentication on first sign-in.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array{role: string}>|null
     */
    private function parseProjectAttachments(): ?array
    {
        $specs = $this->option('project');
        if ($specs === []) {
            return [];
        }

        $attach = [];
        foreach ($specs as $spec) {
            $spec = trim($spec);
            if ($spec === '') {
                continue;
            }

            if (str_contains($spec, ':')) {
                [$id, $role] = explode(':', $spec, 2);
                $id = trim($id);
                $role = trim($role);
            } else {
                $id = $spec;
                $role = User::ROLE_USER;
            }

            if (! ctype_digit((string) $id)) {
                $this->error("Invalid project spec \"{$spec}\" (project id must be numeric).");

                return null;
            }

            if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_USER], true)) {
                $this->error("Invalid role \"{$role}\" for project {$id} (use admin or user).");

                return null;
            }

            if (! Project::whereKey((int) $id)->exists()) {
                $this->error("Project ID {$id} does not exist.");

                return null;
            }

            $attach[(int) $id] = ['role' => $role];
        }

        return $attach;
    }

    private function resolvePassword(): ?string
    {
        if ($this->option('generate-password')) {
            return Str::password(16);
        }

        $password = $this->option('password');
        if (is_string($password) && $password !== '') {
            return $password;
        }

        if (! $this->input->isInteractive()) {
            $this->error('In non-interactive mode, pass --password=... or --generate-password.');

            return null;
        }

        $password = $this->secret('Password (min 8 characters)');
        $confirm = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return null;
        }

        if ($password === '') {
            $this->error('Password cannot be empty.');

            return null;
        }

        return $password;
    }
}
