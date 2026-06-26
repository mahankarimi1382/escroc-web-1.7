<?php

namespace App\Http\Controllers\Api\V1;

use Exception;
use Carbon\Carbon;
use App\Models\UserWallet;
use App\Models\Transaction;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Models\UserNotification;
use App\Models\Admin\BasicSettings;
use Illuminate\Support\Facades\DB;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use App\Traits\PaymentGateway\Manual;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\AdminNotification;
use App\Models\Admin\CryptoTransaction;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\PaymentGatewayCurrency;
use net\authorize\api\contract\v1 as AnetAPI;
use App\Traits\PaymentGateway\SslcommerzTrait;
use App\Http\Helpers\Api\Helpers as ApiResponse;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use net\authorize\api\controller as AnetController;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;
use App\Events\User\NotificationEvent as UserNotificationEvent;

class AddMoneyController extends Controller
{
    use Manual, SslcommerzTrait;
    public function index()
    {
        $user = auth()->user();
        // user wallet
        $userWallet = UserWallet::with('currency')->where('user_id', $user->id)->get()->map(function ($data) {
            return [
                'name'            => $data->currency->name,
                'balance'         => $data->balance,
                'currency_code'   => $data->currency->code,
                'currency_symbol' => $data->currency->symbol,
                'currency_type'   => $data->currency->type,
                'rate'            => $data->currency->rate,
                'flag'            => $data->currency->flag,
                'image_path'      => get_files_public_path('currency-flag'),
            ];
        });
        //add money payment gateways currencys
        $gatewayCurrencies = PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::add_money_slug());
            $gateway->where('status', 1);
        })->get()->map(function ($data) {
            return [
                'id'                 => $data->id,
                'payment_gateway_id' => $data->payment_gateway_id,
                'type'               => $data->gateway->type,
                'name'               => $data->name,
                'alias'              => $data->alias,
                'currency_code'      => $data->currency_code,
                'currency_symbol'    => $data->currency_symbol,
                'image'              => $data->image,
                'min_limit'          => getAmount($data->min_limit, 8),
                'max_limit'          => getAmount($data->max_limit, 8),
                'percent_charge'     => getAmount($data->percent_charge, 8),
                'fixed_charge'       => getAmount($data->fixed_charge, 8),
                'rate'               => getAmount($data->rate, 8),
                'created_at'         => $data->created_at,
                'updated_at'         => $data->updated_at,
            ];
        });
        //add money transactions
        $transactions = Transaction::where('user_id', auth()->user()->id)->addMoney()->latest()->take(5)->get()->map(function ($item) {
            return [
                'id'                    => $item->id,
                'trx_id'                => $item->trx_id,
                'gateway_currency'      => $item->gateway_currency->name,
                'transaction_type'      => $item->type,
                'sender_request_amount' => $item->sender_request_amount,
                'sender_currency_code'  => $item->sender_currency_code,
                'total_payable'         => $item->total_payable,
                'gateway_currency_code' => $item->gateway_currency->currency_code,
                'exchange_rate'         => $item->exchange_rate,
                'fee'                   => $item->transaction_details->total_charge,
                'rejection_reason'      => $item->reject_reason ?? null,
                'created_at'            => $item->created_at,
            ];
        });
        $data = [
            'base_curr'         => get_default_currency_code(),
            'base_curr_rate'    => get_amount(1),
            'default_image'     => "backend/images/default/default.webp",
            'image_path'        => "backend/images/payment-gateways",
            'base_url'          => get_asset_url(),
            'userWallet'        => (object)$userWallet,
            'gatewayCurrencies' => $gatewayCurrencies,
            'transactionss'     => $transactions,
        ];
        $message = ['success' => [__('Add Money Information')]];
        return ApiResponse::success($message, $data);
    }
    //add money submit
    public function submit(Request $request)
    {
        try {
            $instance = PaymentGatewayHelper::init($request->all())->gateway()->api()->get();
            $trx = $instance['response']['id'] ?? $instance['response']['trx'] ?? $instance['response']['reference_id'] ?? $instance['response']['tokenValue'] ?? $instance['response']['url'] ?? $instance['response']['temp_identifier'] ?? $instance['order_id'] ?? $instance['response']['identifier'] ?? $instance['response'] ?? "";

            $temData = TemporaryData::where('identifier', $trx)->first();

            if (!$temData) {
                $error = ['error' => ["Invalid Request"]];
                return ApiResponse::onlyError($error);
            }
            $payment_gateway_currency = PaymentGatewayCurrency::where('id', $temData->data->currency)->first();
            $payment_gateway          = PaymentGateway::where('id', $temData->data->gateway)->first();

            $payment_informations = [
                'trx'                   => $temData->identifier,
                'gateway_currency_name' => $payment_gateway_currency->name,
                'request_amount'        => get_amount($temData->data->amount->requested_amount, $temData->data->amount->sender_currency),
                'exchange_rate'         => "1" . ' ' . $temData->data->amount->sender_currency . ' = ' . get_amount($temData->data->amount->exchange_rate, $temData->data->amount->gateway_cur_code),
                'total_charge'          => get_amount($temData->data->amount->gateway_total_charge, $temData->data->amount->gateway_cur_code),
                'will_get'              => get_amount($temData->data->amount->requested_amount, $temData->data->amount->sender_currency),
                'payable_amount'        => get_amount($temData->data->amount->total_payable_amount, $temData->data->amount->gateway_cur_code),
            ];

            if ($payment_gateway->type == "AUTOMATIC") {
                if ($temData->type == PaymentGatewayConst::STRIPE) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => @$temData->data->response->link . "?prefilled_email=" . @auth()->user()->email,
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } else if ($temData->type == PaymentGatewayConst::PAYPAL) {
                    $data = [
                        'gategay_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => @$temData->data->response->links,
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } else if ($temData->type == PaymentGatewayConst::FLUTTER_WAVE) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => @$temData->data->response->link,
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } else if ($temData->type == PaymentGatewayConst::SSLCOMMERZ) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => @$temData->data->response->link,
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } else if ($temData->type == PaymentGatewayConst::RAZORPAY) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => $instance['response']['redirect_url'],
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } else if ($temData->type == PaymentGatewayConst::QRPAY) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => @$instance['response']['link'],
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } else if ($temData->type == PaymentGatewayConst::PAGADITO) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => @$temData->data->response->value,
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } else if ($temData->type == PaymentGatewayConst::COINGATE) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => @$instance['response']['link'],
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } else if ($temData->type == 'perfectmoney') {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'url'                   => @$instance['response']['link'],
                        'method'                => "get",
                    ];
                    $message = ['success' => [__('Add Money Inserted Successfully')]];
                    return ApiResponse::success($message, $data);
                } elseif ($temData->type == PaymentGatewayConst::TATUM) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'action_type'           => $instance['response']['type']  ?? false,
                        'address_info'          => $instance['response']['address_info'] ?? [],
                        'url'                   => $instance['response']['redirect_url'],
                        'method'                => "get",
                    ];
                    $message =  ['success' => ['Add Money Inserted Successfully']];
                    return ApiResponse::success($message, $data);
                } elseif ($temData->type == PaymentGatewayConst::PAYSTACK) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'action_type'           => $instance['response']['type']  ?? false,
                        'address_info'          => $instance['response']['address_info'] ?? [],
                        'url'                   => $instance['response']['link'],
                        'method'                => "get",
                    ];
                    $message =  ['success' => ['Add Money Inserted Successfully']];
                    return ApiResponse::success($message, $data);
                } elseif ($payment_gateway->alias == PaymentGatewayConst::AUTHORIZE) {
                    $data = [
                        'gateway_type'          => $payment_gateway->type,
                        'gateway_currency_name' => $payment_gateway_currency->name,
                        'alias'                 => $payment_gateway_currency->alias,
                        'identify'              => $temData->type,
                        'payment_informations'  => $payment_informations,
                        'action_type'           => $instance['response']['type']  ?? false,
                        'address_info'          => $instance['response']['address_info'] ?? [],
                        'url'                   => $instance['response']['redirect_url'],
                        'method'                => "post",
                    ];
                    $message =  ['success' => ['Add Money Inserted Successfully']];
                    return ApiResponse::success($message, $data);
                }
            } elseif ($payment_gateway->type == "MANUAL") {
                $data = [
                    'gategay_type'          => $payment_gateway->type,
                    'gateway_currency_name' => $payment_gateway_currency->name,
                    'alias'                 => $payment_gateway_currency->alias,
                    'identify'              => $temData->type,
                    'details'               => strip_tags($payment_gateway->desc) ?? null,
                    'input_fields'          => $payment_gateway->input_fields ?? null,
                    'payment_informations'  => $payment_informations,
                    'url'                   => route('api.v1.user.add-money.manual.payment.confirmed'),
                    'method'                => "post",
                ];
                $message = ['success' => [__('Add Money Inserted Successfully')]];
                return ApiResponse::success($message, $data);
            } else {
                $error = ['error' => [__("Something is wrong")]];
                return ApiResponse::onlyError($error);
            }
        } catch (Exception $e) {
            $error = ['error' => [$e->getMessage()]];
            return ApiResponse::onlyError($error);
        }
    }
    //api payment success
    public function apiPaymentSuccess(Request $request, $gateway)
    {
        $requestData   = $request->all();
        $token         = $requestData['token'] ?? "";
        $checkTempData = TemporaryData::where("type", $gateway)->where("identifier", $token)->first();
        if (!$checkTempData) {
            $message = ['error' => [__("Transaction failed. Record didn\'t saved properly. Please try again")]];
            return ApiResponse::onlyError($message);
        }

        $checkTempData = $checkTempData->toArray();
        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceiveApi();
        } catch (Exception $e) {
            $message = ['error' => [$e->getMessage()]];
            return ApiResponse::onlyError($message);
        }
        $message = ['success' => [__("Payment successful, please go back your app")]];
        return ApiResponse::onlySuccess($message);
    }
    public function postSuccess(Request $request, $gateway)
    {
        try {
            $token = PaymentGatewayHelper::getToken($request->all(), $gateway);
            $temp_data = TemporaryData::where("identifier", $token)->first();
            if ($temp_data && $temp_data->data->creator_guard != 'api') {
                Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
            }
        } catch (Exception $e) {
            $message = ['error' => [$e->getMessage()]];
            return ApiResponse::onlyError($message);
        }

        return $this->successGlobal($request, $gateway);
    }

    public function postCancel(Request $request, $gateway)
    {
        try {
            $token = PaymentGatewayHelper::getToken($request->all(), $gateway);
            $temp_data = TemporaryData::where("identifier", $token)->first();
            if ($temp_data && $temp_data->data->creator_guard != 'api') {
                Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
            }
        } catch (Exception $e) {
            $message = ['error' => [$e->getMessage()]];
            return ApiResponse::onlyError($message);
        }

        return $this->cancel($request, $gateway);
    }
    //stripe success
    public function stripePaymentSuccess($trx)
    {
        $token = $trx;
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::STRIPE)->where("identifier", $token)->first();
        $message       = ['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]];

        if (!$checkTempData) return ApiResponse::error($message);
        $checkTempData = $checkTempData->toArray();

        $creator_table = $checkTempData['data']->creator_table ?? null;
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $api_authenticated_guards = PaymentGatewayConst::apiAuthenticateGuard();
        if ($creator_table != null && $creator_id != null && $creator_guard != null) {
            if (!array_key_exists($creator_guard, $api_authenticated_guards)) throw new Exception(__('Request user doesn\'t save properly. Please try again'));
            $creator = DB::table($creator_table)->where("id", $creator_id)->first();
            if (!$creator) throw new Exception(__("Request user doesn\'t save properly. Please try again"));
            $api_user_login_guard = $api_authenticated_guards[$creator_guard];
            Auth::guard($api_user_login_guard)->loginUsingId($creator->id);
        }

        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceiveApi('stripe');
        } catch (Exception $e) {
            $message = ['error' => ["Something Is Wrong..."]];
            ApiResponse::error($message);
        }
        $message = ['success' => [__("Payment Successful, Please Go Back Your App")]];
        return ApiResponse::onlysuccess($message);
    }
    //flutter wave paynebt syccess
    public function flutterWavePaymentSuccess()
    {
        $status = request()->status;
        if ($status ==  'successful' || $status ==  'completed') {

            $transactionID = Flutterwave::getTransactionIDFromCallback();
            $data          = Flutterwave::verifyTransaction($transactionID);

            $requestData = request()->tx_ref;

            $token = $requestData;

            $checkTempData = TemporaryData::where("type", 'flutterwave')->where("identifier", $token)->first();

            $message = ['error' => [__('Transaction faild. Record didn\'t saved properly. Please try again')]];

            if (!$checkTempData) return ApiResponse::error($message);

            $checkTempData = $checkTempData->toArray();
            try {
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('flutterWave');
            } catch (Exception $e) {
                $message = ['error' => [$e->getMessage()]];
                ApiResponse::error($message);
            }
            $message = ['success' => [__("Payment successful, Please Go Back Your App")]];
            return ApiResponse::onlySuccess($message);
        } elseif ($status ==  'cancelled') {
            $message = ['error' => ['Payment Cancelled']];
            ApiResponse::error($message);
        } else {
            $message = ['error' => ['Payment Failed']];
            ApiResponse::error($message);
        }
    }
    //razor payment link create
    public function razorPaymentLink($trx)
    {
        $temData = TemporaryData::where('identifier', $trx)->first();
        if (!$temData) {
            $message = ['error' => [__('Transaction faild. Record didn\'t saved properly. Please try again')]];
            ApiResponse::error($message);
        }
        return view('user.sections.add-money.automatic.razor-api', compact('temData'));
    }
    //razor pay callback
    public function razorCallback()
    {
        $request_data = request()->all();
        //if payment is successful
        $token = $request_data['razorpay_order_id'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::RAZORPAY)->where("identifier", $token)->first();
        $message       = ['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]];

        if (!$checkTempData) return ApiResponse::error($message);
        $checkTempData = $checkTempData->toArray();

        $creator_table = $checkTempData['data']->creator_table ?? null;
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $api_authenticated_guards = PaymentGatewayConst::apiAuthenticateGuard();
        if ($creator_table != null && $creator_id != null && $creator_guard != null) {
            if (!array_key_exists($creator_guard, $api_authenticated_guards)) throw new Exception(__('Request user doesn\'t save properly. Please try again'));
            $creator = DB::table($creator_table)->where("id", $creator_id)->first();
            if (!$creator) throw new Exception(__("Request user doesn\'t save properly. Please try again"));
            $api_user_login_guard = $api_authenticated_guards[$creator_guard];
            Auth::guard($api_user_login_guard)->loginUsingId($creator->id);
        }

        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceiveApi('razorpay');
        } catch (Exception $e) {
            $message = ['error' => [__("Something Is Wrong")]];
            ApiResponse::error($message);
        }
        $message = ['success' => [__("Payment Successful, Please Go Back Your App")]];
        return ApiResponse::onlysuccess($message);
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
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceiveApi('qrpay');
            } catch (Exception $e) {
                ApiResponse::error($e->getMessage());
            }
            $message = ['success' => [__("Payment Successful, Please Go Back Your App")]];
            return ApiResponse::onlysuccess($message);
        } else {
            ApiResponse::error('Transaction failed');
        }
    }
    public function coinGateSuccess(Request $request, $gateway)
    {
        try {
            $token = $request->token;
            $checkTempData = TemporaryData::where("type", PaymentGatewayConst::COINGATE)->where("identifier", $token)->first();
            if (Transaction::where('callback_ref', $token)->exists()) {
                if (!$checkTempData) {
                    $message = ['error' => ["Transaction request sended successfully!"]];
                    return ApiResponse::error($message);
                }
            } else {
                if (!$checkTempData) {
                    $message = ['error' => ["Transaction failed. Record didn\'t saved properly. Please try again."]];
                    return ApiResponse::error($message);
                }
            }
            $update_temp_data = json_decode(json_encode($checkTempData->data), true);
            $update_temp_data['callback_data']  = $request->all();
            $checkTempData->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $checkTempData->toArray();
            PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceiveApi('coingate');
        } catch (Exception $e) {
            $message = ['error' => [$e->getMessage()]];
            return ApiResponse::error($message);
        }
        $message = ['success' => ["Add Money Successful, Please Go Back Your App"]];
        return ApiResponse::onlySuccess($message);
    }
    public function paystactSuccess(Request $request, $gateway)
    {
        try {
            $token = $request->trxref;
            $checkTempData = TemporaryData::where("type", PaymentGatewayConst::PAYSTACK)->where("identifier", $token)->first();
            if (!$checkTempData) {
                $message = ['error' => ["Add Money Successful, Please Go Back Your App"]];
                return ApiResponse::onlySuccess($message);
            }
            $update_temp_data = json_decode(json_encode($checkTempData->data), true);
            $update_temp_data['callback_data']  = $request->all();
            $checkTempData->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $checkTempData->toArray();
            PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceiveApi('paystack');
        } catch (Exception $e) {
            $message = ['error' => [$e->getMessage()]];
            return ApiResponse::error($message);
        }
        $message = ['success' => ["Add Money Successful, Please Go Back Your App"]];
        return ApiResponse::onlySuccess($message);
    }
    //sslcommerz success
    public function sllCommerzSuccess(Request $request)
    {
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::SSLCOMMERZ)->where("identifier", $token)->first();
        $message = ['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]];
        if (!$checkTempData) return ApiResponse::error($message);
        $checkTempData = $checkTempData->toArray();

        $creator_table = $checkTempData['data']->creator_table ?? null;
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $api_authenticated_guards = PaymentGatewayConst::apiAuthenticateGuard();
        if ($creator_table != null && $creator_id != null && $creator_guard != null) {
            if (!array_key_exists($creator_guard, $api_authenticated_guards)) throw new Exception(__('Request user doesn\'t save properly. Please try again'));
            $creator = DB::table($creator_table)->where("id", $creator_id)->first();
            if (!$creator) throw new Exception(__("Request user doesn\'t save properly. Please try again"));
            $api_user_login_guard = $api_authenticated_guards[$creator_guard];
            Auth::guard($api_user_login_guard)->loginUsingId($creator->id);
        }
        if ($data['status'] != "VALID") {
            $message = ['error' => ["Added Money Failed"]];
            return ApiResponse::error($message);
        }
        try {
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceiveApi('sslcommerz');
        } catch (Exception $e) {
            $message = ['error' => [$e->getMessage()]];
            return ApiResponse::error($message);
        }
        $message = ['success' => [__("Payment Successful, Please Go Back Your App")]];
        return ApiResponse::onlysuccess($message);
    }
    //sslCommerz fails
    public function sllCommerzFails(Request $request)
    {
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::SSLCOMMERZ)->where("identifier", $token)->first();
        $message = ['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]];
        if (!$checkTempData) return ApiResponse::error($message);
        $checkTempData = $checkTempData->toArray();

        $creator_table = $checkTempData['data']->creator_table ?? null;
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;

        $api_authenticated_guards = PaymentGatewayConst::apiAuthenticateGuard();
        if ($creator_table != null && $creator_id != null && $creator_guard != null) {
            if (!array_key_exists($creator_guard, $api_authenticated_guards)) throw new Exception(__('Request user doesn\'t save properly. Please try again'));
            $creator = DB::table($creator_table)->where("id", $creator_id)->first();
            if (!$creator) throw new Exception(__("Request user doesn\'t save properly. Please try again"));
            $api_user_login_guard = $api_authenticated_guards[$creator_guard];
            Auth::guard($api_user_login_guard)->loginUsingId($creator->id);
        }
        if ($data['status'] == "FAILED") {
            TemporaryData::destroy($checkTempData['id']);
            $message = ['error' => ["Added Money Failed"]];
            return ApiResponse::error($message);
        }
    }
    //sslCommerz canceled
    public function sllCommerzCancel(Request $request)
    {
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type", PaymentGatewayConst::SSLCOMMERZ)->where("identifier", $token)->first();
        $message = ['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]];
        if (!$checkTempData) return ApiResponse::error($message);
        $checkTempData = $checkTempData->toArray();


        $creator_table = $checkTempData['data']->creator_table ?? null;
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;

        $api_authenticated_guards = PaymentGatewayConst::apiAuthenticateGuard();
        if ($creator_table != null && $creator_id != null && $creator_guard != null) {
            if (!array_key_exists($creator_guard, $api_authenticated_guards)) throw new Exception(__('Request user doesn\'t save properly. Please try again'));
            $creator = DB::table($creator_table)->where("id", $creator_id)->first();
            if (!$creator) throw new Exception(__("Request user doesn\'t save properly. Please try again"));
            $api_user_login_guard = $api_authenticated_guards[$creator_guard];
            Auth::guard($api_user_login_guard)->loginUsingId($creator->id);
        }
        if ($data['status'] != "VALID") {
            TemporaryData::destroy($checkTempData['id']);
            $message = ['error' => [__("Added Money Canceled")]];
            return ApiResponse::error($message);
        }
    }
    public function tatumUserTransactionRequirements($trx_type = null)
    {
        $requirements = [
            PaymentGatewayConst::TYPEADDMONEY => [
                [
                    'type'          => 'text',
                    'label'         =>  "Txn Hash",
                    'placeholder'   => "Enter Txn Hash",
                    'name'          => "txn_hash",
                    'required'      => true,
                    'validation'    => [
                        'min'           => "0",
                        'max'           => "250",
                        'required'      => true,
                    ]
                ]
            ],
        ];

        if ($trx_type) {
            if (!array_key_exists($trx_type, $requirements)) throw new Exception("User Transaction Requirements Not Found!");
            return $requirements[$trx_type];
        }

        return $requirements;
    }
    public function cryptoPaymentAddress(Request $request, $trx_id)
    {
        $transaction = Transaction::where('trx_id', $trx_id)->first();
        $transactionData = [
            'id'                    => $transaction->id,
            'trx_id'                => $transaction->trx_id,
            'gateway_currency'      => $transaction->gateway_currency->name,
            'transaction_type'      => $transaction->type,
            'sender_request_amount' => $transaction->sender_request_amount,
            'sender_currency_code'  => $transaction->sender_currency_code,
            'total_payable'         => $transaction->total_payable,
            'gateway_currency_code' => $transaction->gateway_currency->currency_code,
            'exchange_rate'         => $transaction->exchange_rate,
            'fee'                   => $transaction->transaction_details->total_charge,
            'rejection_reason'      => $transaction->reject_reason ?? null,
            'created_at'            => $transaction->created_at,
        ];
        if ($transaction->gateway_currency->gateway->isCrypto() && $transaction->details?->payment_info?->receiver_address ?? false) {
            $data = [
                'transaction'         => $transactionData,
                'address_info'      => [
                    'coin'          => $transaction->details?->payment_info?->currency ?? "",
                    'address'       => $transaction->details?->payment_info?->receiver_address ?? "",
                    'input_fields'  => $this->tatumUserTransactionRequirements(PaymentGatewayConst::TYPEADDMONEY),
                    'submit_url'    => route('api.v1.add-money.payment.crypto.confirm', $trx_id),
                    'method'        => "post",
                ],
                'base_url'          => get_asset_url(),
            ];
            $message = ['success' => [__('Add Money Information')]];
            return ApiResponse::success($message, $data);
        }

        return ApiResponse::error(['error' => ['Something went wrong! Please try again']]);
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

        if (!isset($validated['txn_hash'])) return ApiResponse::error(['error' => ['Transaction hash is required for verify']]);

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

        if (!$crypto_transaction) return ApiResponse::error(['error' => ['Transaction hash is not valid! Please input a valid hash']]);

        if ($crypto_transaction->amount >= $transaction->total_payable == false) {
            if (!$crypto_transaction) ApiResponse::error(['error' => ['Insufficient amount added. Please contact with system administrator']]);
        }

        DB::beginTransaction();
        try {

            // Update user wallet balance
            DB::table($transaction->user_wallet->getTable())
                ->where('id', $transaction->user_wallet->id)
                ->increment('balance', $transaction->request_amount);

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
            return ApiResponse::error(['error' => ['Something went wrong! Please try again']]);
        }

        return ApiResponse::onlySuccess(['error' => ['Payment Confirmation Success!']]);
    }
    public function redirectBtnPay(Request $request, $gateway)
    {
        try {
            return PaymentGatewayHelper::init([])->handleBtnPay($gateway, $request->all());
        } catch (Exception $e) {
            $message = ['error' => [$e->getMessage()]];
            return ApiResponse::error($message);
        }
    }
    public function successGlobal(Request $request, $gateway)
    {
        try {
            $token = PaymentGatewayHelper::getToken($request->all(), $gateway);
            $temp_data = TemporaryData::where("identifier", $token)->first();

            if (!$temp_data) {
                if (Transaction::where('callback_ref', $token)->exists()) {
                    $message = ['error' => [__('Transaction request sended successfully!')]];
                    return ApiResponse::error($message);
                } else {
                    $message = ['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]];
                    return ApiResponse::error($message);
                }
            }

            $update_temp_data = json_decode(json_encode($temp_data->data), true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive($temp_data['type']);

            // return $instance;
        } catch (Exception $e) {
            $message = ['error' => [$e->getMessage()]];
            return ApiResponse::error($message);
        }
        $message = ['success' => [__('Successfully Added Money')]];
        return ApiResponse::onlysuccess($message);
    }

    /**
     * Method function authorize payment submit
     * @param Illuminate\Http\Request $request, $identifier
     */
    public function authorizePaymentSubmit(Request $request)
    {
        $temp_data          = TemporaryData::where('identifier', $request->trx)->first();

        if (!$temp_data) return ApiResponse::error(['error' => ['Sorry ! Data not found.']]);

        $validator          = Validator::make($request->all(), [
            'trx'    => 'required',
            'card_number'   => 'required',
            'date'          => 'required',
            'code'          => 'required'
        ]);

        if ($validator->fails()) {
            $error =  ['error' => $validator->errors()->all()];
            return ApiResponse::validation($error);
        }
        $validated          = $validator->validate();

        $gateway_credentials          = $this->authorizeCredentials($temp_data);
        $basic_settings               = BasicSettings::first();

        $creator_table = $temp_data['data']->creator_table ?? null;
        $creator_id = $temp_data['data']->creator_id ?? null;
        $creator_guard = $temp_data['data']->creator_guard ?? null;

        $api_authenticated_guards = PaymentGatewayConst::apiAuthenticateGuard();
        if ($creator_table != null && $creator_id != null && $creator_guard != null) {
            if (!array_key_exists($creator_guard, $api_authenticated_guards)) throw new Exception(__('Request user doesn\'t save properly. Please try again'));
            $creator = DB::table($creator_table)->where("id", $creator_id)->first();
            if (!$creator) throw new Exception(__("Request user doesn\'t save properly. Please try again"));
            $api_user_login_guard = $api_authenticated_guards[$creator_guard];
            Auth::guard($api_user_login_guard)->loginUsingId($creator->id);
        }

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

                    $message = ['success' => [__("Payment Successful, Please Go Back Your App")]];
                    return ApiResponse::onlysuccess($message);
                } else {
                    return ApiResponse::error(['error' => ['Transaction Failed']]);
                    if ($tresponse->getErrors() != null) {
                        return ApiResponse::error(['error' => [$tresponse->getErrors()[0]->getErrorText()]]);
                    }
                }
            } else {
                return ApiResponse::error(['error' => ['Transaction Failed']]);
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getErrors() != null) {
                    return ApiResponse::error(['error' => [$tresponse->getErrors()[0]->getErrorText()]]);
                } else {
                    return ApiResponse::error(['error' => [$response->getMessages()->getMessage()[0]->getText()]]);
                }
            }
        } else {
            return ApiResponse::error(['error' => ['No response returned']]);
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
                    (new PushNotificationHelper())->prepareApi([$user->id],[
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
