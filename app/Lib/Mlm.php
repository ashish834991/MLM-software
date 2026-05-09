<?php

namespace App\Lib;

use App\Models\BvLog;
use App\Models\Transaction;
use App\Models\User;

class Mlm
{
    /**
     * User who subscribe a plan
     *
     * @var object
     */
    public $user;

    /**
     * Plan which subscribed by the user
     *
     * @var object
     */
    public $plan;

    /**
     * General setting
     *
     * @var object
     */
    public $setting;

    /**
     * Transaction number of whole process
     *
     * @var string
     */
    public $trx;

    /**
     * Initialize some global properties
     *
     * @param object $user
     * @param object $plan
     * @param string $trx
     * @return void
     */
    public function __construct($user = null, $plan = null, $trx = null)
    {
        $this->user = $user;
        $this->plan = $plan;
        $this->trx = $trx;
        $this->setting = gs();
    }

    /**
     * Get the positioner user object
     *
     * @param object $positioner
     * @param int $position
     * @return object;
     */
    public static function getPositioner($positioner, $position)
    {
        $getPositioner = $positioner;
        while (0 == 0) {
            $getUnder = User::where('pos_id', $positioner->id)->where('position', $position)->first(['id', 'pos_id', 'position', 'username']);
            if ($getUnder) {
                $positioner = $getUnder;
                $getPositioner = $getUnder;
            } else {
                break;
            }
        }
        return $getPositioner;
    }

    /**
     * Give BV to upper positioners
     *
     * @return void
     */
    public function updateBv()
    {
        $user = $this->user;
        $bv = $this->plan->bv;
        while (0 == 0) {
            $upper = User::where('id', $user->pos_id)->first();
            if (!$upper) {
                break;
            }
            if ($upper->plan_id == 0) {
                $user = $upper;
                continue;
            }

            $bvlog = new BvLog();
            $bvlog->user_id = $upper->id;
            $bvlog->trx_type = '+';
            $extra = $upper->userExtra;
            if ($user->position == 1) {
                $extra->bv_left += $bv;
                $bvlog->position = '1';
            } else {
                $extra->bv_right += $bv;
                $bvlog->position = '2';
            }
            $extra->save();
            $bvlog->amount = $bv;
            $bvlog->details = 'BV from ' . auth()->user()->username;
            $bvlog->save();

            $user = $upper;
        }
    }

    /**
     * Give referral commission to immediate referrer
     *
     * @return void
     */
    public function referralCommission()
    {
        $user = $this->user;
        $referrer = $user->referrer;
        $plan = @$referrer->plan;
        if ($plan) {
            $amount = $plan->ref_com;
            $referrer->balance += $amount;
            $referrer->total_ref_com += $amount;
            $referrer->save();

            $trx = $this->trx;
            $transaction = new Transaction();
            $transaction->user_id = $referrer->id;
            $transaction->amount = $amount;
            $transaction->post_balance = $referrer->balance;
            $transaction->charge = 0;
            $transaction->trx_type = '+';
            $transaction->details = 'Direct referral commission from ' . $user->username;
            $transaction->trx =  $trx;
            $transaction->remark = 'referral_commission';
            $transaction->save();

            notify($referrer, 'REFERRAL_COMMISSION', [
                'amount' => showAmount($amount),
                'username' => $user->username,
                'post_balance' => $referrer->balance,
                'trx' => $trx
            ]);
        }
    }

    /**
     * Give tree commission to upper positioner
     *
     * @return void
     */
    public function treeCommission()
    {
        $user = $this->user;
        $amount = $this->plan->tree_com;
        while (0 == 0) {
            $upper = User::where('id', $user->pos_id)->first();
            if (!$upper) {
                break;
            }
            if ($upper->plan_id == 0) {
                $user = $upper;
                continue;
            }
            $upper->balance += $amount;
            $upper->total_binary_com += $amount;
            $upper->save();

            $trx = $this->trx;
            $transaction = new Transaction();
            $transaction->user_id = $upper->id;
            $transaction->amount = $amount;
            $transaction->post_balance = $upper->balance;
            $transaction->charge = 0;
            $transaction->trx_type = '+';
            $transaction->details = 'Tree commission';
            $transaction->remark = 'binary_commission';
            $transaction->trx =  $trx;
            $transaction->save();

            notify($upper, 'TREE_COMMISSION', [
                'amount' => showAmount($amount),
                'post_balance' => $upper->balance,
            ]);

            $user = $upper;
        }
    }

    /**
     * Update paid count users to upper positioner when user subscribe a plan
     *
     * @return void
     */
    public function updatePaidCount()
    {
        $user = $this->user;
        while (0 == 0) {
            $upper = User::where('id', $user->pos_id)->first();
            if (!$upper) {
                break;
            }

            $extra = $upper->userExtra;
            if ($user->position == 1) {
                $extra->free_left -= 1;
                $extra->paid_left += 1;
            } else {
                $extra->free_right -= 1;
                $extra->paid_right += 1;
            }
            $extra->save();
            $user = $upper;
        }
    }

