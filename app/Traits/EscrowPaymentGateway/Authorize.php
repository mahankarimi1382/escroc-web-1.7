<?php
namespace App\Traits\EscrowPaymentGateway;

use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\PaymentGateway;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\Api\Helpers as ApiResponse;

trait Authorize{

    public function authorizeInit($escrow_data = null){
        if(!$escrow_data) $escrow_data = $this->request_data->data;
        
        return $this->setupAuthorizeInitEscrow($escrow_data);
       
    }

    public function setupAuthorizeInitEscrow($escrow_data){
        //for api payment
        if(request()->expectsJson()) {
            if (isset($escrow_data->payment_type) && $escrow_data->payment_type == "approvalPending") {
                 $url = route('api.v1.api-escrow-action.authorize.payment.submit');
            }else {
                $url = route('api.v1.my-escrow.authorize.payment.submit');
              
            }
            $payment_informations = [
                'trx'                   => $escrow_data->trx,
                'gateway_currency_name' => $escrow_data->gateway_currency->name,
                'request_amount'        => get_amount($escrow_data->escrow->amount, $escrow_data->escrow->escrow_currency),
                'exchange_rate'         => "1".' '.$escrow_data->escrow->escrow_currency.' = '. get_amount($escrow_data->escrow->gateway_exchange_rate, $escrow_data->escrow->gateway_currency),
                'total_charge'          => get_amount($escrow_data->escrow->escrow_total_charge, $escrow_data->escrow->escrow_currency),
                'charge_payer'          => $escrow_data->escrow->charge_payer,
                'seller_get'            => get_amount($escrow_data->escrow->seller_amount, $escrow_data->escrow->escrow_currency),
                'payable_amount'        => get_amount($escrow_data->escrow->buyer_amount, $escrow_data->escrow->gateway_currency),
            ];
            $data =[
                'gategay_type'          => $escrow_data->gateway_currency->gateway->type,
                'gateway_currency_name' => $escrow_data->gateway_currency->name,
                'alias'                 => $escrow_data->gateway_currency->alias,
                'identify'              => $escrow_data->gateway_currency->gateway->name,
                'payment_informations'  => $payment_informations,
                'url'                   => $url,
                'method'                => "post",
            ];
            $message = ['success'=>['Escrow Payment Gateway Captured Successful']];
            return ApiResponse::success($message, $data);
            
        }
        //for web payment
        
        if (isset($escrow_data->payment_type) && $escrow_data->payment_type == "approvalPending") {
            return redirect()->route('user.escrow-action.authorize.card.info',$escrow_data->identifier);
        }else {
            return redirect()->route('user.my-escrow.authorize.card.info.web',$escrow_data->trx);
        }
        
    }
    public function authorizeJunkInsert() {
        $output = $this->output;
        $temp_record_token = generate_unique_string('temporary_datas', 'identifier', 60);

        $data = [
            'gateway'       => $output['gateway']->id,
            'currency'      => $output['gateway_currency']->id,
            'amount'        => json_decode(json_encode($output['amount']),true),
            'wallet_table'  => $output['wallet']->getTable(),
            'wallet_id'     => $output['wallet']->id,
            'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard' => get_auth_guard(),
        ];

        return TemporaryData::create([
            'type'          => PaymentGatewayConst::TYPEADDMONEY,
            'identifier'    => $temp_record_token,
            'data'          => $data,
        ]);
    }
    public function authorizeInitApi($escrow_data = null){
        if(!$escrow_data) $escrow_data = $this->request_data->data;
        return $this->setupAuthorizeInitEscrow($escrow_data);
    }
     // For get the gateway credentials
    function authorizeCredentials($temp_data){
        $gateway             = PaymentGateway::where('id',$temp_data->data->gateway)->first() ?? null;
        if(!$gateway) throw new Exception(__("Payment gateway not available"));
        $credentials         = $gateway->credentials;
        $app_login_id        = getPaymentCredentials($credentials,'App Login ID');
        $transaction_key     = getPaymentCredentials($credentials,'Transaction Key');
        $signature_key       = getPaymentCredentials($credentials,'Signature Key');

        $mode           = $gateway->env;

        $authorize_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "live",
        ];
        if(array_key_exists($mode,$authorize_register_mode)) {
            $mode = $authorize_register_mode[$mode];
        }else {
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
    public function authorizeSuccess($output) {
        $output['capture']      = $output['tempData']['data']->response ?? "";

        // need to insert new transaction in database
        try{
            $this->createTransaction($output);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    public function isAuthorize($gateway)
    {
        $search_keyword = ['authorize','authorize gateway','gateway authorize','authorize payment gateway'];
        $gateway_name = $gateway->name;

        $search_text = Str::lower($gateway_name);
        $search_text = preg_replace("/[^A-Za-z0-9]/","",$search_text);
        foreach($search_keyword as $keyword) {
            $keyword = Str::lower($keyword);
            $keyword = preg_replace("/[^A-Za-z0-9]/","",$keyword);
            if($keyword == $search_text) {
                return true;
                break;
            }
        }
        return false;
    }
}

?>
