<?php

namespace App\Console\Commands;

use App\Constants\Status;
use App\Models\Deposit;
use App\Models\GatewayCurrency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Console\Command;

class CreateDeposits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:deposits {--email=} {--count=100}';

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
        $method_code = 103;
        $currency = 'USD';
        $amount = 100;

        if($email = $this->option('email')){
            $user = User::query()
                ->where('email', $email)
                ->first();

            if(!$user){
                $this->info('This email '. $this->option('email') . ' not found in the database' );
                return Command::FAILURE;
            }

            $gate = GatewayCurrency::whereHas('method', function ($gate) {
                $gate->where('status', Status::ENABLE);
            })->where('method_code', $method_code)->where('currency', $currency)->first();

            for ($i = 1; $i <= $this->option('count'); $i++){
                if (!$gate) {
                    $this->info('Invalid gateway');
                    return Command::FAILURE;
                }

                if ($gate->min_amount > $amount || $gate->max_amount < $amount) {
                    $this->info('Please follow deposit limit');
                    return Command::FAILURE;
                }

                $charge    = $gate->fixed_charge + ($amount * $gate->percent_charge / 100);
                $payable   = $amount + $charge;
                $finalAmount = $payable * $gate->rate;

                $data                  = new Deposit();
                $data->user_id         = $user->id;
                $data->method_code     = $gate->method_code;
                $data->method_currency = strtoupper($gate->currency);
                $data->amount          = $amount;
                $data->charge          = $charge;
                $data->rate            = $gate->rate;
                $data->final_amount    = $finalAmount;
                $data->btc_amo         = 0;
                $data->btc_wallet      = "";
                $data->trx             = getTrx();
                $data->save();

                $currentUser = User::find($user->id);
                $currentUser->balance += $amount;
                $currentUser->save();

                $transaction               = new Transaction();
                $transaction->user_id      = $currentUser->id;
                $transaction->amount       = $amount;
                $transaction->post_balance = $currentUser->balance;
                $transaction->charge       = $data->charge;
                $transaction->trx_type     = '+';
                $transaction->details      = 'Deposit Via ' . $data->gatewayCurrency()->name;
                $transaction->trx          = $data->trx;
                $transaction->remark       = 'deposit';
                $transaction->save();

                $userNotification              = new UserNotification();
                $userNotification->title       = 'Deposit Completed Successfully';
                $userNotification->user_id     = $currentUser->id;
                $userNotification->remark      = 'DEPOSIT_COMPLETE';
                $userNotification->click_value = $data->id;
                $userNotification->save();
            }
        }

        $this->info('Successfully created deposit data');

        return Command::SUCCESS;
    }
}
