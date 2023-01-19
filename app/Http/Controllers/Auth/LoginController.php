<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use Config;
use App\User;



class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('guest')->except('logout');
    }
	
	/**
     * API for User Login 
     */
    public function login(Request $request) {
		if(!empty($request->_provider) && $request->_provider === 'facebook') {
            $checkUser = User::where('facebook_id','=', $request->_profile['id'])
                    ->orWhere('email','=', $request->_profile['email'])
                    ->first();
            if(!empty($checkUser)) {
                if(($checkUser->status === 1) && ($checkUser->delete_status === 0)) {
                    if(empty($checkUser->facebook_id)) {
                        $updte = User::where('id','=',$checkUser->id)->update(['facebook_id' => $request->_profile['id']]);
                    }

                    Auth::login($checkUser);

                    $user = Auth::user();
                    $response['token']   = $user->createToken('MyApp')->accessToken;
                    $response['user']    = $user;
                    $response['status']  = true;
                    $response['success'] = true;
                    $response['register'] = false; 
                    return response()->json($response, 200);
                } else {
                    Auth::logout();                 
                     $response['status']  = false;    
                    $response['success']= false;
                    $response['msg']    = 'Your account is inactive. Please contact your administrator to activate it.';                               
                    return response()->json($response,200);
                }
                
            } else {
                $create = User::create([
                    'facebook_id'           => $request->_profile['id'],
                    'name'                  => $request->_profile['name'],
                    'email'                 => $request->_profile['email'],
                    'merchant_first_name'   => $request->_profile['firstName'],
                    'merchant_last_name'    => $request->_profile['lastName'],
                    'user_type'             => 2,// 2 -> merchant ,
                    'status'                => 2,// 2-> pending status
                ]);
                if($create) {
                    $to_email1= Config::get('constants.credentials.superAdminEmail');
                    $to_email2= Config::get('constants.credentials.superAdmin2Email');
                    $subject = 'New Merchant Registration';
                    $message = '';
                    $message.= '<span style="font-size: 12px; line-height: 1.5; color: #333333;">';
                    $message.= 'Hi,';
                    $message.= '<br/>';
                    $message.= 'New Merchant has registered with us!';
                    $message.= '<br/><br/>';
                    $message.= 'Here are the Merchant Details -:';
                    $message.= '<br/><br/>';
                    $message.= '<b>Name</b>: '.$request->_profile['name'];
                    $message.= '<br/>';
                    $message.= '<b>Email</b>: '.$request->_profile['email'];
                    $message.= '<br/><br/>';
                    $message.=  'Thank you,<br/>Fuze Financial Team';
                    $message.=  '</span>';

                    $params     = ['to_email' => $to_email1,
                                    'to_email_second_recipient' =>$to_email2,
                                    'subject' => $subject,
                                    'message' => $message,
                                    'from_name' => $request->_profile['name'],
                                    ];
                    // send mail
                    $emailMsg   = $this->sendMail($params);
                    
                    $response['status']     = true;
                    $response['register']   = true; 
                    $response['success']    = true;
                    $response['msg']        = Config::get('constants.messages.registrationSuccess'); 
                    return response()->json($response, 200); 
                } else {
                    return response()->json(['status' => false,'message' => Config::get('constants.messages.wrong')]);
                }
            }
        } else {
            $this->validateLogin($request);

            if($this->attemptLogin($request)) {
                // check account is active
                if (User::isActive(strtolower(trim($request['email'])))) 
                {
                    $user = Auth::user();
                    $response['token']   = $user->createToken('MyApp')->accessToken;
                    $response['user']    = $user;
                    $response['success'] = true;
                    return response()->json($response, 200);
                } 
                else 
                {
                    Auth::logout();                 
                    $response['success']= false;
                    $response['msg']    = 'Your account is inactive. Please contact your administrator to activate it.';                               
                    return response()->json($response,200);
                }
            } else {

                $response['success']= false;
                $response['msg']    = 'Your Email & Password combination do not match!';
                return response()->json($response,200);
            }
        }
		
    }


    public function sendMail($params) {
        $fromEmail  = (isset($params['from_email']) && $params['from_email']!="") ? $params['from_email'] : config('mail.from.address');
        $fromName   = (isset($params['from_name']) && $params['from_name']!="") ? $params['from_name'] : config('mail.from.name');
        
        try{
            $mail = new PHPMailer(TRUE);

            $mail->isHTML(TRUE);
            $mail->isSMTP();

            $mail->SMTPDebug = false;
            /* SMTP server address. */
            $mail->Host = config('mail.host');

            /* Use SMTP authentication. */
            $mail->SMTPAuth = TRUE;

            /* Set the encryption system. */
            $mail->SMTPSecure = config('mail.encryption');

            /* SMTP authentication username. */
            $mail->Username = config('mail.username');

            /* SMTP authentication password. */
            $mail->Password = config('mail.password');

            /* Set the SMTP port. */
            $mail->Port = config('mail.port');  //SSL:465 TLS : 587 , Non SSL : 25
            /* Set the mail sender. */
            $mail->setFrom($fromEmail, $fromName);

            /* Add a recipient. */
            $mail->addAddress($params['to_email']);
            // second recipient
            if(!empty($params['to_email_second_recipient'])) {
                $mail->addAddress($params['to_email_second_recipient']);
            }
            
            //$mail->AddReplyTo($emailfrom);

            /* Set the subject. */
            $mail->Subject  = $params['subject']; 

            /* Set the mail message body. */
            $mail->Body     = $params['message'];

            /* Finally send the mail. */
            $result = $mail->send();
            if($result){
                    $res['response']= $result;
                    $res['error']   = 0;
                    $res['msg']     = 'Message send successfully.';
            }else{
                    $res['response'] = 0;
                    $res['error']   = 1;
                    $res['msg']     = 'Message could not be sent.';
            }

        }catch (Exception $ex) {
            $res['response']    = '';
            $res['error']       = 1;
            $res['msg']         = $ex->getMessage();
        }
    }



	
	public function logout() {
        $user = Auth::user();
        $user->token()->revoke();
        $user->token()->delete();
        return response()->json(['success'=>true], 200);
    }

    protected function credentials(Request $request) {
        $credentials = [
            $this->username() => strtolower($request->get($this->username())),
            "password" => $request->get("password")
        ];
        return $credentials;
    }

}
