<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\UserSupportChat;
use App\Models\UserSupportTicket;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Constants\SupportTicketConst;
use Illuminate\Support\Facades\Validator;
use App\Models\UserSupportTicketAttachment;
use Illuminate\Support\Facades\Notification;
use App\Events\Admin\SupportConversationEvent;
use App\Notifications\Admin\NewUserNotification;
use App\Notifications\Admin\SupportTicketNotification;

class SupportTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "All Ticket";
        $support_tickets = UserSupportTicket::orderByDesc("id")->get();
        return view('admin.sections.support-ticket.index', compact(
            'page_title',
            'support_tickets',
        ));
    }


    /**
     * Display The Pending List of Support Ticket
     * 
     * @return view
     */
    public function pending() {
        $page_title = "Pending Ticket";
        $support_tickets = UserSupportTicket::pending()->orderByDesc("id")->get();
        return view('admin.sections.support-ticket.index', compact(
            'page_title',
            'support_tickets'
        ));
    }


    /**
     * Display The Active List of Support Ticket
     * 
     * @return view
     */
    public function active() {
        $page_title = "Active Ticket";
        $support_tickets = UserSupportTicket::active()->orderByDesc("id")->get();
        return view('admin.sections.support-ticket.index', compact(
            'page_title',
            'support_tickets',
        ));
    }


    /**
     * Display The Solved List of Support Ticket
     * 
     * @return view
     */
    public function solved() {
        $page_title = "Solved Ticket";
        $support_tickets = UserSupportTicket::solved()->orderByDesc("id")->get();
        return view('admin.sections.support-ticket.index', compact(
            'page_title',
            'support_tickets',
        ));
    } 
    public function conversation($encrypt_id) {
        $support_ticket_id = decrypt($encrypt_id);
        $support_ticket = UserSupportTicket::findOrFail($support_ticket_id);
        $page_title = "Support Chat";
        return view('admin.sections.support-ticket.conversation',compact(
            'page_title',
            'support_ticket',
        ));
    } 
    public function messageReply(Request $request) {
        $validator = Validator::make($request->all(),[
            'message'       => 'required|string|max:200',
            'support_token' => 'required|string|exists:user_support_tickets,token',
        ]);
        if($validator->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }
        $validated = $validator->validate();

        $support_ticket = UserSupportTicket::notSolved($validated['support_token'])->first();
        if(!$support_ticket) return Response::error(['error' => ['This support ticket is closed.']]);

        $data = [
            'user_support_ticket_id'    => $support_ticket->id,
            'sender'                    => auth()->user()->id,
            'sender_type'               => "ADMIN",
            'message'                   => $validated['message'],
            'receiver_type'             => "USER",
            'receiver'                  => $support_ticket->user_id,
        ];

        try{
            $chat_data = UserSupportChat::create($data);
        }catch(Exception $e) {
            $error = ['error' => ['SMS Sending faild! Please try again.']];
            return Response::error($error,null,500);
        }

        try{
            event(new SupportConversationEvent($support_ticket,$chat_data));
        }catch(Exception $e) {
            $error = ['error' => ['SMS Sending faild! Please try again.']];
            return Response::error($error,null,500);
        }

        if($support_ticket->status != SupportTicketConst::ACTIVE) {
            try{
                $support_ticket->update([
                    'status'    => SupportTicketConst::ACTIVE,
                ]);
            }catch(Exception $e) {
                $error = ['error' => ['Failed to change status to active!']];
                return Response::error($error,null,500);
            }
        }
    } 
    public function solve(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'    => 'required|string|exists:user_support_tickets,token',  
        ]);
        $validated = $validator->validate();

        $support_ticket = UserSupportTicket::where("token",$validated['target'])->first();
        if($support_ticket->status == SupportTicketConst::SOLVED) return back()->with(['warning' => ['This ticket is already solved!']]);

        try{
            $support_ticket->update([
                'status'        => SupportTicketConst::SOLVED,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return back()->with(['success' => ['Success']]);
    }

         /**
     * Method for view create support ticket page
     * @return view
     */
    public function create(){
        $page_title         = "Create Support Ticket";

        return view('admin.sections.support-ticket.create',compact(
            'page_title'
        ));
    }
    /**
     * Method for check user
     * @param Illuminate\Http\Request $request
     */
    public function checkUser(Request $request){
        $validator      = Validator::make($request->all(),[
            'email'     => 'required|email'
        ]);
        $validated      = $validator->validate();
        $user['data']   = User::where('email',$validated['email'])->first();
        if(!$user['data']) return response()->json(['not_exists' =>['Unregistered user.']]);
        return response($user);

    }
      /**
     * Method for store support ticket information
     * @param Illuminate\Http\Request $request
     */
    public function store(Request $request){
        $validator          = Validator::make($request->all(),[
            'email'         => 'required|email',
            'user_type'     => 'nullable|string',
            'firstname'     => 'required_if:user_type,==' . SupportTicketConst::NEWUSER,
            'lastname'      => 'required_if:user_type,==' . SupportTicketConst::NEWUSER,
            'password'      => 'required_if:user_type,==' . SupportTicketConst::NEWUSER,
            'subject'       => 'required|string',
            'desc'          => 'required',
            'attachment.*'  => "nullable|max:204800",
        ]);
        if($validator->fails()){
            return back()->withErrors($validator)->withInput($request->all());
        }

        $validated          = $validator->validate();
        $basic_settings     = BasicSettings::first();
        if($validated['user_type'] == SupportTicketConst::USER){
            $user                       = User::where('email',$validated['email'])->first();
            $validated['token']         = generate_unique_string('user_support_tickets','token');
            $validated['user_id']       = $user->id;
            $validated['admin_id']      = auth()->user()->id;
            $validated['status']        = 0;
            $validated['created_at']    = now();
            $validated = Arr::except($validated,['user_type','firstname','lastname','password','attachment']);

            try{
                $support_ticket_id = UserSupportTicket::insertGetId($validated);

                if($basic_settings->email_notification == true){
                    try{
                        Notification::route('mail',$user->email)->notify(new SupportTicketNotification($user,$support_ticket_id));
                    }catch(Exception $e){}
                }
            }catch(Exception $e) {
                return back()->with(['error' => ['Something went worng! Please try again.']]);
            }

            if($request->hasFile('attachment')) {
                $validated_files = $request->file("attachment");
                $attachment = [];
                $files_link = [];
                foreach($validated_files as $item) {
                    $upload_file = upload_file($item,'support-attachment');
                    if($upload_file != false) {
                        $attachment[] = [
                            'user_support_ticket_id'    => $support_ticket_id,
                            'attachment'                => $upload_file['name'],
                            'attachment_info'           => json_encode($upload_file),
                            'created_at'                => now(),
                        ];
                    }

                    $files_link[] = get_files_path('support-attachment') . "/". $upload_file['name'];
                }

                try{
                    UserSupportTicketAttachment::insert($attachment);
                }catch(Exception $e) {
                    $support_ticket_id->delete();
                    delete_files($files_link);

                    return back()->with(['error' => ['Opps! Faild to upload attachment. Please try again.']]);
                }
            }

            return redirect()->route('admin.support.ticket.index')->with(['success' => ['Support ticket created successfully!']]);
        }else{

            $user_name              = make_username(Str::slug($validated['firstname']),Str::slug($validated['lastname']));
            $check_user_name        = User::where('username',$user_name)->first();

            if($check_user_name){
                $user_name = $user_name .'-'.rand(123,456);
            }

            $user_data['firstname']     = $validated['firstname'];
            $user_data['lastname']      = $validated['lastname'];
            $user_data['email']         = $validated['email'];
            $user_data['username']      = $user_name;
            $user_data['password']      = Hash::make($validated['password']);


            $user_data['status']        = true;
            $user_data['email_verified']      = true;
            $user_data['kyc_verified']        = true;
            try{
                $user = User::create($user_data);
                if($basic_settings->email_notification){
                    try{
                        Notification::route('mail',$validated['email'])->notify(new NewUserNotification($data = $user_data,$request->password));
                    }catch(Exception $e){
                    }
                }
            }catch(Exception $e){
                return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
            }

            $validated['token']         = generate_unique_string('user_support_tickets','token');
            $validated['user_id']       = $user->id;
            $validated['admin_id']      = auth()->user()->id;
            $validated['status']        = 0;
            $validated['created_at']    = now();
            $validated = Arr::except($validated,['user_type','firstname','lastname','password','attachment']);

            try{
                $support_ticket_id = UserSupportTicket::insertGetId($validated);

                if($basic_settings->email_notification == true){
                    try{
                        Notification::route('mail',$user->email)->notify(new SupportTicketNotification($user,$support_ticket_id));
                    }catch(Exception $e){}
                }
            }catch(Exception $e) {
                return back()->with(['error' => ['Something went worng! Please try again.']]);
            }

            if($request->hasFile('attachment')) {
                $validated_files = $request->file("attachment");
                $attachment = [];
                $files_link = [];
                foreach($validated_files as $item) {
                    $upload_file = upload_file($item,'support-attachment');
                    if($upload_file != false) {
                        $attachment[] = [
                            'user_support_ticket_id'    => $support_ticket_id,
                            'attachment'                => $upload_file['name'],
                            'attachment_info'           => json_encode($upload_file),
                            'created_at'                => now(),
                        ];
                    }

                    $files_link[] = get_files_path('support-attachment') . "/". $upload_file['name'];
                }

                try{
                    UserSupportTicketAttachment::insert($attachment);
                }catch(Exception $e) {
                    $support_ticket_id->delete();
                    delete_files($files_link);

                    return back()->with(['error' => ['Opps! Faild to upload attachment. Please try again.']]);
                }
            }
            return redirect()->route('admin.support.ticket.index')->with(['success' => ['Support ticket created successfully!']]);
        }
    }
      /**
     * Method for delete multiple tickets
     * @param Illuminate\Http\Request $request
     */
    public function bulkDelete(Request $request){
        $validator = Validator::make($request->all(), [
            'target' => 'required',
        ]);
        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $validated = $validator->validate();

        $ticket_ids       = array_map('intval', explode(',', $validated['target']));

        try{
            UserSupportTicket::whereIn('id', $ticket_ids)->delete();
        }catch(Exception $e){
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return back()->with(['success' => ['Support tickets deleted successfully.']]);
    }
    /**
     * Method for delete support ticket information
     * @param Illuminate\Http\Request $request
     */
    public function delete(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => 'required',
        ]);
        $validated = $validator->validate();

        $support_ticket = UserSupportTicket::where("id",$validated['target'])->first();

        try{
            $support_ticket->delete();
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return back()->with(['success' => ['Support ticket deleted successfully.']]);
    }
}
