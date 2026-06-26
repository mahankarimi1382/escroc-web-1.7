<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Models\Admin\Currency;
use Illuminate\Support\Carbon;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use Illuminate\Support\Facades\Log;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use App\Traits\PaymentGateway\Manual;
use App\Traits\PaymentGateway\Stripe;
use Illuminate\Http\RedirectResponse;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\AdminNotification;
use App\Models\Admin\CryptoTransaction;
use Illuminate\Support\Facades\Session;
use App\Traits\PaymentGateway\RazorTrait;
use Illuminate\Support\Facades\Validator;
// Add Authorize.Net SDK imports
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\PaymentGatewayCurrency;
use net\authorize\api\contract\v1 as AnetAPI;
use App\Traits\PaymentGateway\SslcommerzTrait;
use App\Traits\PaymentGateway\FlutterwaveTrait;
use App\Http\Helpers\Api\Helpers as ApiResponse;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use net\authorize\api\controller as AnetController;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;
use App\Events\User\NotificationEvent as UserNotificationEvent;


class AddMoneyController extends Controller
{
    use Stripe, Manual, FlutterwaveTrait, RazorTrait, SslcommerzTrait;
    public $gateway;
    public $request;
    public function index()
    {
        $page_title = __("Add Money");
        $sender_currency             = Currency::where('status', true)->get();
        $payment_gateways_currencies = PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::add_money_slug());
            $gateway->where('status', 1);
        })->get();
        $transactions = Transaction::with('gateway_currency')->addMoney()->where('user_id', auth()->user()->id)->latest()->take(10)->get();
        return view('user.sections.add-money.index', compact("page_title", "transactions", "payment_gateways_currencies", "sender_currency"));
    }
    public function submit(Request $request)
    {
        $basic_setting = BasicSettings::first();
        $user          = auth()->user();
        if ($basic_setting->kyc_verification) {
            if ($user->kyc_verified == 0) {
                return redirect()->route('user.authorize.kyc')->with(['error' => [__('Please submit kyc information')]]);
            } elseif ($user->kyc_verified == 2) {
                return redirect()->route('user.authorize.kyc')->with(['error' => [__('Please wait before admin approved your kyc information')]]);
            } elseif ($user->kyc_verified == 3) {
                return redirect()->route('user.authorize.kyc')->with(['error' => [__('Admin rejected your kyc information')]]);
            }
        }
        try {
            $instance = PaymentGatewayHelper::init($request->all())->gateway()->render();
        } catch (Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return $instance;
    }
    public function success(Request $request, $gateway)
    {
        $requestData   = $request->all();
        $token         = $requestData['token'] ?? "";
        $checkTempData = TemporaryData::where("type", $gateway)->where("identifier", $token)->first();
        if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction faild. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();

        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive();
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully added money')]]);
    }
    public function cancel(Request $request, $gateway)
    {
        $token = session()->get('identifier');
        if ($token) {
            TemporaryData::where("identifier", $token)->delete();
        }

        return redirect()->route('user.add.money.index')->with(['error' => [__('You have canceled the payment')]]);
    }
    // ajax call for get user available balance by currency
    public function availableBalanceByCurrency(Request $request)
    {
        $user_wallets = UserWallet::where(['user_id' => auth()->user()->id, 'currency_id' => $request->id])->first();
        return $user_wallets->balance;
    }
    //add money manual payment gateway
    public function manualPayment()
    {
        $tempData        = Session::get('identifier');
        $hasData         = TemporaryData::where('identifier', $tempData)->first();
        $sender_currency = Currency::where('code', $hasData->data->amount->sender_currency)->first();
        $gateway         = PaymentGateway::manual()->where('slug', PaymentGatewayConst::add_money_slug())->where('id', $hasData->data->gateway)->first();
        $page_title      = "Manual Payment" . ' ( ' . $gateway->name . ' )';
        $digitShow       = $sender_currency->type == "CRYPTO" ? 6 : 2;
        if (!$hasData) {
            return redirect()->route('user.add.money.index');
        }
        return view('user.sections.add-money.manual.payment_confirmation', compact("page_title", "hasData", 'gateway', 'digitShow'));
    }
    //flutterwave payment success
    public function flutterwaveCallback()
    {
        $status = request()->status;
        //if payment is successful
        if ($status ==  'successful' || $status ==  'completed') {
            $transactionID = Flutterwave::getTransactionIDFromCallback();
            $data          = Flutterwave::verifyTransaction($transactionID);
            $requestData = request()->tx_ref;
            $token       = $requestData;
            $checkTempData = TemporaryData::where("type", 'flutterwave')->where("identifier", $token)->first();
            if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction faild. Record didn\'t saved properly. Please try again')]]);
            $checkTempData = $checkTempData->toArray();
            try {
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('flutterWave');
            } catch (Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully added money')]]);
        } elseif ($status ==  'cancelled') {
            return redirect()->route('user.add.money.index')->with(['error' => [__('Add money cancelled')]]);
        } else {
            return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed')]]);
        }
    }
    //stripe success
    public function stripePaymentSuccess($trx)
    {
        $token         = $trx;
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::STRIPE)->where("identifier", $token)->first();
        if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();
        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('stripe');
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
            return back()->with(['error' => ["Something Is Wrong..."]]);
        }

        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    //razor pay callback
    public function razorCallback()
    {
        $request_data = request()->all();
        //if payment is successful
        $token = $request_data['razorpay_order_id'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::RAZORPAY)->where("identifier", $token)->first();
        if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();
        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('razorpay');
        } catch (Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully added money')]]);
    }
    //sslcommerz success
    public function sllCommerzSuccess(Request $request)
    {
        $data          = $request->all();
        $token         = $data['tran_id'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::SSLCOMMERZ)->where("identifier", $token)->first();
        if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id    = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $user = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if ($data['status'] != "VALID") {
            return redirect()->route("user.add.money.index")->with(['error' => [__('Added Money Failed')]]);
        }
        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('sslcommerz');
        } catch (Exception $e) {
            return back()->with(['error' => ["Something Is Wrong..."]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    //sslCommerz fails
    public function sllCommerzFails(Request $request)
    {
        $data          = $request->all();
        $token         = $data['tran_id'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::SSLCOMMERZ)->where("identifier", $token)->first();
        if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id    = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $user          = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if ($data['status'] == "FAILED") {
            TemporaryData::destroy($checkTempData['id']);
            return redirect()->route("user.add.money.index")->with(['error' => [__('Add Money Failed')]]);
        }
    }
    //sslCommerz canceled
    public function sllCommerzCancel(Request $request)
    {
        $data          = $request->all();
        $token         = $data['tran_id'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::SSLCOMMERZ)->where("identifier", $token)->first();
        if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id    = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $user          = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if ($data['status'] != "VALID") {
            TemporaryData::destroy($checkTempData['id']);
            return redirect()->route("user.add.money.index")->with(['error' => [__('Add Money Canceled')]]);
        }
    }
    public function razorCancel()
    {
        return redirect()->route("user.add.money.index")->with(['error' => [__('Add Money Canceled')]]);
    }
    // Qrpay Call Back
    public function qrpayCallback(Request $request)
    {
        if ($request->type ==  'success') {

            $requestData = $request->all();

            $checkTempData = TemporaryData::where("type", 'qrpay')->where("identifier", $requestData['data']['custom'])->first();

            if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => ['Transaction faild. Record didn\'t saved properly. Please try again.']]);

            $checkTempData = $checkTempData->toArray();

            try {
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('qrpay');
            } catch (Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => ['Successfully added money']]);
        } else {
            return redirect()->route('user.add.money.index')->with(['error' => ['Transaction failed']]);
        }
    }

    // QrPay Cancel
    public function qrpayCancel(Request $request, $trx_id)
    {
        TemporaryData::where("identifier", $trx_id)->delete();
        return redirect()->route("user.add.money.index")->with(['error' => ['Payment Canceled']]);
    }
    //coingate response start
    public function coinGateSuccess(Request $request, $gateway)
    {
        try {
            $token = $request->token;
            $checkTempData = TemporaryData::where("type", PaymentGatewayConst::COINGATE)->where("identifier", $token)->first();
            if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);

            if (Transaction::where('callback_ref', $token)->exists()) {
                if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
            } else {
                if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }
            $update_temp_data = json_decode(json_encode($checkTempData->data), true);
            $update_temp_data['callback_data']  = $request->all();
            $checkTempData->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $checkTempData->toArray();
            PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('coingate');
        } catch (Exception $e) {
            return redirect()->route("user.add.money.index")->with(['error' => [__('Something went wrong! Please try again.')]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    public function coinGateCancel(Request $request, $gateway)
    {
        if ($request->has('token')) {
            $identifier = $request->token;
            if ($temp_data = TemporaryData::where('identifier', $identifier)->first()) {
                $temp_data->delete();
            }
        }
        return redirect()->route("user.add.money.index")->with(['error' => [__('Add money cancelled')]]);
    }
    public function pagaditoSuccess()
    {
        $request_data = request()->all();
        //if payment is successful
        $token = $request_data['param1'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::PAGADITO)->where("identifier", $token)->first();
        if ($checkTempData->data->env_type == 'addmoneyweb') {
            if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => ['Transaction Failed. Record didn\'t saved properly. Please try again.']]);
            $checkTempData = $checkTempData->toArray();
            try {
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('pagadito');
            } catch (Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => ['Successfully added money']]);
        } elseif ($checkTempData->data->env_type == 'addmoneyapi') {
            if (!$checkTempData) {
                $message = ['error' => ['Transaction Failed. Record didn\'t saved properly. Please try again.']];
                return ApiResponse::error($message);
            }
            $checkTempData = $checkTempData->toArray();
            try {
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceiveApi('pagadito');
            } catch (Exception $e) {
                $message = ['error' => [$e->getMessage()]];
                ApiResponse::error($message);
            }
            $message = ['success' => ["Payment Successful, Please Go Back Your App"]];
            return ApiResponse::onlySuccess($message);
        } else {
            $message = ['error' => ['Payment Failed,Please Contact With Owner']];
            ApiResponse::error($message);
        }
    }
    public function cryptoPaymentAddress(Request $request, $trx_id)
    {

        $page_title = "Crypto Payment Address";
        $transaction = Transaction::where('trx_id', $trx_id)->first();

        if ($transaction->gateway_currency->gateway->isCrypto() && $transaction->details?->payment_info?->receiver_address ?? false) {
            return view('user.sections.add-money.payment.crypto.address', compact(
                'transaction',
                'page_title',
            ));
        }

        return abort(404);
    }

    public function cryptoPaymentConfirm(Request $request, $trx_id)
    {
        $transaction = Transaction::where('trx_id', $trx_id)->where('status', PaymentGatewayConst::STATUSWAITING)->firstOrFail();

        $dy_input_fields = $transaction->details->payment_info->requirements ?? [];
        $validation_rules = $this->generateValidationRules($dy_input_fields);

        $validated = [];
        if (count($validation_rules) > 0) {
            $validated = Validator::make($request->all(), $validation_rules)->validate();
        }

        if (!isset($validated['txn_hash'])) return back()->with(['error' => ['Transaction hash is required for verify']]);

        $receiver_address = $transaction->details->payment_info->receiver_address ?? "";


        // check hash is valid or not
        $crypto_transaction = CryptoTransaction::where('txn_hash', $validated['txn_hash'])
            ->where('receiver_address', $receiver_address)
            ->where('asset', $transaction->gateway_currency->currency_code)
            ->where(function ($query) {
                return $query->where('transaction_type', "Native")
                    ->orWhere('transaction_type', "native");
            })
            ->where('status', PaymentGatewayConst::NOT_USED)
            ->first();

        if (!$crypto_transaction) return back()->with(['error' => ['Transaction hash is not valid! Please input a valid hash']]);

        if ($crypto_transaction->amount >= $transaction->total_payable == false) {
            if (!$crypto_transaction) return back()->with(['error' => ['Insufficient amount added. Please contact with system administrator']]);
        }

        DB::beginTransaction();
        try {
            // Update user wallet balance
            DB::table($transaction->user_wallets->getTable())
                ->where('id', $transaction->user_wallets->id)
                ->increment('balance', $transaction->sender_request_amount);

            // update crypto transaction as used
            DB::table($crypto_transaction->getTable())->where('id', $crypto_transaction->id)->update([
                'status'        => PaymentGatewayConst::USED,
            ]);

            // update transaction status
            $transaction_details = json_decode(json_encode($transaction->details), true);
            $transaction_details['payment_info']['txn_hash'] = $validated['txn_hash'];

            DB::table($transaction->getTable())->where('id', $transaction->id)->update([
                'details'       => json_encode($transaction_details),
                'status'        => PaymentGatewayConst::STATUSSUCCESS,
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return back()->with(['success' => ['Payment Confirmation Success!']]);
    }
    //perfect money success
    public function perfectMoneySuccess(Request $request, $gateway)
    {
        // dd($request->all());
        $requestData   = $request->all();
        $token         = $requestData['PAYMENT_ID'] ?? "";
        $checkTempData = TemporaryData::where("type", 'perfectmoney')->where("identifier", $token)->first();
        if (Transaction::where('callback_ref', $token)->exists()) {
            if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['success' => ['Transaction request sended successfully!']]);
        } else {
            if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => ['Transaction failed. Record didn\'t saved properly. Please try again.']]);
        }
        $update_temp_data = json_decode(json_encode($checkTempData->data), true);
        $update_temp_data['callback_data']  = $request->all();
        $checkTempData->update([
            'data'  => $update_temp_data,
        ]);
        $checkTempData = $checkTempData->toArray();

        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('perfectmoney');
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully added money')]]);
    }
    public function perfectMoneyCallback(Request $request, $gateway)
    {
        $callback_token = $request->get('token');
        $callback_data = $request->all();

        try {
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_MULTIPLE)->handleCallback($callback_token, $callback_data, $gateway);
        } catch (Exception $e) {
            // handle Error
            logger($e);
        }
    }
    public function callback(Request $request, $gateway)
    {
        $callback_token = $request->get('token');
        $callback_data = $request->all();

        try {
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_MULTIPLE)->handleCallback($callback_token, $callback_data, $gateway);
        } catch (Exception $e) {
            // handle Error
            logger($e);
        }
    }
    public function redirectUsingHTMLForm(Request $request, $gateway)
    {
        $temp_data = TemporaryData::where('identifier', $request->token)->first();
        if (!$temp_data || $temp_data->data->action_type != PaymentGatewayConst::REDIRECT_USING_HTML_FORM) return back()->with(['error' => ['Request token is invalid!']]);
        $redirect_form_data = $temp_data->data->redirect_form_data;
        $action_url         = $temp_data->data->action_url;
        $form_method        = $temp_data->data->form_method;

        return view('payment-gateway.redirect-form', compact('redirect_form_data', 'action_url', 'form_method'));
    }
    public function successGlobal(Request $request, $gateway)
    {

        try {
            $token = PaymentGatewayHelper::getToken($request->all(), $gateway);
            $temp_data = TemporaryData::where("identifier", $token)->first();

            if (Transaction::where('callback_ref', $token)->exists()) {
                if (!$temp_data) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
            } else {
                if (!$temp_data) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($temp_data->data), true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive($temp_data['type']);
            if ($instance instanceof RedirectResponse) return $instance;
        } catch (Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    public function postSuccess(Request $request, $gateway)
    {
        try {
            $token = PaymentGatewayHelper::getToken($request->all(), $gateway);
            $temp_data = TemporaryData::where("identifier", $token)->first();
            Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
        } catch (Exception $e) {
            return redirect()->route('index');
        }
        return $this->successGlobal($request, $gateway);
    }
    public function postCancel(Request $request, $gateway)
    {
        try {
            $token = PaymentGatewayHelper::getToken($request->all(), $gateway);
            $temp_data = TemporaryData::where("type", PaymentGatewayConst::TYPEADDMONEY)->where("identifier", $token)->first();
            if ($temp_data && $temp_data->data->creator_guard != 'api') {
                Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
            }
        } catch (Exception $e) {
            return redirect()->route("user.add.money.index")->with(['error' => ['Payment Canceled']]);
        }

        return $this->cancel($request, $gateway);
    }
    /**
     * Redirect Users for collecting payment via Button Pay (JS Checkout)
     */
    public function redirectBtnPay(Request $request, $gateway)
    {
        try {
            return PaymentGatewayHelper::init([])->handleBtnPay($gateway, $request->all());
        } catch (Exception $e) {
            return redirect()->route('user.add.money.index')->with(['error' => [$e->getMessage()]]);
        }
    }
    //paystack payment success
    public function paystackSuccess(Request $request, $gateway)
    {
        $requestData   = $request->all();
        $token         = $requestData['reference'] ?? $requestData['trxref'] ?? "";
        $checkTempData = TemporaryData::where("type", $gateway)->where("identifier", $token)->first();
        $transaction_data = Transaction::where('callback_ref', $token)->first();
        if ($transaction_data != null) {
            return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
        } else {
            if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
        }

        $checkTempData = $checkTempData->toArray();
        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('paystack');
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully added money')]]);
    }


    /**
     * Method for view authorize card view page
     * @param Illuminate\Http\Request $request,$identifier
     */
    public function authorizeCardInfo(Request $request, $identifier)
    {
        $page_title         = "Authorize card infomation";
        $temp_data          = TemporaryData::where('identifier', $identifier)->first();

        return view('user.sections.add-money.automatic.authorize', compact(
            'page_title',
            'temp_data'
        ));
    }
    /**
     * Method function authorize payment submit
     * @param Illuminate\Http\Request $request, $identifier
     */
    public function authorizePaymentSubmit(Request $request, $identifier)
    {
        $temp_data          = TemporaryData::where('identifier', $identifier)->first();

        if (!$temp_data) return back()->with(['error' => ['Sorry ! Data not found.']]);

        $validator          = Validator::make($request->all(), [
            'card_number'   => 'required',
            'date'          => 'required',
            'code'          => 'required'
        ]);


        if ($validator->fails()) return back()->withErrors($validator)->withInput($request->all());
        $validated          = $validator->validate();

        $gateway_credentials          = $this->authorizeCredentials($temp_data);
        $basic_settings               = BasicSettings::first();

        $validated['card_number']     = str_replace(' ', '', $validated['card_number']);

        $convert_date   = explode('/', $validated['date']);
        $month          = $convert_date[1];
        $year           = $convert_date[0];

        $current_year   = substr(date('Y'), 0, 2);
        $full_year      = $current_year . $year;

        $validated['date'] = $full_year . '-' . $month;

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($gateway_credentials->app_login_id);
        $merchantAuthentication->setTransactionKey($gateway_credentials->transaction_key);
        $amount = round($temp_data->data->amount->total_payable_amount, 6);


        // Set the transaction's refId
        $refId = 'ref' . time();

        // Create the payment data for a credit card
        $creditCard = new AnetAPI\CreditCardType();

        $creditCard->setCardNumber($validated['card_number']);
        $creditCard->setExpirationDate($validated['date']);
        $creditCard->setCardCode($validated['code']);


        // Add the payment data to a paymentType object
        $paymentOne = new AnetAPI\PaymentType();
        $paymentOne->setCreditCard($creditCard);

        $generate_invoice_number        = generate_random_string_number(10) . time();

        // Create order information
        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber($generate_invoice_number);
        $order->setDescription("Add Money");

        // Set the customer's Bill To address
        $customerAddress = new AnetAPI\CustomerAddressType();
        $customerAddress->setFirstName(auth()->user()->firstname);
        $customerAddress->setLastName(auth()->user()->lastname);
        $customerAddress->setCompany($basic_settings->site_name);
        $customerAddress->setAddress(auth()->user()->address->address);
        $customerAddress->setCity(auth()->user()->address->city);
        $customerAddress->setState(auth()->user()->address->state);
        $customerAddress->setZip(auth()->user()->address->zip);
        $customerAddress->setCountry(auth()->user()->address->country);

        $make_customer_id       = auth()->user()->id . $gateway_credentials->code;
        // Set the customer's identifying information
        $customerData = new AnetAPI\CustomerDataType();
        $customerData->setType("individual");
        $customerData->setId($make_customer_id);
        $customerData->setEmail(auth()->user()->email);

        // Add values for transaction settings
        $duplicateWindowSetting = new AnetAPI\SettingType();
        $duplicateWindowSetting->setSettingName("duplicateWindow");
        $duplicateWindowSetting->setSettingValue("60");

        // Create a TransactionRequestType object and add the previous objects to it
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("authCaptureTransaction");
        $transactionRequestType->setAmount($amount);
        $transactionRequestType->setOrder($order);
        $transactionRequestType->setPayment($paymentOne);
        $transactionRequestType->setBillTo($customerAddress);
        $transactionRequestType->setCustomer($customerData);
        $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
        // $transactionRequestType->addToUserFields($merchantDefinedField1);
        // $transactionRequestType->addToUserFields($merchantDefinedField2);

        // Assemble the complete transaction request
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);


        // Create the controller and get the response
        $controller = new AnetController\CreateTransactionController($request);

        if ($gateway_credentials->mode == GlobalConst::ENV_SANDBOX) {
            $environment = \net\authorize\api\constants\ANetEnvironment::SANDBOX;
        } else {
            $environment = \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
        }
        $response   = $controller->executeWithApiResponse($environment);

        if ($response != null) {
            // Check to see if the API request was successfully received and acted upon
            if ($response->getMessages()->getResultCode() == "Ok") {
                // Since the API request was successful, look for a transaction response
                // and parse it to display the results of authorizing the card
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    $trx_id = generate_unique_string("transactions", "trx_id", 16);
                    $status = PaymentGatewayConst::STATUSSUCCESS;
                    // dd($tresponse);
                    $inserted_id = $this->createTransactionAuthorize($trx_id, $temp_data, $status);

                    return redirect()->route('user.add.money.index')->with(['success' => ['Successfully Added Money']]);
                } else {
                    return redirect()->route('user.add.money.index')->with(['error' => ['Transaction Failed']]);
                    if ($tresponse->getErrors() != null) {
                        return redirect()->route('user.add.money.index')->with(['error' => [$tresponse->getErrors()[0]->getErrorText()]]);
                    }
                }
            } else {
                return redirect()->route('user.add.money.index')->with(['error' => ['Transaction Failed']]);
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getErrors() != null) {
                    return redirect()->route('user.add.money.index')->with(['error' => [$tresponse->getErrors()[0]->getErrorText()]]);
                } else {
                    return redirect()->route('user.add.money.index')->with(['error' => [$response->getMessages()->getMessage()[0]->getText()]]);
                }
            }
        } else {
            return redirect()->route('user.add.money.index')->with(['error' => ['No response returned']]);
        }
    }
    // For get the gateway credentials
    function authorizeCredentials($temp_data)
    {
        $gateway             = PaymentGateway::where('id', $temp_data->data->gateway)->first() ?? null;
        if (!$gateway) throw new Exception(__("Payment gateway not available"));
        $credentials         = $gateway->credentials;
        $app_login_id        = getPaymentCredentials($credentials, 'App Login ID');
        $transaction_key     = getPaymentCredentials($credentials, 'Transaction Key');
        $signature_key       = getPaymentCredentials($credentials, 'Signature Key');

        $mode           = $gateway->env;

        $authorize_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "live",
        ];
        if (array_key_exists($mode, $authorize_register_mode)) {
            $mode = $authorize_register_mode[$mode];
        } else {
            $mode = "sandbox";
        }

        return (object) [
            'app_login_id'          => $app_login_id,
            'transaction_key'       => $transaction_key,
            'signature_key'         => $signature_key,
            'mode'                  => $mode,
            'code'                  => $gateway->code
        ];
    }
    // Fro insert data in db
    function createTransactionAuthorize($trx_id, $temp_data, $status)
    {
        $trx_id = $trx_id;
        try {

            $user_id = auth()->user()->id;

            $inserted_id = $this->insertRecord($temp_data, $trx_id);
            $this->insertCharges($temp_data, $inserted_id);
            $this->insertDevice($temp_data, $inserted_id);
            $this->removeTempData($temp_data);
        } catch (Exception $e) {
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $inserted_id;
    }
    public function insertRecord($temp_data, $trx_id)
    {

        $wallet  = UserWallet::where('id', $temp_data->data->wallet_id)->first();
        $gateway = PaymentGateway::where('id', $temp_data->data->gateway)->first();
        $trx_id = $trx_id;
        $token  = $this->output['tempData']['identifier'] ?? "";
        DB::beginTransaction();
        try {
            $id = DB::table("transactions")->insertGetId([
                'user_id'                     => auth()->user()->id,
                'user_wallet_id'              => $temp_data->data->wallet_id,
                'payment_gateway_currency_id' => $temp_data->data->currency,
                'type'                        => $temp_data->type,
                'trx_id'                      => $trx_id,
                'sender_request_amount'       => $temp_data->data->amount->requested_amount,
                'sender_currency_code'        => $temp_data->data->amount->sender_currency,
                'total_payable'               => $temp_data->data->amount->total_payable_amount,
                'exchange_rate'               => $temp_data->data->amount->exchange_rate,
                'available_balance'           => $wallet->balance + $temp_data->data->amount->requested_amount,
                'remark'                      => "add_money",
                'details'                     => 'Add money via Authorize.Net',
                'status'                      => true,
                'attribute'                   => PaymentGatewayConst::SEND,
                'created_at'                  => now(),
            ]);

            $this->updateWalletBalance($temp_data, $wallet);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }

    public function updateWalletBalance($temp_data, $wallet)
    {
        $update_amount = $wallet->balance + $temp_data->data->amount->requested_amount;

        $wallet->update([
            'balance' => $update_amount,
        ]);
    }

    public function insertCharges($temp_data, $id)
    {
        if (Auth::guard(get_auth_guard())->check()) {
            $user = auth()->guard(get_auth_guard())->user();
        }
        DB::beginTransaction();
        try {
            DB::table('transaction_details')->insert([
                'transaction_id' => $id,
                'percent_charge' => $temp_data->data->amount->gateway_percent_charge,
                'fixed_charge'   => $temp_data->data->amount->gateway_fixed_charge,
                'total_charge'   => $temp_data->data->amount->gateway_total_charge,
                'created_at'     => now(),
            ]);
            DB::commit();

            // notification
            $notification_content = [
                'title'   => "Add Money",
                'message' => "Your Wallet (" . $temp_data->data->amount->sender_currency . ") balance  has been added " . $temp_data->data->amount->requested_amount . ' ' . $temp_data->data->amount->sender_currency,
                'time'    => Carbon::now()->diffForHumans(),
                'image'   => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'    => NotificationConst::BALANCE_ADDED,
                'user_id' => auth()->user()->id,
                'message' => $notification_content,
            ]);
            //Push Notifications
            $basic_setting = BasicSettings::first();
            try {
                if ($basic_setting->push_notification == true) {
                    (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
            //admin create notifications
            $notification_content['title'] = 'Add Money ' . $temp_data->data->amount->requested_amount . ' ' . $temp_data->data->amount->sender_currency;
            AdminNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'admin_id'  => 1,
                'message'   => $notification_content,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    public function insertDevice($temp_data, $id)
    {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();
        $mac = "";

        DB::beginTransaction();
        try {
            DB::table("transaction_devices")->insert([
                'transaction_id' => $id,
                'ip'            => $client_ip,
                'mac'           => $mac,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    public function removeTempData($temp_data)
    {
        TemporaryData::where("identifier", $temp_data->identifier)->delete();
    }
}
