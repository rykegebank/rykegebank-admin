<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Console\Command;

class CreateNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:notifications {--email=} {--count=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if($email = $this->option('email')){
            $user = User::query()
                ->where('email', $email)
                ->first();

            if($user){
                for ($i=1; $i <= $this->option('count'); $i++){
                    $userNotification              = new UserNotification();
                    $userNotification->title       = 'Loan installment due';
                    $userNotification->user_id     = $user->id;
                    $userNotification->remark      = 'LOAN_INSTALLMENT_DUE';
                    $userNotification->click_value = 1;
                    $userNotification->save();
                }

            }else{
                $this->info('This email '. $email. ' not found in the database');
                return Command::FAILURE;
            }
        }

        $this->info('Successfully created '. $this->option('count') . ' notifications for '. $email);

        return Command::SUCCESS;
    }
}