    /**
     * Update free count users to upper positioner when user register to this system
     *
     * @param object $user
     * @return void
     */
    public static function updateFreeCount($user)
    {
        while (0 == 0) {
            $upper = User::where('id', $user->pos_id)->first();
            if (!$upper) {
                break;
            }

            $extra = $upper->userExtra;
            if ($user->position == 1) {
                $extra->free_left += 1;
            } else {
                $extra->free_right += 1;
            }
            $extra->save();

            $user = $upper;
        }
    }

    /**
     * Check the time for giving the matching bonus
     *
     * @return boolean
     */
    public function checkTime()
    {
        $general = $this->setting;
        $times = [
            'H' => 'daily',
            'D' => 'weekly',
            'd' => 'monthly',
        ];
        foreach ($times as $timeKey => $time) {
            if ($general->matching_bonus_time == $time) {
                $day = Date($timeKey);
                if (strtolower($day) != $general->matching_when) {
                    return false;
                }
            }
        }
        if (now()->toDateString() == now()->parse($general->last_paid)->toDateString()) {
            return false;
        }
        return true;
    }

    /**
     * Update the user BV after getting bonus
     *
     * @param object $general
     * @param object $uex
     * @param integer $paidBv
     * @param float $weak
     * @param float $bonus
     * @return void
     */
    public function updateUserBv($uex, $paidBv, $weak, $bonus)
    {
        $general = $this->setting;
        $user = $uex->user;
        //cut paid bv from both
        if ($general->cary_flash == 0) {
            $uex->bv_left -= $paidBv;
            $uex->bv_right -= $paidBv;
            $lostl = 0;
            $lostr = 0;
        }

        //cut only weaker bv from both
        if ($general->cary_flash == 1) {
            $uex->bv_left -= $weak;
            $uex->bv_right -= $weak;
            $lostl = $weak - $paidBv;
            $lostr = $weak - $paidBv;
        }

        //cut all bv from both
        if ($general->cary_flash == 2) {
            $uex->bv_left = 0;
            $uex->bv_right = 0;
            $lostl = $uex->bv_left - $paidBv;
            $lostr = $uex->bv_right - $paidBv;
        }
        $uex->save();
        $bvLog = null;
        if ($paidBv != 0) {
            $bvLog[] = [
                'user_id' => $user->id,
                'position' => 1,
                'amount' => $paidBv,
                'trx_type' => '-',
                'details' => 'Paid ' . showAmount($bonus) . ' ' . __($general->cur_text) . ' For ' . showAmount($paidBv) . ' BV.',
            ];
            $bvLog[] = [
                'user_id' => $user->id,
                'position' => 2,
                'amount' => $paidBv,
                'trx_type' => '-',
                'details' => 'Paid ' . showAmount($bonus) . ' ' . __($general->cur_text) . ' For ' . showAmount($paidBv) . ' BV.',
            ];
        }
        if ($lostl != 0) {
            $bvLog[] = [
                'user_id' => $user->id,
                'position' => 1,
                'amount' => $lostl,
                'trx_type' => '-',
                'details' => 'Flush ' . showAmount($lostl) . ' BV after Paid ' . showAmount($bonus) . ' ' . __($general->cur_text) . ' For ' . showAmount($paidBv) . ' BV.',
            ];
        }
        if ($lostr != 0) {
            $bvLog[] = [
                'user_id' => $user->id,
                'position' => 2,
                'amount' => $lostr,
                'trx_type' => '-',
                'details' => 'Flush ' . showAmount($lostr) . ' BV after Paid ' . showAmount($bonus) . ' ' . __($general->cur_text) . ' For ' . showAmount($paidBv) . ' BV.',
            ];
        }

        if ($bvLog) {
            BvLog::insert($bvLog);
        }
    }


    /**
     * Get the under position user
     *
     * @param integer $id
     * @param integer $position
     * @return object
     */

    protected function getPositionUser($id, $position)
    {
        return User::where('pos_id', $id)->where('position', $position)->with('referrer', 'plan', 'userExtra')->first();
    }


