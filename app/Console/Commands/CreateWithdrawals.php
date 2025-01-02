<?php

namespace App\Console\Commands;

use App\Constants\Status;
use App\Lib\OTPManager;
use App\Models\OtpVerification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Withdrawal;
use App\Models\WithdrawMethod;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateWithdrawals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:withdrawals {--email=} {--count=100}';

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

            if(!$user) {
                $this->info('This email '. $email . ' not found in database.');
                return Command::FAILURE;
            }

            $additional_data = array(
                'amount' => 20,
                'after_verified' => 'api.withdraw.store'
            );

            for ($i = 1; $i <= $this->option('count'); $i++){
                $user = User::query()
                    ->where('email', $email)
                    ->first();
                $verification = New OtpVerification();
                $verification->verifiable_type = 'App\Models\WithdrawMethod';
                $verification->verifiable_id = 1;
                $verification->user_id = $user->id;
                $verification->otp = verificationCode(6);
                $verification->send_via = 'sms';
                $verification->notify_template = json_encode($additional_data, JSON_FORCE_OBJECT);
                $verification->additional_data = $additional_data;
                $verification->send_at = now();
                $verification->expired_at = Carbon::parse(now())->addMinutes(2);
                $verification->used_at = Carbon::parse(now())->addMinutes(1);
                $verification->save();

                $method = $verification->verifiable;
                $amount = $verification->additional_data->amount;

                if ($user->balance < $amount) {
                    $this->info('Sorry! You don\'t have sufficient balance');
                    return Command::FAILURE;
                }

                $charge      = $method->fixed_charge + ($amount * $method->percent_charge / 100);
                $afterCharge = $amount - $charge;
                $finalAmount = $afterCharge * $method->rate;

                $withdraw               = new Withdrawal();
                $withdraw->method_id    = $method->id;
                $withdraw->user_id      = $user->id;
                $withdraw->amount       = $amount;
                $withdraw->currency     = $method->currency;
                $withdraw->rate         = $method->rate;
                $withdraw->charge       = $charge;
                $withdraw->final_amount = $finalAmount;
                $withdraw->after_charge = $afterCharge;
                $withdraw->trx          = getTrx();
                $withdraw->status               = Status::PAYMENT_SUCCESS;
                $withdraw->withdraw_information = null;
                $withdraw->save();

                $user->balance -= $amount;
                $user->save();

                $transaction               = new Transaction();
                $transaction->user_id      = $withdraw->user_id;
                $transaction->amount       = $withdraw->amount;
                $transaction->post_balance = $user->balance;
                $transaction->charge       = $withdraw->charge;
                $transaction->trx_type     = '-';
                $transaction->details      = showAmount($withdraw->final_amount) . ' ' . $withdraw->currency . ' Withdraw Via ' . $withdraw->method->name;
                $transaction->trx          = $withdraw->trx;
                $transaction->remark       = 'withdraw';
                $transaction->save();

                $userNotification              = new UserNotification();
                $userNotification->title       = 'Withdraw - Approved';
                $userNotification->user_id     = $withdraw->user_id;
                $userNotification->remark      = 'WITHDRAW_APPROVE';
                $userNotification->click_value = $withdraw->id;
                $userNotification->save();
            }
        }

        $this->info('Created Withdrawal Records Successfully');

        return Command::SUCCESS;
    }
}
