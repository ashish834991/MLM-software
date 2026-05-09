<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Lib\GoogleAuthenticator;
use App\Lib\Mlm;
use App\Models\BvLog;
use App\Models\Deposit;
use App\Models\Form;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserExtra;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function home()
    {
        $pageTitle = 'Dashboard';
        $user = auth()->user();
        $totalDeposit = Deposit::where('user_id', $user->id)->where('status', Status::PAYMENT_SUCCESS)->sum('amount');
        $submittedDeposit = Deposit::where('status', '!=', Status::PAYMENT_INITIATE)->where('user_id', $user->id)->sum('amount');
        $pendingDeposit = Deposit::pending()->where('user_id', $user->id)->sum('amount');
        $rejectedDeposit = Deposit::rejected()->where('user_id', $user->id)->sum('amount');

        $totalWithdraw = Withdrawal::where('user_id', $user->id)->where('status', Status::PAYMENT_SUCCESS)->sum('amount');
        $submittedWithdraw = Withdrawal::where('status', '!=', Status::PAYMENT_INITIATE)->where('user_id', $user->id)->sum('amount');
        $pendingWithdraw = Withdrawal::pending()->where('user_id', $user->id)->count();
        $rejectWithdraw = Withdrawal::rejected()->where('user_id', $user->id)->sum('amount');

        $totalRef = User::where('ref_by', $user->id)->count();
        $totalBvCut = BvLog::where('user_id', $user->id)->where('trx_type', '-')->sum('amount');
        $totalLeft = @$user->userExtra->free_left + @$user->userExtra->paid_left;
        $totalRight = @$user->userExtra->free_right + @$user->userExtra->paid_right;
        $totalBv = @$user->userExtra->bv_left + @$user->userExtra->bv_right;
        $logs = UserExtra::where('user_id', $user->id)->firstOrFail();
        return view($this->activeTemplate . 'user.dashboard', compact('pageTitle', 'user', 'totalDeposit', 'submittedDeposit', 'pendingDeposit', 'rejectedDeposit', 'totalWithdraw', 'submittedWithdraw', 'pendingWithdraw', 'rejectWithdraw', 'totalRef', 'totalBvCut', 'totalLeft', 'totalRight', 'totalBv', 'logs'));
    }

    public function depositHistory(Request $request)
    {
        $pageTitle = 'Deposit History';
        $deposits = auth()->user()->deposits()->searchable(['trx'])->with(['gateway'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view($this->activeTemplate . 'user.deposit_history', compact('pageTitle', 'deposits'));
    }

    public function show2faForm()
    {
        $general = gs();
        $ga = new GoogleAuthenticator();
        $user = auth()->user();
        $secret = $ga->createSecret();
        $qrCodeUrl = $ga->getQRCodeGoogleUrl($user->username . '@' . $general->site_name, $secret);
        $pageTitle = '2FA Setting';
        return view($this->activeTemplate . 'user.twofactor', compact('pageTitle', 'secret', 'qrCodeUrl'));
    }

    public function create2fa(Request $request)
    {
        $user = auth()->user();
        $this->validate($request, [
            'key' => 'required',
            'code' => 'required',
        ]);
        $response = verifyG2fa($user, $request->code, $request->key);
        if ($response) {
            $user->tsc = $request->key;
            $user->ts = 1;
            $user->save();
            $notify[] = ['success', 'Google authenticator activated successfully'];
            return back()->withNotify($notify);
        } else {
            $notify[] = ['error', 'Wrong verification code'];
            return back()->withNotify($notify);
        }
    }

    public function disable2fa(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
        ]);

        $user = auth()->user();
        $response = verifyG2fa($user, $request->code);
        if ($response) {
            $user->tsc = null;
            $user->ts = 0;
            $user->save();
            $notify[] = ['success', 'Two factor authenticator deactivated successfully'];
        } else {
            $notify[] = ['error', 'Wrong verification code'];
        }
        return back()->withNotify($notify);
    }

    public function transactions(Request $request)
    {
        $pageTitle = 'Transactions';
        $remarks = Transaction::distinct('remark')->orderBy('remark')->get('remark');

        $transactions = Transaction::where('user_id', auth()->id())->searchable(['trx'])->filter(['trx_type', 'remark'])->orderBy('id', 'desc')->paginate(getPaginate());

        return view($this->activeTemplate . 'user.transactions', compact('pageTitle', 'transactions', 'remarks'));
    }

    public function kycForm()
    {
        if (auth()->user()->kv == 2) {
            $notify[] = ['error', 'Your KYC is under review'];
            return to_route('user.home')->withNotify($notify);
        }
        if (auth()->user()->kv == 1) {
            $notify[] = ['error', 'You are already KYC verified'];
            return to_route('user.home')->withNotify($notify);
        }
        $pageTitle = 'KYC Form';
        $form = Form::where('act', 'kyc')->first();
        return view($this->activeTemplate . 'user.kyc.form', compact('pageTitle', 'form'));
    }

    public function kycData()
    {
        $user = auth()->user();
        $pageTitle = 'KYC Data';
        return view($this->activeTemplate . 'user.kyc.info', compact('pageTitle', 'user'));
    }

    public function kycSubmit(Request $request)
    {
        $form = Form::where('act', 'kyc')->first();
        $formData = $form->form_data;
        $formProcessor = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $userData = $formProcessor->processFormData($request, $formData);
        $user = auth()->user();
        $user->kyc_data = $userData;
        $user->kv = 2;
        $user->save();

        $notify[] = ['success', 'KYC data submitted successfully'];
        return to_route('user.home')->withNotify($notify);
    }

    public function attachmentDownload($fileHash)
    {
        $filePath = decrypt($fileHash);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $general = gs();
        $title = slug($general->site_name) . '- attachments.' . $extension;
        $mimetype = mime_content_type($filePath);
        header('Content-Disposition: attachment; filename="' . $title);
        header("Content-Type: " . $mimetype);
        return readfile($filePath);
    }

    public function userData()
    {
        $user = auth()->user();
        if ($user->profile_complete == 1) {
            return to_route('user.home');
        }
        $pageTitle = 'User Data';
        return view($this->activeTemplate . 'user.user_data', compact('pageTitle', 'user'));
    }

    public function userDataSubmit(Request $request)
    {
        $user = auth()->user();
        if ($user->profile_complete == 1) {
            return to_route('user.home');
        }
        $request->validate([
            'firstname' => 'required',
            'lastname' => 'required',
        ]);
        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;
        $user->address = [
            'country' => @$user->address->country,
            'address' => $request->address,
            'state' => $request->state,
            'zip' => $request->zip,
            'city' => $request->city,
        ];
        $user->profile_complete = 1;
        $user->save();

        $notify[] = ['success', 'Registration process completed successfully'];
        return to_route('user.home')->withNotify($notify);
    }

    public function bvLog(Request $request)
    {
        $user = auth()->user();
        if ($request->type) {
            if ($request->type == 'leftBV') {
                $pageTitle = "Left BV";
                $logs = BvLog::where('user_id', $user->id)->where('position', 1)->where('trx_type', '+')->orderBy('id', 'desc')->paginate(getPaginate());
            } elseif ($request->type == 'rightBV') {
                $pageTitle = "Right BV";
                $logs = BvLog::where('user_id', $user->id)->where('position', 2)->where('trx_type', '+')->orderBy('id', 'desc')->paginate(getPaginate());
            } elseif ($request->type == 'cutBV') {
                $pageTitle = "Cut BV";
                $logs = BvLog::where('user_id', $user->id)->where('trx_type', '-')->orderBy('id', 'desc')->paginate(getPaginate());
            } else {
                $pageTitle = "All Paid BV";
                $logs = BvLog::where('user_id', $user->id)->where('trx_type', '+')->orderBy('id', 'desc')->paginate(getPaginate());
            }
        } else {
            $pageTitle = "BV LOG";
            $logs = BvLog::where('user_id', $user->id)->orderBy('id', 'desc')->paginate(getPaginate());
        }
        return view($this->activeTemplate . 'user.bv_log', compact('pageTitle', 'logs'));
    }

    public function myReferralLog()
    {
        $pageTitle = "My Referral";
        $logs = User::where('ref_by', auth()->id())->orderBy('id', 'desc')->paginate(getPaginate());
        return view($this->activeTemplate . 'user.my_referral', compact('pageTitle', 'logs'));
    }

    public function binaryTree($user = null)
    {
        $pageTitle = 'Binary Tree';
        $user = User::where('username', $user)->first();
        if (!$user) {
            $user = auth()->user();
        }
        $mlm = new Mlm();
        $tree = $mlm->showTreePage($user);
        return view($this->activeTemplate . 'user.tree', compact('pageTitle', 'mlm', 'tree'));
    }

    public function balanceTransfer()
    {
        $general = gs();
        if ($general->balance_transfer != Status::ENABLE) {
            abort(404);
        }
        $pageTitle = 'Transfer Balance';
        return view($this->activeTemplate . 'user.balance_transfer', compact('pageTitle'));
    }

    public function transferConfirm(Request $request)
    {
        $general = gs();
        if ($general->balance_transfer != Status::ENABLE) {
            abort(404);
        }

        $request->validate([
            'username' => 'required|exists:users,username',
            'amount' => 'required|numeric|gt:0'
        ]);

        $user = auth()->user();
        if ($user->ts) {
            $response = verifyG2fa($user, $request->authenticator_code);
            if (!$response) {
                $notify[] = ['error', 'Wrong verification code'];
                return back()->withNotify($notify);
            }
        }

        $toUser = User::where('username', $request->username)->first();

        if ($user->id == $toUser->id) {
            $notify[] = ['error', 'You can\'t send money to your own account'];
            return back()->withNotify($notify);
        }

        $general    = gs();
        $amount     = $request->amount;
        $fixed      = $general->balance_transfer_fixed_charge;
        $percent    = $general->balance_transfer_per_charge;
        $charge     = ($amount * $percent / 100) + $fixed;
        $withCharge = $amount + $charge;

        if ($user->balance < $withCharge) {
            $notify[] = ['error', 'You have no sufficient balance'];
            return back()->withNotify($notify);
        }

        if ($general->balance_transfer_min > $amount) {
            $notify[] = ['error', 'Please follow minimum balance transfer limit'];
            return back()->withNotify($notify);
        }

        if ($general->balance_transfer_max < $amount) {
            $notify[] = ['error', 'Please follow maximum balance transfer limit'];
            return back()->withNotify($notify);
        }

        $user->balance -= $withCharge;
        $user->save();

        $trx                       = getTrx();
        $transaction               = new Transaction();
        $transaction->user_id      = $user->id;
        $transaction->amount       = $withCharge;
        $transaction->post_balance = $user->balance;
        $transaction->charge       = $charge;
        $transaction->trx_type     = '-';
        $transaction->remark       = 'balance_transfer';
        $transaction->details      = 'Balance transfer to ' . $toUser->username;
        $transaction->trx          = $trx;
        $transaction->save();

        notify($toUser, 'BALANCE_SEND', [
            'amount' => showAmount($amount),
            'charge' => showAmount($charge),
            'username' => $toUser->username,
            'post_balance' => showAmount($user->balance)
        ]);

        $toUser->balance += $amount;
        $toUser->save();

        $transaction = new Transaction();
        $transaction->user_id = $toUser->id;
        $transaction->amount = $amount;
        $transaction->post_balance = $toUser->balance;
        $transaction->charge = 0;
        $transaction->trx_type = '+';
        $transaction->remark = 'balance_transfer';
        $transaction->details = 'Balance receive from ' . $user->username;
        $transaction->trx = $trx;
        $transaction->save();

        notify($toUser, 'BALANCE_RECEIVE', [
            'amount' => showAmount($amount),
            'username' => $user->username,
            'post_balance' => showAmount($toUser->balance)
        ]);

        $notify[] = ['success', 'Balance transferred successfully'];
        return back()->withNotify($notify);
    }
}
