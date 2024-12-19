<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;

class CreateUserTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:transactions {--email=} {--count=100}';

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
        if($email = $this->option('email')) {

            $user = $user   = User::query()
                ->where('email', $email)
                ->first();

            if($user){
                for($i = 1; $i <= $this->option('count'); $i ++){
                    $user   = User::query()
                        ->where('email', $email)
                        ->first();

                    $amount = 100;
                    $trx    = getTrx();

                    $transaction = new Transaction();


                    $user->balance += $amount;
                    $transaction->trx_type = '+';
                    $transaction->remark   = 'balance_add';
                    $notify[]              = ['success', gs('cur_sym') . $amount . ' added successfully'];

                    $user->save();

                    $transaction->user_id      = $user->id;
                    $transaction->amount       = $amount;
                    $transaction->post_balance = $user->balance;
                    $transaction->charge       = 0;
                    $transaction->trx          = $trx;
                    $transaction->details      = 'balance_add';
                    $transaction->save();
                }
            }else{
                $this->info('This '. $email. ' not found in the database');
            }

            $this->info('Created' . $this->option('count'). ' transactions record for '. $email . ' successfully');
        }

        return Command::SUCCESS;
    }
}
