<?php
namespace App\Traits\PaymentGateway;

use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\PaymentGateway;
use App\Constants\PaymentGatewayConst;

trait Authorize{

    public function authorizeInit($output = null){
        if(!$output) $output = $this->output;
        if($output['type'] === PaymentGatewayConst::TYPEADDMONEY){
            return $this->setupAuthorizeInitAddMoney($output);
        }
    }

    public function setupAuthorizeInitAddMoney($output){ 
        $junk_data = $this->authorizeJunkInsert();
        
        if(request()->expectsJson()) {
            $this->output['redirection_response'] = [];
            $this->output['redirect_links']       = [];
            $this->output['gateway_alias']        = "authorize";
            $this->output['identifier']           = $junk_data->identifier ?? '';
            $this->output['redirect_url']         = route('api.v1.add-money.authorize.payment.submit');

            return $this->get();
        }
        return redirect()->route('user.add.money.authorize.card.info',$junk_data->identifier);
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
    public function authorizeInitApi($output = null){
        
        if(!$output) $output = $this->output;
        if($output['type'] === PaymentGatewayConst::TYPEADDMONEY){ 
            return $this->setupAuthorizeInitAddMoney($output);
        }
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
