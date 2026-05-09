<?php

namespace App\Http\Controllers;

use App\Lib\Mlm;
use App\Models\Transaction;
use App\Models\UserExtra;

class CronController extends Controller
{
    public function cron()
    {
        $mlm = new Mlm();
        $general = gs();
        $general->last_cron = now()->toDateTimeString();
        $general->save();

        $check = $mlm->checkTime();
        if (!$check) {
            return 0;
        }

        $general->last_paid = now()->toDateString();
        $general->save();

        $eligibleUsers = UserExtra::where('bv_left', '>=', $general->total_bv)->where('bv_right', '>=', $general->total_bv)->cursor();
        foreach ($eligibleUsers as $uex) {
            $user = $uex->user;

            //get BV and bonus
            $weak = $uex->bv_left < $uex->bv_right ? $uex->bv_left : $uex->bv_right;
            $weaker = $weak < $general->max_bv ? $weak : $general->max_bv;
            $pair = intval($weaker / $general->total_bv);
            $bonus = $pair * $general->bv_price;
            if ($bonus <= 0) {
                continue;
            }
            $paidBv = $pair * $general->total_bv;

            $user->balance += $bonus;
            $user->save();

            //create transaction
            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->amount = $bonus;
            $transaction->post_balance = $user->balance;
            $transaction->charge = 0;
            $transaction->trx_type = '+';
            $transaction->remark = 'paid_bv';
            $transaction->details = 'Paid ' . $bonus . ' ' . $general->cur_text . ' For ' . $paidBv . ' BV.';
            $transaction->trx =  getTrx();
            $transaction->save();


            $mlm->updateUserBv($uex, $paidBv, $weak, $bonus);

            notify($user, 'MATCHING_BONUS', [
                'amount' => showAmount($bonus),
                'paid_bv' => $paidBv,
                'post_balance' => showAmount($user->balance)
            ]);
        }
    }
}
