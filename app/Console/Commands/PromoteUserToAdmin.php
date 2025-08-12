<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserToAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:promote {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promove um utilizador existente ao status de administrador';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Utilizador com o email '{$email}' não encontrado.");
            return;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("Sucesso! O utilizador {$user->name} ({$user->email}) é agora um administrador.");
    }
}
