<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateTestTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'token:generate 
                          {--user= : Email of the user to generate token for}
                          {--name= : Name for the token}';

    /**
     * The console command description.
     */
    protected $description = 'Generate a personal access token for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('user') ?: $this->ask('User email:', 'test@example.com');
        $tokenName = $this->option('name') ?: $this->ask('Token name:', 'Test API Token');

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email '$email' not found.");
            return 1;
        }

        try {
            $token = $user->createToken($tokenName);
            
            $this->info("Token generated successfully!");
            $this->line("");
            $this->line("User: {$user->name} ({$user->email})");
            $this->line("Token Name: {$tokenName}");
            $this->line("Token: {$token->accessToken}");
            $this->line("");
            $this->info("Test with: curl -H 'Authorization: Bearer {$token->accessToken}' http://localhost:8000/api/v1/quantum/health");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Failed to generate token: " . $e->getMessage());
            return 1;
        }
    }
}