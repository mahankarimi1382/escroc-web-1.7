<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\User;
use App\Models\Escrow;
use App\Models\EscrowChat;
use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\EscrowDetails;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use Illuminate\Support\Carbon;
use App\Models\UserNotification;
use App\Http\Helpers\Api\Helpers;
use App\Constants\EscrowConstants;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use App\Events\EscrowConversationEvent;
use App\Models\Admin\CryptoTransaction;
use Illuminate\Support\Facades\Session;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\EscrowPaymentGateway;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Models\EscrowConversationAttachment;
use App\Notifications\Escrow\EscrowApprovel;
use App\Notifications\Escrow\EscrowReleased;
use net\authorize\api\contract\v1 as AnetAPI;
use App\Notifications\Escrow\EscrowDisputePayment;
use App\Notifications\Escrow\EscrowReleasedRequest;
use net\authorize\api\controller as AnetController;
use App\Events\User\NotificationEvent as UserNotificationEvent;

class EscrowActionsController extends Controller
{
    use ControlDynamicInputFields;
    public function paymentApprovalPending($id)
    {
        $page_title                  = "Payment Approval";
        $escrow                      = Escrow::findOrFail(decrypt($id));
        $payment_gateways_currencies = PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::add_money_slug());
            $gateway->where('status', 1);
        })->get();
        $user_wallet = UserWallet::where(['user_id' => auth()->user()->id, 'currency_id' => $escrow->escrowCurrency->id])->first();
        return view('user.my-escrow.payment-approval-pending', compact('page_title', 'escrow', 'payment_gateways_currencies', 'user_wallet'));
    }
    //payment approvel submit when seller will send payment request to buyer
    public function paymentApprovalSubmit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_gateway' => 'required',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $validated = $validator->validate();
        $escrow    = Escrow::findOrFail($id);
        if ($escrow->status != EscrowConstants::APPROVAL_PENDING) {
            return redirect()->back()->with(['error' => ['Something went wrong']]);
        }
        if ($validated['payment_gateway'] == "myWallet") {
            $sender_currency = Currency::where('code', $escrow->escrow_currency)->first();
            $user_wallet     = UserWallet::where(['user_id' => auth()->user()->id, 'currency_id' => $sender_currency->id])->first();
            //buyer amount calculation
            $amount = $escrow->amount;
            if ($escrow->role == EscrowConstants::SELLER_TYPE && $escrow->who_will_pay == EscrowConstants::ME) {
                $buyer_amount = $amount;
            } else if ($escrow->role == EscrowConstants::SELLER_TYPE && $escrow->who_will_pay == EscrowConstants::BUYER) {
                $buyer_amount = $amount + $escrow->escrowDetails->fee;
            } else if ($escrow->role == EscrowConstants::SELLER_TYPE && $escrow->who_will_pay == EscrowConstants::HALF) {
                $buyer_amount = $amount + ($escrow->escrowDetails->fee / 2);
            }
            if ($user_wallet->balance == 0 || $user_wallet->balance < 0 || $user_wallet->balance < $buyer_amount) {
                return redirect()->back()->with(['error' => ['Insuficiant Balance']]);
            }
            $this->escrowWalletPayment($escrow);
            $escrow->payment_type = EscrowConstants::MY_WALLET;
            $escrow->status       = EscrowConstants::ONGOING;
        } else {
            $payment_gateways_currencies = PaymentGatewayCurrency::with('gateway')->find($validated['payment_gateway']);
            if (!$payment_gateways_currencies || !$payment_gateways_currencies->gateway) {
                return redirect()->back()->withInput()->with(['error' => [__('Payment gateway not found')]]);
            }
            //buyer amount calculation
            $amount       = $escrow->amount;
            $eschangeRate = (1 / $escrow->escrowCurrency->rate) * $payment_gateways_currencies->rate;
            if ($escrow->role == EscrowConstants::SELLER_TYPE && $escrow->who_will_pay == EscrowConstants::ME) {
                $buyer_amount = $amount * $eschangeRate;
            } else if ($escrow->role == EscrowConstants::SELLER_TYPE && $escrow->who_will_pay == EscrowConstants::BUYER) {
                $total_amount = $amount + $escrow->escrowDetails->fee;
                $buyer_amount = $total_amount * $eschangeRate;
            } else if ($escrow->role == EscrowConstants::SELLER_TYPE && $escrow->who_will_pay == EscrowConstants::HALF) {
                $total_amount = $amount + ($escrow->escrowDetails->fee / 2);
                $buyer_amount = $total_amount * $eschangeRate;
            }

            $identifier = generate_unique_string("escrows", "escrow_id", 16);
            $tempData = (object)[
                'data'   => (object)[
                    'escrow' => (object)[
                        'escrow_id'    => $escrow->id,
                        'escrow_trx'    => $escrow->escrow_id,
                        'escrow_currency'    => $escrow->escrow_currency,
                        'eschangeRate' => $eschangeRate,
                        'buyer_amount' => $buyer_amount,
                    ],
                    'trx'    => $escrow->escrow_id,
                    'identifier'    => $identifier,
                    'gateway_currency' => $payment_gateways_currencies ?? null,
                    'payment_type'     => "approvalPending",
                    'creator_id'       => auth()->guard(get_auth_guard())->user()->id,   //for sscommerz relogin after payment
                    'creator_guard'    => get_auth_guard(),                              //for sscommerz relogin after payment
                ]
            ];

            $this->addEscrowTempData($identifier, $tempData->data);
            Session::put('identifier', $identifier);
            $instance = EscrowPaymentGateway::init($tempData)->gateway();
            return $instance;
        }
        DB::beginTransaction();
        try {
            $escrow->save();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('Payment successfully completed')]]);
    }
    //escrow wallet payment
    public function escrowWalletPayment($escrowData)
    {
        $sender_currency = Currency::where('code', $escrowData->escrow_currency)->first();
        $user_wallet     = UserWallet::where(['user_id' => auth()->user()->id, 'currency_id' => $sender_currency->id])->first();
        //buyer amount calculation
        $amount = $escrowData->amount;
        if ($escrowData->role == EscrowConstants::SELLER_TYPE && $escrowData->who_will_pay == EscrowConstants::ME) {
            $buyer_amount = $amount;
        } else if ($escrowData->role == EscrowConstants::SELLER_TYPE && $escrowData->who_will_pay == EscrowConstants::BUYER) {
            $buyer_amount = $amount + $escrowData->escrowDetails->fee;
        } else if ($escrowData->role == EscrowConstants::SELLER_TYPE && $escrowData->who_will_pay == EscrowConstants::HALF) {
            $buyer_amount = $amount + ($escrowData->escrowDetails->fee / 2);
        }
        $user_wallet->balance -= $buyer_amount;
        $escrowData->escrowDetails->buyer_pay = $buyer_amount;

        DB::beginTransaction();
        try {
            $user_wallet->save();
            $escrowData->escrowDetails->save();
            DB::commit();
            $this->approvelNotificationSend($escrowData->user, $escrowData);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    //escrow temp data insert
    public function addEscrowTempData($identifier, $data)
    {
        return TemporaryData::create([
            'type'       => "add-escrow",
            'identifier' => $identifier,
            'data'       => $data,
        ]);
    }
    //payment approval success
    public function escrowPaymentApprovalSuccess()
    {
        $identifier    = session()->get('identifier');
        $tempData      = TemporaryData::where("identifier", $identifier)->first();
        if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $escrow        = Escrow::findOrFail($tempData->data->escrow->escrow_id);
        $escrowDetails = EscrowDetails::where('escrow_id', $escrow->id)->first();
        $escrow->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
        $escrow->payment_type                = EscrowConstants::GATEWAY;
        $escrow->status                      = EscrowConstants::ONGOING;
        $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
        $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;
        DB::beginTransaction();
        try {
            $escrow->save();
            $escrowDetails->save();
            DB::commit();
            $this->approvelNotificationSend($escrow->user, $escrow);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('Payment successfully completed.')]]);
    }

    public function cancel(Request $request)
    {
        return redirect()->route('user.my-escrow.index')->with(['error' => [__('You have canceled the payment')]]);
    }
    public function escrowPaymentApprovalSuccessflutterWave()
    {
        $status = request()->status;
        //if payment is successful
        if ($status ==  'successful' || $status ==  'completed') {
            $identifier    = session()->get('identifier');
            $tempData      = TemporaryData::where("identifier", $identifier)->first();
            if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
            $escrow        = Escrow::findOrFail($tempData->data->escrow->escrow_id);
            $escrowDetails = EscrowDetails::where('escrow_id', $escrow->id)->first();
            $escrow->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
            $escrow->payment_type                = EscrowConstants::GATEWAY;
            $escrow->status                      = EscrowConstants::ONGOING;
            $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
            $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;
            DB::beginTransaction();
            try {
                $escrow->save();
                $escrowDetails->save();
                DB::commit();
                $this->approvelNotificationSend($escrow->user, $escrow);
            } catch (Exception $e) {
                DB::rollBack();
                throw new Exception($e->getMessage());
            }
            return redirect()->route('user.my-escrow.index')->with(['success' => [__('Payment successfully completed')]]);
        } else {
            return redirect()->route('user.my-escrow.index')->with(['error' => [__('Payment Canceled')]]);
        }
    }
    //payment approval success
    public function escrowPaymentApprovalSuccessSslcommerz(Request $request)
    {
        $tempData      = TemporaryData::where("identifier", $request->tran_id)->first();
        if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $creator_id    = $tempData->data->creator_id ?? null;
        $creator_guard = $tempData->data->creator_guard ?? null;
        $user          = Auth::guard($creator_guard)->loginUsingId($creator_id);
        $escrow        = Escrow::findOrFail($tempData->data->escrow->escrow_id);
        $escrowDetails = EscrowDetails::where('escrow_id', $escrow->id)->first();
        $escrow->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
        $escrow->payment_type                = EscrowConstants::GATEWAY;
        $escrow->status                      = EscrowConstants::ONGOING;
        $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
        $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;
        DB::beginTransaction();
        try {
            $escrow->save();
            $escrowDetails->save();
            DB::commit();
            $this->approvelNotificationSend($escrow->user, $escrow);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('Payment successfully completed')]]);
    }
    public function escrowPaymentApprovalSuccessCoingate(Request $request)
    {
        $callback_data = $request->all();
        $callback_status = $callback_data['status'] ?? "";
        $tempData   = TemporaryData::where("identifier", $request->get('trx'))->first();
        if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);

        $escrow = Escrow::where('callback_ref', $request->get('trx'))->first();

        if ($escrow->status == EscrowConstants::APPROVAL_PENDING) {
            $escrow->status = EscrowConstants::PAYMENT_PENDING;
        }

        $escrowDetails = EscrowDetails::where('escrow_id', $escrow->id)->first();
        $escrow->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
        $escrow->payment_type                = EscrowConstants::GATEWAY;

        $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
        $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;
        DB::beginTransaction();
        try {
            $escrow->save();
            $escrowDetails->save();
            DB::commit();
            $this->approvelNotificationSend($escrow->user, $escrow);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('Payment successfully completed.')]]);
    }
    public function escrowPaymentApprovalCallbackCoingate(Request $request)
    {
        $callback_data = $request->all();
        $callback_status = $callback_data['status'] ?? "";
        $tempData   = TemporaryData::where("identifier", $request->get('trx'))->first();
        $escrowData = Escrow::where('callback_ref', $request->get('trx'))->first();
        $escrowDetails = EscrowDetails::where('escrow_id', $escrowData->id)->first();
        if ($escrowData != null) { // if transaction already created & status is not success
            // Just update transaction status and update user wallet if needed
            if ($callback_status == "paid") {
                $escrowData->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
                $escrowData->payment_type                = EscrowConstants::GATEWAY;
                $escrowData->status                      = EscrowConstants::ONGOING;
                $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
                $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;
                DB::beginTransaction();
                try {
                    $escrowData->save();
                    $escrowDetails->save();
                    DB::commit();
                    $this->approvelNotificationSend($escrowData->user, $escrowData);
                } catch (Exception $e) {
                    DB::rollBack();
                    logger($e->getMessage());
                }
            }
        } else { // need to create transaction and update status if needed
            $status = EscrowConstants::PAYMENT_PENDING;
            if ($callback_status == "paid") {
                $status = EscrowConstants::ONGOING;
            }
            $escrowData->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
            $escrowData->payment_type                = EscrowConstants::GATEWAY;
            $escrowData->status                      = $status;
            $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
            $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;
            DB::beginTransaction();
            try {
                $escrowData->save();
                $escrowDetails->save();
                DB::commit();
                $this->approvelNotificationSend($escrowData->user, $escrowData);
            } catch (Exception $e) {
                DB::rollBack();
                logger($e->getMessage());
            }
        }
        logger("Escrow Created Successfully ::" . $callback_data['status']);
    }
    //approval manual payment
    public function manualPaymentPrivew()
    {
        $tempData = Session::get('identifier');
        $oldData  = TemporaryData::where('identifier', $tempData)->first();
        $escrow     = Escrow::findOrFail($oldData->data->escrow->escrow_id);
        $gateway    = PaymentGateway::manual()->where('slug', PaymentGatewayConst::add_money_slug())->where('id', $oldData->data->gateway_currency->gateway->id)->first();
        $page_title = "Manual Payment" . ' ( ' . $gateway->name . ' )';
        if (!$oldData) {
            return redirect()->route('user.my-escrow.index');
        }
        return view('user.my-escrow.manual-payment-approval', compact("page_title", "oldData", "escrow", 'gateway'));
    }
    public function manualPaymentConfirm(Request $request)
    {
        $tempData       = Session::get('identifier');
        $oldData        = TemporaryData::where('identifier', $tempData)->first();
        $escrow         = Escrow::findOrFail($oldData->data->escrow->escrow_id);
        $escrowDetails  = EscrowDetails::where('escrow_id', $escrow->id)->first();
        $gateway        = PaymentGateway::manual()->where('slug', PaymentGatewayConst::add_money_slug())->where('id', $oldData->data->gateway_currency->gateway->id)->first();
        $payment_fields = $gateway->input_fields ?? [];
        $validation_rules       = $this->generateValidationRules($payment_fields);
        $payment_field_validate = Validator::make($request->all(), $validation_rules)->validate();
        $get_values             = $this->placeValueWithFields($payment_fields, $payment_field_validate);
        $escrow->payment_gateway_currency_id = $oldData->data->gateway_currency->id;
        $escrow->payment_type                = EscrowConstants::GATEWAY;
        $escrow->details                     = json_encode($get_values);
        $escrow->status                      = EscrowConstants::PAYMENT_PENDING;
        $escrowDetails->buyer_pay             = $oldData->data->escrow->buyer_amount;
        $escrowDetails->gateway_exchange_rate = $oldData->data->escrow->eschangeRate;
        DB::beginTransaction();
        try {
            $escrow->save();
            $escrowDetails->save();
            DB::commit();
            $this->approvelNotificationSend($escrow->user, $escrow);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('Payment successfully completed')]]);
    }
    public function razorCallback()
    {
        $identifier    = session()->get('identifier');
        $tempData      = TemporaryData::where("identifier", $identifier)->first();
        if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $escrow        = Escrow::findOrFail($tempData->data->escrow->escrow_id);
        $escrowDetails = EscrowDetails::where('escrow_id', $escrow->id)->first();
        $escrow->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
        $escrow->payment_type                = EscrowConstants::GATEWAY;
        $escrow->status                      = EscrowConstants::ONGOING;
        $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
        $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;
        DB::beginTransaction();
        try {
            $escrow->save();
            $escrowDetails->save();
            DB::commit();
            $this->approvelNotificationSend($escrow->user, $escrow);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('Payment successfully completed')]]);
    }
    //escrow approvel payment mail send
    public function approvelNotificationSend($user, $escrow)
    {
        $notification_content = [
            'title'   => "Escrow Approvel Payment",
            'message' => "A user has paid your escrow",
            'time'    => Carbon::now()->diffForHumans(),
            'image'   => files_asset_path('profile-default'),
        ];
        UserNotification::create([
            'type'    => NotificationConst::ESCROW_CREATE,
            'user_id' => $user->id,
            'message' => $notification_content,
        ]);
        //Push Notifications
        $basic_setting = BasicSettings::first();
        try {
            $user->notify(new EscrowApprovel($user, $escrow));
            if ($basic_setting->push_notification == true) {
                (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
            }
        } catch (Exception $e) {
        }
    }
    //escrow conversation
    public function escrowConversation($escrow_id)
    {
        $page_title    = "Escrow Conversation";
        $escrow        = Escrow::with('conversations')->findOrFail(decrypt($escrow_id));
        $conversations = EscrowChat::where(['escrow_id' => $escrow->id, 'seen' => 0])->where('sender', '!=', auth()->user()->id)->update(['seen' => 1]);
        return view('user.my-escrow.conversation', compact('page_title', 'escrow'));
    }
    //escrow conversation message send
    public function messageSend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'escrow_id' => 'required|string',
            'message'   => 'nullable|string|max:2000',
            'file'   => 'nullable',
        ]);
        if ($validator->fails()) {
            $error = ['error' => "Something Went Wrong."];
            return Response::error($error, null, 400);
        }
        $validated = $validator->validate();

        if (!isset($validated['file']) && $validated['message'] == null) {
            $error = ['error' => ["You didn't write any message."]];
            return Response::error($error, null, 400);
        }
        $escrow = Escrow::findOrFail($validated['escrow_id']);
        if (!$escrow) return Response::error(['error' => [__('This escrow is closed')]]);
        $data = [
            'escrow_id'     => $escrow->id,
            'sender'        => auth()->user()->id,
            'sender_type'   => "USER",
            'message'       => $validated['message'],
            'receiver_type' => "USER",
        ];
        try {
            $chat_data = EscrowChat::create($data);
            if (isset($validated['file'])) {
                $chat_attachments = [
                    'escrow_chat_id'  => $chat_data->id,
                    'attachment'      => $validated['file']['attachment'],
                    'attachment_info' => json_decode($validated['file']['attachment_info']),
                    'created_at'      => now(),
                ];
                EscrowConversationAttachment::create($chat_attachments);
            }
        } catch (Exception $e) {
            // return $e;
            $error = ['error' => [__('Message Sending faild! Please try again')]];
            return Response::error($error, null, 500);
        }
        try {
            event(new EscrowConversationEvent($escrow, $chat_data));
        } catch (Exception $e) {
            return $e;
            $error = ['error' => [__('SMS Sending faild! Please try again')]];
            return Response::error($error, null, 500);
        }
    }
    public function chatFileUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:jpg,jpeg,png,pdf,doc,xls,xlsx|max:2048'
        ]);
        if ($validator->fails()) {
            $error = ['error' => ["This file is not supported."]];
            return Response::error($error, null, 400);
        }
        if ($request->file()) {
            $upload_file = upload_file($request->file('file'), 'escrow-conversation');
            $upload_image = upload_files_from_path_dynamic([$upload_file['dev_path']], 'escrow-conversation');
            chmod($upload_file['dev_path'], 0644);
            $file_type = explode("/", $upload_file['type'])[0];
            if ($file_type == "image") {
                delete_file($upload_file['dev_path']);
            }

            $data = [
                'attachment'      =>  $upload_image,
                'attachment_info' => json_encode($upload_file),
            ];
            $message = ['success' => [__('File Uploaded Success')]];
            return Helpers::success($message, $data);
        }
    }
    //dispute payment
    public function disputePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target' => 'required',
        ]);
        $validated = $validator->validate();
        $escrow    = Escrow::findOrFail($validated['target']);
        $user      = User::findOrFail($escrow->user_id == auth()->user()->id ? $escrow->buyer_or_seller_id : $escrow->user_id);
        //status check
        if ($escrow->status != EscrowConstants::ONGOING) {
            return redirect()->back()->with(['error' => [__('Something went wrong')]]);
        }
        $escrow->status = EscrowConstants::ACTIVE_DISPUTE;
        try {
            $escrow->save();
            DB::commit();
            //send user notification
            $notification_content = [
                'title'   => "Payment Disputed",
                'message' => "A user dispute the payment",
                'time'    => Carbon::now()->diffForHumans(),
                'image'   => files_asset_path('profile-default'),
            ];
            UserNotification::create([
                'type'    => NotificationConst::ESCROW_CREATE,
                'user_id' => $user->id,
                'message' => $notification_content,
            ]);
            //Push Notifications
            $basic_setting = BasicSettings::first();
            try {
                $user->notify(new EscrowDisputePayment($user, $escrow));
                if ($basic_setting->push_notification == true) {
                    (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }
            } catch (Exception $e) {
            }
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('You have request to dispute this escrow successfully')]]);
    }
    //release payment to seller
    public function releasePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target' => 'required',
        ]);
        $validated = $validator->validate();
        $escrow    = Escrow::findOrFail($validated['target']);
        $user      = User::findOrFail($escrow->user_id == auth()->user()->id ? $escrow->buyer_or_seller_id : $escrow->user_id);
        //status check
        if ($escrow->status != EscrowConstants::ONGOING) {
            return redirect()->back()->with(['error' => [__('Something went wrong')]]);
        }
        //select wallet id
        $wallet_user_id = $escrow->user_id == auth()->user()->id && $escrow->role == "buyer" ? $escrow->buyer_or_seller_id : $escrow->user_id;
        $user_wallet           = UserWallet::where('user_id', $wallet_user_id)->where('currency_id', $escrow->escrowCurrency->id)->first();
        if (empty($user_wallet)) return redirect()->back()->with(['error' => [__('Seller Wallet not found')]]);
        $user_wallet->balance += $escrow->escrowDetails->seller_get;

        $escrow->status = EscrowConstants::RELEASED;
        DB::beginTransaction();
        try {
            $user_wallet->save();
            $escrow->save();

            DB::commit();
            //send user notification
            $notification_content = [
                'title'   => "Payment Released",
                'message' => "A buyer released your payment",
                'time'    => Carbon::now()->diffForHumans(),
                'image'   => files_asset_path('profile-default'),
            ];
            UserNotification::create([
                'type'    => NotificationConst::ESCROW_CREATE,
                'user_id' => $user->id,
                'message' => $notification_content,
            ]);
            //Push Notifications
            $basic_setting = BasicSettings::first();
            try {
                $user->notify(new EscrowReleased($user, $escrow));
                if ($basic_setting->push_notification == true) {
                    (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }
            } catch (Exception $e) {
            }
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('You have successfully released payment')]]);
    }
    //send payment release request to buyer
    public function releaseRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target' => 'required',
        ]);
        $validated = $validator->validate();
        $escrow    = Escrow::findOrFail($validated['target']);
        $user      = User::findOrFail($escrow->user_id == auth()->user()->id ? $escrow->buyer_or_seller_id : $escrow->user_id);
        try {
            //send user notification
            $notification_content = [
                'title'   => "Release Request",
                'message' => "A seller is requested to release there payment",
                'time'    => Carbon::now()->diffForHumans(),
                'image'   => files_asset_path('profile-default'),
            ];
            UserNotification::create([
                'type'    => NotificationConst::ESCROW_CREATE,
                'user_id' => $user->id,
                'message' => $notification_content,
            ]);
            //Push Notifications
            $basic_setting = BasicSettings::first();
            try {
                $user->notify(new EscrowReleasedRequest($user, $escrow));
                if ($basic_setting->push_notification == true) {
                   (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }
            } catch (Exception $e) {
            }
        } catch (Exception $e) {
            return back()->with(['error' => [__('Something went wrong! Please try again')]]);
        }
        return back()->with(['success' => [__('Email send successfully!')]]);
    }
    //escrow sslcommerz fail
    public function escrowSllCommerzFails(Request $request)
    {
        $tempData = TemporaryData::where("identifier", $request->tran_id)->first();
        if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $creator_id    = $tempData->data->creator_id ?? null;
        $creator_guard = $tempData->data->creator_guard ?? null;
        $user          = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if ($request->status == "FAILED") {
            TemporaryData::destroy($tempData->id);
            return redirect()->route("user.my-escrow.index")->with(['error' => [__('Escrow Create Failed')]]);
        }
    }
    //escrow sslcommerz cancel
    public function escrowSllCommerzCancel(Request $request)
    {
        $tempData = TemporaryData::where("identifier", $request->tran_id)->first();
        if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $creator_id    = $tempData->data->creator_id ?? null;
        $creator_guard = $tempData->data->creator_guard ?? null;
        $user          = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if ($request->status == "FAILED") {
            TemporaryData::destroy($tempData->id);
            return redirect()->route("user.my-escrow.index")->with(['error' => [__('Escrow Create Cancel')]]);
        }
    }
    public function cryptoPaymentAddress(Request $request, $escrow_id)
    {
        $page_title = "Crypto Payment Address";
        $escrowData = Escrow::where('escrow_id', $escrow_id)->first();

        return view('user.my-escrow.payment.crypto.approval-address', compact(
            'page_title',
            'escrowData',
        ));
    }
    public function cryptoPaymentConfirm(Request $request, $escrow_id)
    {
        $escrowData = Escrow::where('escrow_id', $escrow_id)->first();

        $dy_input_fields = $escrowData->details->payment_info->requirements ?? [];
        $validation_rules = $this->generateValidationRules($dy_input_fields);

        $validated = [];
        if (count($validation_rules) > 0) {
            $validated = Validator::make($request->all(), $validation_rules)->validate();
        }

        if (!isset($validated['txn_hash'])) return back()->with(['error' => ['Transaction hash is required for verify']]);

        $receiver_address = $escrowData->details->payment_info->receiver_address ?? "";


        // check hash is valid or not
        $crypto_transaction = CryptoTransaction::where('txn_hash', $validated['txn_hash'])
            ->where('receiver_address', $receiver_address)
            ->where('asset', $escrowData->paymentGatewayCurrency->currency_code)
            ->where(function ($query) {
                return $query->where('transaction_type', "Native")
                    ->orWhere('transaction_type', "native");
            })
            ->where('status', PaymentGatewayConst::NOT_USED)
            ->first();

        if (!$crypto_transaction) return back()->with(['error' => ['Transaction hash is not valid! Please input a valid hash']]);

        if ($crypto_transaction->amount >= $escrowData->escrowDetails->buyer_pay == false) {
            if (!$crypto_transaction) return back()->with(['error' => ['Insufficient amount added. Please contact with system administrator']]);
        }

        DB::beginTransaction();
        try {

            // update crypto transaction as used
            DB::table($crypto_transaction->getTable())->where('id', $crypto_transaction->id)->update([
                'status'        => PaymentGatewayConst::USED,
            ]);

            // update transaction status
            $transaction_details = json_decode(json_encode($escrowData->details), true);
            $transaction_details['payment_info']['txn_hash'] = $validated['txn_hash'];

            DB::table($escrowData->getTable())->where('id', $escrowData->id)->update([
                'details'       => $transaction_details,
                'status'        => EscrowConstants::ONGOING,
                'payment_type'        => EscrowConstants::GATEWAY,
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return back()->with(['success' => ['Payment Confirmation Success!']]);
    }
    public function redirectBtnPay(Request $request, $gateway)
    {
        try {
            return EscrowPaymentGateway::init([])->handleBtnPay($gateway, $request->all());
        } catch (Exception $e) {
            return redirect()->route('user.my-escrow.index')->with(['error' => [$e->getMessage()]]);
        }
    }
    public function escrowPaymentSuccessRazorpayPost(Request $request, $gateway)
    {
        $tempData      = TemporaryData::where("identifier", $request->token)->first();
        if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $escrow        = Escrow::findOrFail($tempData->data->escrow->escrow_id);
        $escrowDetails = EscrowDetails::where('escrow_id', $escrow->id)->first();
        $escrow->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
        $escrow->payment_type                = EscrowConstants::GATEWAY;
        $escrow->status                      = EscrowConstants::ONGOING;
        $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
        $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;
        DB::beginTransaction();
        try {
            $escrow->save();
            $escrowDetails->save();
            DB::commit();
            $this->approvelNotificationSend($escrow->user, $escrow);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return redirect()->route('user.my-escrow.index')->with(['success' => [__('Payment successfully completed.')]]);
    }



    /**
     * Method for view authorize card view page
     * @param Illuminate\Http\Request $request,$identifier
     */
    public function authorizeCardInfo(Request $request, $identifier)
    {
        $page_title         = "Authorize card infomation";
        $tempData          = TemporaryData::where('identifier', $identifier)->first();
        $escrow        = Escrow::findOrFail($tempData->data->escrow->escrow_id);
        $escrowDetails = EscrowDetails::where('escrow_id', $escrow->id)->first();

        $tempData = $tempData->data;
        return view('user.my-escrow.authorize-payment-approval', compact(
            'page_title',
            'escrow',
            'escrowDetails',
            'tempData'
        ));
    }
    /**
     * Method function authorize payment submit
     * @param Illuminate\Http\Request $request, $identifier
     */
    public function authorizePaymentSubmit(Request $request, $identifier)
    {
        $validator          = Validator::make($request->all(), [
            'card_number'   => 'required',
            'date'          => 'required',
            'code'          => 'required'
        ]);
        if ($validator->fails()) return back()->withErrors($validator)->withInput($request->all());
        $validated          = $validator->validate();

        $tempData          = TemporaryData::where('identifier', $identifier)->first();
        if (!$tempData) return redirect()->route('user.my-escrow.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);
        $escrow        = Escrow::findOrFail($tempData->data->escrow->escrow_id);
        $escrowDetails = EscrowDetails::where('escrow_id', $escrow->id)->first();
        $escrow->payment_gateway_currency_id = $tempData->data->gateway_currency->id;
        $escrow->payment_type                = EscrowConstants::GATEWAY;
        $escrow->status                      = EscrowConstants::ONGOING;
        $escrowDetails->buyer_pay             = $tempData->data->escrow->buyer_amount;
        $escrowDetails->gateway_exchange_rate = $tempData->data->escrow->eschangeRate;


        $gateway_credentials          = $this->authorizeCredentials($tempData);
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
        $amount = round($tempData->data->escrow->buyer_amount, 6);


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

        $make_customer_id       = auth()->user()->id . $tempData->data->gateway_currency->gateway->code;
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
                    DB::beginTransaction();
                    try {
                        $escrow->save();
                        $escrowDetails->save();
                        DB::commit();
                        $this->approvelNotificationSend($escrow->user, $escrow);
                    } catch (Exception $e) {
                        DB::rollBack();
                        throw new Exception($e->getMessage());
                    }

                    return redirect()->route('user.my-escrow.index')->with(['success' => ['Payment successfully completed.']]);
                } else {
                    return redirect()->route('user.my-escrow.index')->with(['error' => ['Transaction Failed']]);
                    if ($tresponse->getErrors() != null) {
                        return redirect()->route('user.my-escrow.index')->with(['error' => [$tresponse->getErrors()[0]->getErrorText()]]);
                    }
                }
            } else {
                return redirect()->route('user.my-escrow.index')->with(['error' => ['Transaction Failed']]);
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getErrors() != null) {
                    return redirect()->route('user.my-escrow.index')->with(['error' => [$tresponse->getErrors()[0]->getErrorText()]]);
                } else {
                    return redirect()->route('user.my-escrow.index')->with(['error' => [$response->getMessages()->getMessage()[0]->getText()]]);
                }
            }
        } else {
            return redirect()->route('user.my-escrow.index')->with(['error' => ['No response returned']]);
        }
    }
    // For get the gateway credentials
    function authorizeCredentials($temp_data)
    {
        $gateway             = $temp_data->data->gateway_currency->gateway;
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
}
