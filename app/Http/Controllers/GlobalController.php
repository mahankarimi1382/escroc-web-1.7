<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Jenssegers\Agent\Facades\Agent;
use App\Constants\PaymentGatewayConst;

class GlobalController extends Controller
{

    /**
     * Funtion for get state under a country
     * @param country_id
     * @return json $state list
     */
    public function getStates(Request $request) {
        $request->validate([
            'country_id' => 'required|integer',
        ]);
        $country_id = $request->country_id;
        // Get All States From Country
        $country_states = get_country_states($country_id);
        return response()->json($country_states,200);
    }


    public function getCities(Request $request) {
        $request->validate([
            'state_id' => 'required|integer',
        ]);

        $state_id = $request->state_id;
        $state_cities = get_state_cities($state_id);

        return response()->json($state_cities,200);
    }


    public function getCountries(Request $request) {
        $countries = get_all_countries();

        return response()->json($countries,200);
    }


    public function getTimezones(Request $request) {
        $timeZones = get_all_timezones();

        return response()->json($timeZones,200);
    }
    public function setCookie(Request $request){
        $userAgent = $request->header('User-Agent');
        $cookie_status = $request->type;
        if($cookie_status == 'allow'){
            $response_message = __("Cookie Allowed Success");
            $expirationTime = 2147483647; //Maximum Unix timestamp.
        }else{
            $response_message = __("Cookie Declined");
            $expirationTime = Carbon::now()->addHours(24)->timestamp;// Set the expiration time to 24 hours from now.
        }
        $browser = Agent::browser();
        $platform = Agent::platform();
        $ipAddress = $request->ip();
        return response($response_message)->cookie('approval_status', $cookie_status,$expirationTime)
                                            ->cookie('user_agent', $userAgent,$expirationTime)
                                            ->cookie('ip_address', $ipAddress,$expirationTime)
                                            ->cookie('browser', $browser,$expirationTime)
                                            ->cookie('platform', $platform,$expirationTime);
    }

    public function webHookResponse(Request $request){
        $response_data = $request->all();
        $transaction = Transaction::where('callback_ref',$response_data['data']['reference'])->first();

        $update_temp_data = json_decode(json_encode($transaction->details),true);
        $update_temp_data['callback_data']  = $response_data;


        if($response_data['data']['status'] === "SUCCESSFUL" && $transaction->request_amount > $transaction->creator_wallet->balance ){
            $transaction->update([
                'status'    => PaymentGatewayConst::STATUSFAILD,
                'details'   => $update_temp_data,
                'reject_reason'   => "Insufficient Balance In Your Wallet"??null,
                'available_balance' => $transaction->creator_wallet->balance,
            ]);
            logger("Transaction Status: " . PaymentGatewayConst::STATUSFAILD." Reason: "."Insufficient Balance In Your Wallet"??"");

        }elseif($response_data['data']['status'] === "SUCCESSFUL"){
            $reduce_balance = ($transaction->creator_wallet->balance - $transaction->request_amount);
            $transaction->update([
                'status'            => PaymentGatewayConst::STATUSSUCCESS,
                'details'           => $update_temp_data,
                'available_balance' => $reduce_balance,
            ]);

            $transaction->creator_wallet->update([
                'balance'   => $reduce_balance,
            ]);
            logger("Transaction Status: " . $response_data['data']['status']);
        }elseif($response_data['data']['status'] === "FAILED"){

            $transaction->update([
                'status'    => PaymentGatewayConst::STATUSFAILD,
                'details'   => $update_temp_data,
                'reject_reason'   => $response_data['data']['complete_message']??null,
                'available_balance' => $transaction->creator_wallet->balance,
            ]);
            logger("Transaction Status: " . $response_data['data']['status']." Reason: ".$response_data['data']['complete_message']??"");
        }


    }
}