    /**
     * Get the under position user
     *
     * @param object $user
     * @return array
     */
    public function showTreePage($user, $isAdmin = false)
    {
        if (!$isAdmin) {
            if ($user->username != @auth()->user()->username) {
                $this->checkMyTree($user);
            }
        }
        $hands = array_fill_keys($this->getHands(), null);
        $hands['a'] = $user;
        $hands['b'] = $this->getPositionUser($user->id, 1);
        if ($hands['b']) {
            $hands['d'] = $this->getPositionUser($hands['b']->id, 1);
            $hands['e'] = $this->getPositionUser($hands['b']->id, 2);
        }
        if ($hands['d']) {
            $hands['h'] = $this->getPositionUser($hands['d']->id, 1);
            $hands['i'] = $this->getPositionUser($hands['d']->id, 2);
        }
        if ($hands['e']) {
            $hands['j'] = $this->getPositionUser($hands['e']->id, 1);
            $hands['k'] = $this->getPositionUser($hands['e']->id, 2);
        }
        $hands['c'] = $this->getPositionUser($user->id, 2);
        if ($hands['c']) {
            $hands['f'] = $this->getPositionUser($hands['c']->id, 1);
            $hands['g'] = $this->getPositionUser($hands['c']->id, 2);
        }
        if ($hands['f']) {
            $hands['l'] = $this->getPositionUser($hands['f']->id, 1);
            $hands['m'] = $this->getPositionUser($hands['f']->id, 2);
        }
        if ($hands['g']) {
            $hands['n'] = $this->getPositionUser($hands['g']->id, 1);
            $hands['o'] = $this->getPositionUser($hands['g']->id, 2);
        }
        return $hands;
    }

    /**
     * Get single user in tree
     *
     * @param object $user
     * @return string
     */
    public function showSingleUserinTree($user, $isAdmin = false)
    {
        $html = '';
        if ($user) {
            if ($user->plan_id == 0) {
                $userType = "free-user";
                $stShow = "Free";
                $planName = '';
            } else {
                $userType = "paid-user";
                $stShow = "Paid";
                $planName = @$user->plan->name;
            }

            $img = getImage(getFilePath('userProfile') . '/' . $user->image, false, true);
            $refby = @$user->referrer->fullname ?? '';

            if ($isAdmin) {
                $hisTree = route('admin.users.binary.tree', $user->username);
            } else {
                $hisTree = route('user.binary.tree', $user->username);
            }

            $extraData = " data-name=\"$user->fullname\"";
            $extraData .= " data-treeurl=\"$hisTree\"";
            $extraData .= " data-status=\"$stShow\"";
            $extraData .= " data-plan=\"$planName\"";
            $extraData .= " data-image=\"$img\"";
            $extraData .= " data-refby=\"$refby\"";
            $extraData .= " data-lpaid=\"" . @$user->userExtra->paid_left . "\"";
            $extraData .= " data-rpaid=\"" . @$user->userExtra->paid_right . "\"";
            $extraData .= " data-lfree=\"" . @$user->userExtra->free_left . "\"";
            $extraData .= " data-rfree=\"" . @$user->userExtra->free_right . "\"";
            $extraData .= " data-lbv=\"" . showAmount(@$user->userExtra->bv_left) . "\"";
            $extraData .= " data-rbv=\"" . showAmount(@$user->userExtra->bv_right) . "\"";

            $html .= "<div class=\"user showDetails\" type=\"button\" $extraData>";
            $html .= "<img src=\"$img\" alt=\"*\"  class=\"$userType\">";
            $html .= "<p class=\"user-name\">$user->username</p>";
        } else {
            $img = getImage('assets/images/nouser.png');

            $html .= "<div class=\"user\" type=\"button\">";
            $html .= "<img src=\"$img\" alt=\"*\"  class=\"no-user\">";
            $html .= "<p class=\"user-name\">No User</p>";
        }

        $html .= " </div>";
        $html .= " <span class=\"line\"></span>";

        return $html;
    }


    /**
     * Get the mlm hands for tree
     *
     * @return array
     */
    public function getHands()
    {
        return ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
    }

    /**
     * Check the user is in my tree or not
     *
     * @param object $user
     * @return bool
     */
    protected function checkMyTree($user)
    {
        $topUser = User::where('id', $user->pos_id)->first(['id', 'pos_id']);
        if (!$topUser) {
            abort(401);
        }
        if ($topUser->id == auth()->user()->id) {
            return true;
        }
        $this->checkMyTree($topUser);
    }

    /**
     * Plan subscribe logic
     *
     * @return void
     */
    public function purchasePlan()
    {
        $user = $this->user;
        $plan = $this->plan;
        $trx = $this->trx;

        $oldPlan = $user->plan_id;
        $user->plan_id = $plan->id;
        $user->balance -= $plan->price;
        $user->total_invest += $plan->price;
        $user->save();

        $transaction = new Transaction();
        $transaction->user_id = $user->id;
        $transaction->amount = $plan->price;
        $transaction->trx_type = '-';
        $transaction->details = 'Purchased ' . $plan->name;
        $transaction->remark = 'purchased_plan';
        $transaction->trx = $trx;
        $transaction->post_balance = $user->balance;
        $transaction->save();

        notify($user, 'PLAN_PURCHASED', [
            'plan_name' => $plan->name,
            'price' => showAmount($plan->price),
            'trx' => $transaction->trx,
            'post_balance' => showAmount($user->balance)
        ]);

        if ($oldPlan == 0) {
            $this->updatePaidCount($user->id);
        }

        if ($plan->bv) {
            $this->updateBV();
        }

        if ($plan->tree_com > 0) {
            $this->treeCommission();
        }

        $this->referralCommission();
    }
}
