<?php
/*
|--------------------------------------------------------------------------
| User controller
|--------------------------------------------------------------------------
|
| Show/Store/Edit User related entries.
| 
*/
namespace App\Http\Controllers;

use DB;
use PDF;
use App\User;
use App\SMS;
use App\PaymentGatewaySetting;
use App\MerchantCredits;
use App\UploadedCsv;
use Illuminate\Http\Request;
use App\Exports\MerchantExcelExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use PHPMailer\PHPMailer\PHPMailer;
use Config;

class UserController extends Controller
{
	use AuthenticatesUsers;
	
	/**
	 * Send reset password link on request.
	 * API Path: /recover_password
	 * Method : POST
	 * @return view/json with message
	*/
	function sendPasswordMail(Request $request)
	{
		#Set all values
		$email 	= isset($request->email) ? trim($request->email) : "";
		$url 	= config('app.api_url').'/#/create_password/';

		// check email with user type 1 (admin)
		$user 	= User::select('id', 'name', 'email')->where('email', '=', $email)->first();
		
		if($user)
		{
			$encodedUrl = $url.urlencode(base64_encode($user->id."~".$user->email));
			
			$subject = 'Fuze Financial: Recover Your Password';
			
			$message = 'Dear '.$user->name.'<br/><br/>';
			$message.= '<span style="font-size: 12px; line-height: 1.5; color: #333333;">';
			$message.= 'We have sent you this email in response to your request to recover the password of your Fuze Financial account.';
			$message.= '<br/><br/>';
			$message.= 'Please click <a href="'.$encodedUrl.'">here</a> to recover your password.';
			$message.= '<br/><br/>';
			$message.=  'Thank you,<br/>Fuze Financial Team';
			$message.=  '</span>';

			$params		= [ 'to_email' => $user->email,
							'subject' => $subject,
							'message' => $message
						  ];
			// send mail
			$emailMsg 	= User::sendMail($params);

			if(!$emailMsg['error'])
			{
				$response['success']= true;
				$response['msg'] 	= $message;   				
                return response()->json($response,200);
			}else {
				$response['success']= false;
                $response['msg'] 	= 'Please enter valid e-mail address.!';             
                return response()->json($response,200);
            }
        }else{
			$response['success']= false;
            $response['msg']	='Please enter valid e-mail address.!';             
            return response()->json($response,200);            
        }	
	}	


	/**
	 * Create new password on request.
	 * API URL: /create_password
	 * Method : POST
	 * @return view/json with message
	*/
	function createPassword(Request $request)
	{		
		// Apply validator
		$validator = Validator::make($request->all(), [
                'new_password' => 'required',
                'confirm_password' => 'required|same:new_password'
        ]);
		
		// check if validator passes
		if($validator->passes())
		{
			#Set all values
			$password 	= isset($request->new_password) ? $request->new_password : "";
			$encId 		= explode('~',  base64_decode(urldecode($request->encId)));
			$user_id 	= $encId[0]; // Defined as User ID
			
			$user = User::where('id', '=', $user_id)->count();
			
			if($user)
			{
				$result = User::changePassword($user_id, $password);
				
				if($result)
				{
					$response['success']= true; 				
					return response()->json($response,200);
				}
				else
				{
					$response['success']= false;
					$response['msg'] 	= 'Password could not be created.';             
					return response()->json($response,200);
				}

			}else{
				$response['success']= false;
				$response['msg'] 	= 'Authentication failed!';             
				return response()->json($response,200);			
			}
		}
		else
		{
			$response['success']= false;
			$response['msg'] 	= 'New password and confirm are not same.';             
			return response()->json($response,200);	
		}
	}
	
	/**
	 * Get user profile detail.
	 * API Path: /user/profile
	 * Method : GET
	 * @return view/json with message
	*/
	public function getProfileDetail()
	{
		$userId 	= Auth::user()->id;
		
		if(Auth::user()->user_type==2)
		{
			$fields		= ['id', 'name', 'email', 'allowed_review_rating', 'yelp_short_url', 'google_short_url', 'sms_request_for_rating', 'sms_below_threshold_rating', 'yelp_api_key','google_api_key'];
		}
		else
		{
			$fields		= ['id', 'name', 'email'];
		}
			
		$userDetail = User::select($fields)->where('id', $userId)->first();
		$userDetail['yelp_api_key'] = $userDetail->yelp_api_key!=null ? Crypt::decryptString($userDetail->yelp_api_key) : null;
		$userDetail['google_api_key'] =  $userDetail->google_api_key!=null ? Crypt::decryptString($userDetail->google_api_key) : null;
		return response()->json(['success' => true, 'user' => $userDetail], 200);
	}
	
	/**
	 * Update user profile.
	 * API Path: user/update
	 * Method : POST
	 * @return view/json with message
	*/
	public function updateProfile(Request $request)
    {		
		$rules  = ['username' => 'required'
				 ];
		
		// Apply validator
		$validator = Validator::make($request->all(), $rules);
		
		// check if validator passes
		if($validator->passes())
		{
			$userId = Auth::user()->id;
			$user 	= User::find($userId);
			$user->name	= $request->username;

			$user->yelp_api_key	= Crypt::encryptString($request->yelp_api_key);
			$user->google_api_key	= Crypt::encryptString($request->google_api_key);
			
			if(isset($request->allowed_review_rating))
			{
				$user->allowed_review_rating	= $request->allowed_review_rating;
			}
			
			$user->updated_at = date('Y-m-d H:i:s');
			$saved = $user->save();
			
			if($saved)
			{
				$success = true;
				$msg = 'Account information updated successfully.';
			}
			else
			{
				$success = false;
				$msg = 'Account information could not be updated.';
			}		
		}
		
		$result = ['name' => $user->name];
		return response()->json(['success' => $success, 'msg'=>$msg, 'result' => $result], 200);
    }
	
	/**
	 * Add merchant.
	 * API Path: merchant/add
	 * Method : POST
	 * @return view/json with message
	*/
	function addMerchant(Request $request)
	{
		$sendMail = false;
		if(!empty($request->submit_for)) {
			$rules    = ['merchant_company_name' => 'required',
					 'email' =>    'required|email',
					 'contact_number' => 'required',
					 'company_contact_number' =>'required',
					 'equipment_type' =>'required',
					 'staff_id' =>'required',
					];
			$password     = '';
			$user_status = 2;
			$successMessage = 'Your Merchant has been added successfully! Merchants will be notified over email once their Registration process is complete and verified by the Admin!';
		} else {
			// this is come from add merchant from admin
			$rules    = ['merchant_first_name' => 'required',
					 'facility_name' => 'required',
					 'email' =>    'required|email',
					 'contact_number' => 'required',
					 'allowed_review_rating' =>'required',
					 'yelp_review_url' =>'required',
					 'yelp_business_id' =>'required',
					 'yelp_api_key' =>'required',
					 'google_review_url' =>'required',
					 'google_business_id' =>'required',
					 'google_api_key' =>'required'
					];
			$user_status = 1;
			$password    = str_random(6);
			//$successMessage = 'Your Merchant has been added successfully! Merchants will be notified over email once their Registration process is complete and verified by the Admin!';
		}
		
		
		// Apply validator
		$validator = Validator::make($request->all(), $rules);
		
		// Check if validator passes
		if($validator->passes())
		{
			// Check email exists
			$checkUserExists = User::select('id')->where('email', strtolower(trim($request->email)))->first();
			
			$response = [];
			
			$url 	= config('app.api_url');
			
			if(!$checkUserExists)
			{
				$smsRequestForRating = 'Thank you for using '.trim($request->facility_name).'. How would you rate your experience out of 5 (5 is best service)?';
				
				$smsBelowThresholdRating = 'We apologize you had a bad experience and want to make it up to you. A representative will be contacting you shortly.';
				
				// Create short URL of Yelp
				$yelpReviewUrl	= $request->yelp_review_url;
				$yelpShortUrl 	= SMS::createShortUrl($yelpReviewUrl);				
				// Encrypt Yelp API Key
				$yelpApiKey		= $request->yelp_api_key!="" ? Crypt::encryptString($request->yelp_api_key) : null;
				
				// Create short URL of Google
				$googleReviewUrl= $request->google_review_url;
				$googleShortUrl = SMS::createShortUrl($googleReviewUrl);				
				// Encrypt Google API Key
				$googleApiKey	= $request->google_api_key!="" ? Crypt::encryptString($request->google_api_key) : null;
				
				// Encrypt Clearent API Key
				$clearentApiKey	= $request->clearent_api_key!="" ? Crypt::encryptString($request->clearent_api_key) : null;
				
				// Encrypt Card Connect Api TOKEN Key
				$cardConnectApiToken	= $request->card_connect_api_key!="" ? Crypt::encryptString($request->card_connect_api_key) : null;

				// Encrypt Tysys Api Key
				$transactionId	= $request->transaction_id!="" ? Crypt::encryptString($request->transaction_id) : null;
				
				// Encrypt Clove Api for Api Token Key
				$cloverApiToken	= $request->clover_api_token!="" ? Crypt::encryptString($request->clover_api_token) : null;

				$password   				= str_random(6);
				$user 						= new User();
				$user->email				= strtolower(trim($request->email));
				$user->password 			= Hash::make($password);
				$user->contact_number 		= $request->contact_number;

				$user->merchant_first_name = $request->merchant_first_name;
				$user->merchant_last_name  = $request->merchant_last_name;
				$user->name				   = $request->merchant_first_name;

				if(!empty($request->merchant_company_name)){
					$user->merchant_company_name            = $request->merchant_company_name;
				}


				// add by staff
				$user->staff_id 						= $request->staff_id;
				$user->company_contact_number 			= $request->company_contact_number;
				$user->merchant_company_name            = $request->merchant_company_name;
				$user->sales_representative_name 		= $request->sales_representative_name;
				$user->sales_representative_email 		= $request->sales_representative_email;
				$user->sales_representative_contact_no 	= $request->sales_representative_contact_no;
				$user->merchant_website 			    = $request->merchant_website;
				// when equipment type seletect other
				if($request->equipment_type == 'other') {
					$createEqp = \App\EquipmentType::create([
						'user_id' => $request->staff_id,
						'name'    => $request->other_equipment_type,
						'status'  => 1,//active 
					]);
					if($createEqp) {
						$equipment_type_id 	= $createEqp->id;
					}
				} else {
					$equipment_type_id 		= $request->equipment_type;
				}
				$user->equipment_type_id 	= $equipment_type_id;
				// end staff

                $user->facility_name    			= $request->facility_name;
				$user->allowed_review_rating		= $request->allowed_review_rating;
				$user->yelp_review_url  			= $yelpReviewUrl;
				$user->yelp_short_url  				= $yelpShortUrl;
				$user->yelp_business_id  			= $request->yelp_business_id;
				$user->yelp_api_key  				= $yelpApiKey;
				$user->google_review_url			= $googleReviewUrl;
				$user->google_short_url  			= $googleShortUrl;
				$user->google_business_id			= $request->google_business_id;
				$user->google_api_key				= $googleApiKey;
				$user->mid_number					= $request->mid_number;
				$user->tpn							= $request->tpn;
				$user->terminal_type				= $request->terminal_type;
				$user->sms_request_for_rating 		= $smsRequestForRating;
				$user->sms_below_threshold_rating 	= $smsBelowThresholdRating;

				// ADD Credits Section
				$user->monthly_credit_quota 		= $request->monthly_credit_quota?$request->monthly_credit_quota:0;
				$user->add_to_monthly_credit_quota 	= $request->add_to_monthly_credit_quota?$request->add_to_monthly_credit_quota:0;
				$user->one_time_credit_add 			= $request->one_time_credit_add?$request->one_time_credit_add:0;
				$user->total_credits_this_month 	= $request->total_credits_this_month?$request->total_credits_this_month:0;
				$user->credits						= $request->total_credits_this_month?$request->total_credits_this_month:0;
				if(!empty($request->monthly_credit_quota)) {
					$user->monthly_credit_create_date   = date('Y-m-d');
					$user->monthly_credit_next_date	    = date('Y-m-d', strtotime($user->monthly_credit_create_date. ' +30 days'));
				}
				//end

				$user->user_type 					= 2;	
				$user->status 						= $user_status;	
				$user->created_at					= date('Y-m-d H:i:s');
				$saved = $user->save();
				if($saved) {
					$lastInsertId = $user->id;
					$data = [
						'user_id' 		=> $lastInsertId,
						'status' 		=> 1, //'active'
						'created_at' 	=> date('Y-m-d H:i:s'),
						'updated_at' 	=> date('Y-m-d H:i:s'),
					];
					if(!empty($request->checked_api)) {
						if($request->checked_api == 'clearent') {
							$data1 = $data;
							$data1['api_key'] = $clearentApiKey;
							$data1['pg_name'] = $request->checked_api;
							PaymentGatewaySetting::create($data1);
						} elseif($request->checked_api == 'card_connect') {
							$data2 = $data;
							$data2['merchant_id'] 	= $request->merchant_id;
							$data2['api_key'] 		= $cardConnectApiToken;
							$data2['pg_name']     	= $request->checked_api;
							PaymentGatewaySetting::create($data2);
						} elseif($request->checked_api == 'tsys') {
							$data3 = $data;
							$data3['device_id'] 		= $request->device_id;
							$data3['transaction_id'] 	= $transactionId;
							$data3['developer_id'] 		= $request->developer_id;
							$data3['pg_name'] 			= $request->checked_api;
							PaymentGatewaySetting::create($data3);
						} elseif($request->checked_api == 'clover') {
							$data4 						= $data;
							$data4['merchant_id'] 		= $request->clover_merchant_id;
							$data4['api_key'] 			= $cloverApiToken;
							$data4['pg_name'] 			= $request->checked_api;
							PaymentGatewaySetting::create($data4);
						}
					}
				}
				// if user add by staff then main not will be sent to user
					if($saved)
					{					
						if(!isset($request->submit_for)) {
							$subject = 'Welcome to Fuze Financial';
							
							$message = "Dear ".$user->name.",<br/><br/>";
							$message.= '<span style="font-size: 12px; line-height: 1.5; color: #333333;">';
							$message.= 'Your Fuze Financial Merchant Account has been created. Below are the login credentials to access your account:';
							$message.= '<br/><br/>';
							$message.= '<b>Email</b>: '.$user->email;
							$message.= '<br/>';
							$message.= '<b>Password</b>: '.$password;
							$message.= '<br/><br/>';
							$message.= '<i>(Please note your temporary password is case sensitive.)</i>';
							$message.= '<br/><br/>';
							$message.= 'Please click <a href="'.$url.'">here</a> to login.';
							$message.= '<br/><br/>';
							$message.=  'Thank you,<br/>Fuze Financial Team';
							$message.=  '</span>';

							$params		= ['to_email' => $user->email,
										'subject' => $subject,
										'message' => $message
											];
							// send mail
							$emailMsg 	= User::sendMail($params);

							if(!$emailMsg['error'])
							{
								$response['success']= true;
								$response['msg'] 	= 'Merchant added and a mail sent at the registered email address.';	
							}else {
								$response['success']= false;
								$response['msg'] 	= 'Merchant added but mail could not be sent!'; 
							}
						}
						$response['success']= true;	
						$response['msg'] 	= !empty($successMessage)?$successMessage:'Merchant added and a mail sent at the registered email address';				
					}
					else
					{
						$response['success']= false;
						$response['msg'] 	= 'Merchant could not be added!'; 
					}
				
				
			}else{
				$response['success']= false;
				$response['msg']	='Email account is already Registered.';
			}
		}
		else
		{
			$response['success']= false;
			$response['msg']	='Please fill all the required fields';
		}
		return response()->json($response, 200);   
	}
	
	/**
	 * get merchant list
	 * API Path: merchant/list
	 * Method : GET
	 * @return view/json with message
	*/
	public function getMerchantsList()
    {
		$paginate  		= $_GET['defaultPagination'];
		
		// If search keyword 
		$searchKeyword 	= isset($_GET['searchKeyword']) && trim($_GET['searchKeyword'])!="" ? trim($_GET['searchKeyword']) : '';
		
		// To apply sort by
		$sortBy   		= isset($_GET['sortBy']) && $_GET['sortBy'] ? trim($_GET['sortBy']) : '';
		
		$qryUsers 	= User::select(DB::Raw("users.id, users.name,users.merchant_first_name,users.merchant_last_name,users.merchant_company_name, users.email, users.contact_number, TO_CHAR((users.created_at::date), 'MON-DD-YYYY') AS Created_on, payment_gateway_settings.pg_name,payment_gateway_settings.status AS payment_gatway_status,  (CASE WHEN (users.status=1) THEN 'Active' ELSE 'Inactive' END) AS status"));
		$qryUsers->leftJoin('payment_gateway_settings', 'payment_gateway_settings.user_id', '=', 'users.id');
		$qryUsers->where('users.delete_status', '=', 0);					
		$qryUsers->where('users.user_type', 2);
		if(isset($_GET['user_id'])) {
			$qryUsers->where('users.staff_id', $_GET['user_id']);
		}					
									
		// Apply search
		if($searchKeyword!="")
		{
			$qryUsers->whereRaw(DB::raw("(users.name ILIKE '%" . $searchKeyword . "%' OR users.email ILIKE '%" . $searchKeyword . "%' OR users.contact_number ILIKE '%" . $searchKeyword . "%')"));
		}
		
		// Apply sort 
		if($sortBy!='')
				$qryUsers->orderBy('users.'.$sortBy, 'ASC');
		else
			$qryUsers->orderBy('users.id', 'DESC');
		
		$usersList 	= $qryUsers->paginate($paginate);
					
        return response()->json(['success' => true, 'result' => $usersList], 200);
    }
	
	/**
	 * get merchant detail
	 * API Path: merchant/detail
	 * Method : GET
	 * @return view/json with message
	*/
	public function getMerchantDetail($userId)
	{
		$user = User::select('id', 'name', 'email', 'facility_name', 'contact_number', 'allowed_review_rating', 'yelp_review_url', 'yelp_short_url', 'yelp_business_id', 'yelp_api_key', 'google_review_url', 'google_short_url','google_business_id', 'google_api_key','mid_number','tpn','terminal_type','company_contact_number','sales_representative_name','sales_representative_email','sales_representative_contact_no','equipment_type_id','merchant_website','merchant_company_name','merchant_first_name','merchant_last_name','monthly_credit_quota','add_to_monthly_credit_quota','one_time_credit_add','total_credits_this_month')->where('id', $userId)->first();
		$userApiDetail = PaymentGatewaySetting::where('user_id', $userId)->where('status', 1)->get();
		$clearent_api_key = '';
		$merchant_id = '';
		$developer_id = '';
		$transaction_id = '';
		$device_id = '';
		$clover_merchant_id = '';
		$clover_api_token = '';
		$card_connect_api_key = '';
		$mid_number = '';
		$tpm = '';
		$terminal_type = '';
		$clearent_api_checkbox = false;
		$card_connect_checkbox = false;
		$tsys_checkbox = false;
		$clover_checkbox = false;
		if(!empty($userApiDetail) && !empty($apiDetail = $userApiDetail->toArray())){
			foreach($apiDetail as $apiDetailVal) {
				if($apiDetailVal['pg_name'] == 'clearent') {
					$clearent_api_key = $apiDetailVal['api_key'];
					$clearent_api_checkbox = true;
				}
				if($apiDetailVal['pg_name'] == 'card_connect') {
					$merchant_id = $apiDetailVal['merchant_id'];
					$card_connect_api_key = $apiDetailVal['api_key'];
					$card_connect_checkbox = true;
				}
				if($apiDetailVal['pg_name'] == 'tsys') {
					$developer_id = $apiDetailVal['developer_id'];
					$transaction_id = $apiDetailVal['transaction_id'];
					$device_id = $apiDetailVal['device_id'];
					$tsys_checkbox = true;
				}
				if($apiDetailVal['pg_name'] == 'clover') {
					$clover_api_token = $apiDetailVal['api_key'];
					$clover_merchant_id = $apiDetailVal['merchant_id'];
					$clover_checkbox = true;
				}
			}
		}

		$userDetail = ['id' => $user->id,
				       'name' => $user->name,
					   'email' => $user->email,
					   'facility_name' => $user->facility_name,
					   'contact_number' => $user->contact_number,
					   'company_contact_number' => $user->company_contact_number,
					   'merchant_company_name' => $user->merchant_company_name,
					   'merchant_first_name' => $user->merchant_first_name,
					   'merchant_last_name'  => $user->merchant_last_name,
					   'sales_representative_name' => $user->sales_representative_name,
					   'sales_representative_email' => $user->sales_representative_email,
					   'sales_representative_contact_no' => $user->sales_representative_contact_no,
					   'merchant_website' => $user->merchant_website,
					   'equipment_type' => $user->equipment_type_id,
					   'allowed_review_rating' => $user->allowed_review_rating,
					   'yelp_review_url' => $user->yelp_review_url,
					   'yelp_short_url' => $user->yelp_short_url,
					   'yelp_business_id' => $user->yelp_business_id,
					   'yelp_api_key' => $user->yelp_api_key!=null ? Crypt::decryptString($user->yelp_api_key) : null,
					   'google_review_url' => $user->google_review_url,
					   'google_short_url' => $user->google_short_url,
					   'google_business_id' => $user->google_business_id,
					   'mid_number' => $user->mid_number,
					   'tpn' => $user->tpn,
					   'terminal_type' => $user->terminal_type,
					   'google_api_key' => $user->google_api_key!=null ? Crypt::decryptString($user->google_api_key) : null,
					   'clearent_api_checkbox' => $clearent_api_checkbox,  
					   'clearent_api_key' => $clearent_api_key!=null ? Crypt::decryptString($clearent_api_key) : null,
					   'card_connect_checkbox' => $card_connect_checkbox,  
					   'merchant_id' => $merchant_id,
					   'card_connect_api_key' => $card_connect_api_key!=null ? Crypt::decryptString($card_connect_api_key) : null, 
					   'tsys_checkbox' => $tsys_checkbox,
					   'developer_id' => $developer_id,
					   'transaction_id' => $transaction_id!=null ? Crypt::decryptString($transaction_id) : null,
					   'device_id' => $device_id,
					   'clover_checkbox' => $clover_checkbox,
					   'clover_merchant_id' => $clover_merchant_id,
					   'clover_api_token' => $clover_api_token!=null ? Crypt::decryptString($clover_api_token) : null,
					   'monthly_credit_quota' => $user->monthly_credit_quota,
					   'add_to_monthly_credit_quota' => $user->add_to_monthly_credit_quota,
					   'one_time_credit_add' => $user->one_time_credit_add,
					   'total_credits_this_month' => $user->total_credits_this_month,
					  ];
		
		return response()->json(['success' => true, 'user' => $userDetail], 200);
	}
	
	/**
	 * get edit user
	 * API Path: user/edit
	 * Method : POST
	 * @return view/json with message
	*/
	public function editMerchant(Request $request)
    {		
		$userId = $request->user_id;
		if(!empty($request->submit_for) && $request->submit_for == 'staff') {
			$rules    = ['merchant_company_name' => 'required',
					 'contact_number' => 'required',
					 'company_contact_number' =>'required',

					 'equipment_type' =>'required',
				    ];
		} else {
			$rules  = [	'merchant_first_name' => 'required',
					'facility_name' => 'required',
					'contact_number' => 'required',
					'allowed_review_rating'=>'required',
					'yelp_review_url' =>'required',
					'yelp_business_id' =>'required',
					'yelp_api_key' =>'required',
					'google_review_url' =>'required',
					'google_business_id' =>'required',
					'google_api_key' =>'required'
				  ];
		}
		
		// Apply validator
		$validator = Validator::make($request->all(), $rules);
		// check if validator passes
		if($validator->passes())
		{
			$user 						= User::find($userId);
			if(empty($request->submit_for)) {
				// Create short URL of Yelp
				$yelpReviewUrl	= $request->yelp_review_url;
				$yelpShortUrl 	= SMS::createShortUrl($yelpReviewUrl);				
				// Encrypt Yelp API Key
				$yelpApiKey		= $request->yelp_api_key!="" ? Crypt::encryptString($request->yelp_api_key) : null;
				
				// Create short URL of Google
				$googleReviewUrl= $request->google_review_url;
				$googleShortUrl = SMS::createShortUrl($googleReviewUrl);				
				// Encrypt Google API Key
				$googleApiKey	= $request->google_api_key!="" ? Crypt::encryptString($request->google_api_key) : null;
				
				//Encrypt Clearent API Key
				$clearentApiKey	= $request->clearent_api_key!="" ? Crypt::encryptString($request->clearent_api_key) : null;
					
				// Encrypt Card Connect Api TOKEN Key
				$cardConnectApiToken	= $request->card_connect_api_key!="" ? Crypt::encryptString($request->card_connect_api_key) : null;

				// Encrypt Tysys Api Key
				$transactionId	= $request->transaction_id!="" ? Crypt::encryptString($request->transaction_id) : null;

				// Encrypt Clove Api for Api Token Key
				$cloverApiToken	= $request->clover_api_token!="" ? Crypt::encryptString($request->clover_api_token) : null;
				
				$user->facility_name		= $request->facility_name;
				$user->allowed_review_rating= $request->allowed_review_rating;
				$user->yelp_review_url  	= $yelpReviewUrl;
				$user->yelp_short_url  		= $yelpShortUrl;
				$user->yelp_business_id		= $request->yelp_business_id;
				$user->yelp_api_key  		= $yelpApiKey;
				$user->google_review_url	= $googleReviewUrl;
				$user->google_short_url  	= $googleShortUrl;
				$user->google_business_id	= $request->google_business_id;
				$user->google_api_key		= $googleApiKey;
				$user->mid_number			= $request->mid_number;
				$user->tpn					= $request->tpn;
				$user->terminal_type		= $request->terminal_type;
			}

			$user->contact_number		= $request->contact_number;
			// add by staff
			$user->merchant_first_name = $request->merchant_first_name;
			$user->merchant_last_name  = $request->merchant_last_name;
			$user->name				   = $request->merchant_first_name;
			
			$user->merchant_company_name            = $request->merchant_company_name;
			
			$user->company_contact_number 			= $request->company_contact_number;
			$user->sales_representative_name 		= $request->sales_representative_name;
			$user->sales_representative_email 		= $request->sales_representative_email;
			$user->sales_representative_contact_no 	= $request->sales_representative_contact_no;
			$user->merchant_website 			    = $request->merchant_website;
			// when equipment type seletect other
			if($request->equipment_type == 'other' && !empty($request->other_equipment_type)) {
				$createEqp = \App\EquipmentType::create([
					'user_id' => $userId,
					'name'    => $request->other_equipment_type,
					'status'  => 1,//active 
				]);
				if($createEqp) {
					$equipment_type_id 	= $createEqp->id;
				}
			} else if($request->equipment_type == 'other' && empty($request->other_equipment_type)) {
				$equipment_type_id 		= null;
			} else {
				$equipment_type_id 		= $request->equipment_type;
			}
			$user->equipment_type_id 	= $equipment_type_id;
			// end staff
			

			// ADD Credits Section
			$user->monthly_credit_quota 		= $request->monthly_credit_quota?$request->monthly_credit_quota:0;
			$user->add_to_monthly_credit_quota 	= $request->add_to_monthly_credit_quota?$request->add_to_monthly_credit_quota:0;
			$user->one_time_credit_add 			= $request->one_time_credit_add?$request->one_time_credit_add:0;
			$user->total_credits_this_month 	= $request->total_credits_this_month?$request->total_credits_this_month:0;
			
			$checkMerchantSubscription = MerchantCredits::where(['merchant_id' => $userId,'status' => 1])->first();

			if(empty($checkMerchantSubscription)) {
				$user->credits	= $request->total_credits_this_month?$request->total_credits_this_month:0;
				$user->monthly_credit_create_date   = date('Y-m-d');
				$user->monthly_credit_next_date	    = date('Y-m-d', strtotime($user->monthly_credit_create_date. ' +30 days'));
			}
			//end

			$user->updated_at					= date('Y-m-d H:i:s');
			$saved = $user->save();
			
			if($saved)
			{
				$data = [
					'user_id' => $userId,
					'status'  => 1,
				];
				$checkClearent = PaymentGatewaySetting::where(['user_id'=> $userId,'pg_name'=> 'clearent'])->first();
				if(!empty($request->checked_api)) {
					if(!empty($checkClearent) && ($request->checked_api != 'clearent')) {
						PaymentGatewaySetting::where(['user_id'=> $userId,'pg_name'=> 'clearent'])->delete();
					}  else if(!empty($checkClearent) && ($request->checked_api == 'clearent')) {
						$data1['api_key'] 		= $clearentApiKey;
						$data1['pg_name'] 		= $request->checked_api;
						$data1['status'] 		= 1;
						$data1['updated_at'] 	= date('Y-m-d H:i:s');
						PaymentGatewaySetting::where(['user_id'=> $userId,'id'=> $checkClearent->id])->update($data1);
					} else if(empty($checkClearent) && ($request->checked_api == 'clearent')){
						$data1 = $data;
						$data1['api_key'] 		= $clearentApiKey;
						$data1['pg_name'] 		= $request->checked_api;
						$data1['created_at'] 	= date('Y-m-d H:i:s');
						$data1['updated_at'] 	= date('Y-m-d H:i:s');
						PaymentGatewaySetting::create($data1);
					}
				
					$checkCardConnect = PaymentGatewaySetting::where(['user_id'=> $userId,'pg_name'=> 'card_connect'])->first();
					if(!empty($checkCardConnect) && ($request->checked_api != 'card_connect')) {
						PaymentGatewaySetting::where(['user_id'=> $userId,'pg_name'=> 'card_connect'])->delete();
					} else if(!empty($checkCardConnect) && ($request->checked_api == 'card_connect')) {
						$data2['merchant_id'] = $request->merchant_id;
						$data2['api_key']     = $cardConnectApiToken;
						$data2['pg_name']     = $request->checked_api;
						$data2['status']      = 1;
						$data2['updated_at']  = date('Y-m-d H:i:s');
						PaymentGatewaySetting::where(['user_id'=> $userId,'id'=> $checkCardConnect->id])->update($data2);
					} else if(empty($checkCardConnect) && ($request->checked_api == 'card_connect')) {
						$data2 = $data;
						$data2['merchant_id'] 	= $request->merchant_id;
						$data2['api_key'] 		= $cardConnectApiToken;
						$data2['pg_name']     	= $request->checked_api;
						$data2['created_at']  	= date('Y-m-d H:i:s');
						$data2['updated_at']  	= date('Y-m-d H:i:s');
						PaymentGatewaySetting::create($data2);
					}
	
					$checkTsys = PaymentGatewaySetting::where(['user_id'=> $userId,'pg_name'=> 'tsys'])->first();
					if(!empty($checkTsys) && ($request->checked_api != 'tsys')) {
						PaymentGatewaySetting::where(['user_id'=> $userId,'pg_name'=> 'tsys'])->delete();
					} else if(!empty($checkTsys) && ($request->checked_api == 'tsys')) {
						$data3['device_id'] 		= $request->device_id;
						$data3['transaction_id'] 	= $transactionId;
						$data3['developer_id'] 		= $request->developer_id;
						$data3['pg_name'] 			= $request->checked_api;
						$data3['status'] 			= 1;
						$data3['updated_at'] 		= date('Y-m-d H:i:s');
						PaymentGatewaySetting::where(['user_id'=> $userId,'id'=> $checkTsys->id])->update($data3);
					} else if(empty($checkTsys) && ($request->checked_api == 'tsys')) {
						$data3 = $data;
						$data3['device_id'] 		= $request->device_id;
						$data3['transaction_id'] 	= $transactionId;
						$data3['developer_id'] 		= $request->developer_id;
						$data3['pg_name'] 			= $request->checked_api;
						$data3['created_at'] 		= date('Y-m-d H:i:s');
						$data3['updated_at'] 		= date('Y-m-d H:i:s');
						PaymentGatewaySetting::create($data3);
					}
	
					$checkClover = PaymentGatewaySetting::where(['user_id'=> $userId,'pg_name'=> 'clover'])->first();
					if(!empty($checkClover) && ($request->checked_api != 'clover')) {
						PaymentGatewaySetting::where(['user_id'=> $userId,'pg_name'=> 'clover'])->delete();
					} else if(!empty($checkClover) && ($request->checked_api == 'clover')) {
							$data4['merchant_id'] 	= $request->clover_merchant_id;
							$data4['api_key'] 		= $cloverApiToken;
							$data4['pg_name'] 		= $request->checked_api;
							$data4['status'] 		= 1;
							$data4['updated_at'] 	= date('Y-m-d H:i:s');
							PaymentGatewaySetting::where(['user_id'=> $userId,'id'=> $checkClover->id])->update($data4);
					} else if(empty($checkClover) && ($request->checked_api == 'clover')) {
						$data4 = $data;
						$data4['merchant_id'] 		= $request->clover_merchant_id;
						$data4['api_key'] 			= $cloverApiToken;
						$data4['pg_name'] 			= $request->checked_api;
						$data4['created_at'] 		= date('Y-m-d H:i:s');
						$data4['updated_at'] 		= date('Y-m-d H:i:s');
						PaymentGatewaySetting::create($data4);
					}
				}
				
				$success = true;
				$msg = 'Merchant account updated successfully.';
			}
			else
			{
				$success = false;
				$msg = 'Merchant account could not be updated.';
			}		
		}
		else
		{
			$success = false;
			$msg = 'Please fill all the required fields.';
		}
		
		return response()->json(['success' => $success, 'msg'=>$msg], 200);
    }
	
	/**
	 * Update merchant's status (activate / inactivate)
	 * API Path: merchant/update_status
	 * Method : POST
	 * @return view/json with message
	*/
	public function updateMerchantStatus(Request $request)
    {
		$userId		= $request->userId;
        $status 	= $request->status;
		
        $statusMsg	= $status == 1 ? 'activated' : 'inactivated';
		$password   = str_random(6);// genearte unique password
		$user = User::find($userId);
		$userStatus = $user->status;
		$user->status 	= $status;
		$user->password = Hash::make($password);
		$user->updated_at	= date('Y-m-d H:i:s');
		$update = $user->save();

        if($update) 
		{			
			if(!empty($user->staff_id) && ($user->user_type ==2) && ($userStatus == 2)) {
				$url 	= config('app.api_url');
				$subject = 'Welcome to Fuze Financial';
				$message = 'Dear ".$user->name.",<br/><br/>';
				$message.= '<span style="font-size: 12px; line-height: 1.5; color: #333333;">';
				$message.= 'Your Fuze Financial Merchant Account has been created. Below are the login credentials to access your account:';
				$message.= '<br/><br/>';
				$message.= '<b>Email</b>: '.$user->email;
				$message.= '<br/>';
				$message.= '<b>Password</b>: '.$password;
				$message.= '<br/><br/>';
				$message.= '<i>(Please note your temporary password is case sensitive.)</i>';
				$message.= '<br/><br/>';
				$message.= 'Please click <a href="'.$url.'">here</a> to login.';
				$message.= '<br/><br/>';
				$message.=  'Thank you,<br/>Fuze Financial Team';
				$message.=  '</span>';

				$params		= ['to_email' => $user->email,
							'subject' => $subject,
							'message' => $message
								];
				// send mail
				$emailMsg 	= User::sendMail($params);	
			}
			$success 	= true;
			$msg		= 'Merchant {$statusMsg} successfully.';
        } 
		else 
		{
			$success = false;
			$msg		= 'Merchant could not be {$statusMsg}.';
        }
		
		return response()->json(['success' => $success, 'msg'=>$msg], 200);
    }
	
	/**
	 * Delete Merchant
	 * API Path: merchant/delete
	 * Method : POST
	 * @return view/json with message
	*/
	public function deleteMerchant(Request $request)
    {
		$userId		= $request->userId;
		// soft delete
        $user = User::find($userId);
		$user->delete_status = 1;
		$user->updated_at	 = date('Y-m-d H:i:s');
		$update = $user->save();

        if($update) 
		{				
			$success 	= true;
			$msg		= 'Merchant deleted successfully.';
        } 
		else 
		{
			$success = false;
			$msg		= 'Merchant could not be deleted.';
        }
		
		return response()->json(['success' => $success, 'msg'=>$msg], 200);
    }
	
	/**
	 * generate a list of merchants in excel file
	 * API Path: merchants/export_excel
	 * Method : GET
	 * @return excel file
	*/
    public function exportMerchantsExcel()
    {
		// If search keyword 
		$searchKeyword 	= isset($_GET['searchKeyword']) && trim($_GET['searchKeyword'])!="" ? trim($_GET['searchKeyword']) : '';
		
		// To apply sort by
		$sortBy   		= isset($_GET['sortBy']) && $_GET['sortBy'] ? trim($_GET['sortBy']) : '';
		
		$qryMerchants 	= User::select(DB::Raw("users.name, users.email, users.contact_number, (CASE WHEN (users.status=1) THEN 'Active' ELSE 'Inactive' END) AS status"));
		$qryMerchants->where('users.delete_status', '=', 0);					
		$qryMerchants->where('users.user_type', 2);	

		if(isset($_GET['user_id'])) {
			$qryMerchants->where('users.staff_id', $_GET['user_id']);
		}		

		// Apply search
		if($searchKeyword!="")
		{
			$qryMerchants->whereRaw(DB::raw("(users.name ILIKE '%" . $searchKeyword . "%' OR users.email ILIKE '%" . $searchKeyword . "%' OR users.contact_number ILIKE '%" . $searchKeyword . "%')"));
		}
		
		// Apply sort 
		if($sortBy!='' && $sortBy!='state')
			$qryMerchants->orderBy('users.'.$sortBy, 'ASC');
		else
			$qryMerchants->orderBy('users.id', 'DESC');
		
		$merchantsList 	= $qryMerchants->get();
		
        return Excel::download(new MerchantExcelExport($merchantsList), 'Merchant-List.xlsx');
    }
	
	/**
	 * generate a list of merchants in pdf file
	 * API Path: merchants/export_pdf
	 * Method : GET
	 * @return pdf file
	*/
	public function exportMerchantsPdf()
    {
		// If search keyword 
		$searchKeyword 	= isset($_GET['searchKeyword']) && trim($_GET['searchKeyword'])!="" ? trim($_GET['searchKeyword']) : '';
		
		// To apply sort by
		$sortBy   		= isset($_GET['sortBy']) && $_GET['sortBy'] ? trim($_GET['sortBy']) : '';
		
		$qryMerchants 	= User::select(DB::Raw("users.name, users.email, users.contact_number, (CASE WHEN (users.status=1) THEN 'Active' ELSE 'Inactive' END) AS status"));
		$qryMerchants->where('users.delete_status', '=', 0);					
		$qryMerchants->where('users.user_type', 2);					
		if(isset($_GET['user_id'])) {
			$qryMerchants->where('users.staff_id', $_GET['user_id']);
		}								
		// Apply search
		if($searchKeyword!="")
		{
			$qryMerchants->whereRaw(DB::raw("(users.name ILIKE '%" . $searchKeyword . "%' OR users.email ILIKE '%" . $searchKeyword . "%' OR users.contact_number ILIKE '%" . $searchKeyword . "%')"));
		}
		
		// Apply sort 
		if($sortBy!='' && $sortBy!='state')
			$qryMerchants->orderBy('users.'.$sortBy, 'ASC');
		else
			$qryMerchants->orderBy('users.id', 'DESC');
		
		$merchantsList 	= $qryMerchants->get();
		
        $pdf = PDF::loadView('export.merchants_pdf', compact(array('merchantsList')));  
        return $pdf->download('Merchant-List.pdf');
	}
	
	/**
	 * Send Invitation Mail to Merchant
	 * API Path: merchant/send_invitation
	 * Method : POST
	 * @return view/json with message
	*/
	function sendInvitationMail(Request $request)
	{
		$userId	 	= $request->userId;
					
		// Generate and Update new Password
		$passwordString = User::generatePassword();
		$password 	= Hash::make($passwordString);
		$updPass 	= User::where('id', $userId)->update(['password' => $password, 'updated_at'=>date('Y-m-d H:i:s')]);
		
		$userDetail = User::select('name', 'email')->where('id', trim($userId))->first();
		
		$url 	 = config('app.api_url');
		$subject = 'Welcome to Fuze Financial';
		
		$message = 'Dear ".$userDetail->name."<br/><br/>';
		$message.= '<span style="font-size: 12px; line-height: 1.5; color: #333333;">';
		$message.= 'Your Fuze Financial Merchant Account has been created. Below are the login credentials to access your account:';
		$message.= '<br/><br/>';
		$message.= '<b>Email</b>: '.$userDetail->email;
		$message.= '<br/>';
		$message.= '<b>Password</b>: '.$passwordString;
		$message.= '<br/><br/>';
		$message.= '<i>(Please note your temporary password is case sensitive.)</i>';
		$message.= '<br/><br/>';
		$message.= 'Please click <a href="'.$url.'">here</a> to login.';
		$message.= '<br/><br/>';
		$message.=  'Thank you,<br/>Fuze Financial Team';
		$message.=  '</span>';

		$params		= ['to_email' => $userDetail->email,
					   'subject' => $subject,
					   'message' => $message
					  ];
		// send mail
		$emailMsg 	= User::sendMail($params);

		if(!$emailMsg['error'])
		{
			$response['success']= true;
			$response['msg'] 	= 'Invitation mail has been sent at the registered email address of the merchant.';	
		}else {
			$response['success']= false;
			$response['msg'] 	= 'Error: Invitation mail could not be sent!'; 
		}
		
		return response()->json($response, 200);
	}
	
	/**
	 * customize Merchant SMS
	 * API Path: merchant/customize_sms
	 * Method : POST
	 * @return view/json with message
	*/
	function customizeMerchantSms(Request $request)
	{
		$userId 	= Auth::user()->id;
		$smsRequestForRating		= $request->sms_request_for_rating;
		$smsBelowThresholdRating	= $request->sms_below_threshold_rating;
					
		// Update sms text
		$updSms 	= User::where('id', $userId)->update(['sms_request_for_rating' => $smsRequestForRating, 'sms_below_threshold_rating' => $smsBelowThresholdRating, 'updated_at'=>date('Y-m-d H:i:s')]);
		
		if($updSms)
		{
			$response['success']= true;
			$response['msg'] 	= 'SMS Text updated successfully.';	
		}else {
			$response['success']= false;
			$response['msg'] 	= 'Error: SMS Text could not be updated!'; 
		}
		
		return response()->json($response, 200);
	}

	/* UPDATE USER PRIVACY_ACCEPT_STATUS */
	public function acceptPrivacy(Request $request) {
		if(!empty($request->user_id)) {
			$update = User::where('id',$request->user_id)->update([
				'privacy_accept_status'  => $request->accept_privacy_status]);
			if($update) {
				return response()->json(['status' => true,'accept_privacy_status' => $request->accept_privacy_status,'res_status' =>200]);
			} else {
				return response()->json(['status' => false,'accept_privacy_status' => 0,'res_status' => 400]);
			}
		}
	}

	public function register(Request $request) {

		if(!empty($request->_provider) && $request->_provider === 'facebook') {
            $checkUser = User::where('facebook_id','=', $request->_profile['id'])
                    ->orWhere('email','=', $request->_profile['email'])
                    ->first();
             //return response()->json($checkUser);
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
        	$validator = Validator::make($request->All(),
				[
					'name' 				=> 'required',
					'email' 			=> 'required|unique:users',
					'contact_number' 	=> 'required',
					'password' 			=> 'required',
				]
			);
			if($validator->fails()) {
				return response()->json(['errors' => $validator->getMessageBag()->toArray()]);
			}

			$create = User::create([
				'name' 				=> $request->name,
				'email' 			=> $request->email,
				'contact_number' 	=> $request->contact_number,
				'password' 			=> Hash::make($request->password),
				'user_type' 		=> 2,// 2 -> merchant ,
				'status'    		=> 2,// 2-> pending status
			]);
			if($create) {
				// Encrypt Clove Api for Api Token Key
				$lastInsertId = $create->id;

				$cloverAccessToken	= $request->access_token!="" ? Crypt::encryptString($request->access_token) : null;
			
			    if(!empty($request->merchant_id) && !empty($request->employee_id) && !empty($request->client_id) && !empty($request->access_token))  {
					$data['user_id'] 		= $lastInsertId;
					$data['merchant_id'] 	= $request->merchant_id;
					$data['employee_id'] 	= $request->employee_id;
					$data['client_id'] 		= $request->client_id;
					$data['api_key'] 		= $cloverAccessToken;// access_token
					$data['pg_name'] 		= 'clover';
					$data['status'] 		= 1; //'active'
					$data['created_at'] 	= date('Y-m-d H:i:s');
					$data['updated_at'] 	= date('Y-m-d H:i:s');
					PaymentGatewaySetting::create($data);
				}
			
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
	            $message.= '<b>Name</b>: '.$request->name;
				$message.= '<br/>';
				$message.= '<b>Email</b>: '.$request->email;
				$message.= '<br/>';
				$message.= '<b>Contact No</b>: '.$request->contact_number;
				$message.= '<br/><br/>';
	            $message.=  'Thank you,<br/>Fuze Financial Team';
	            $message.=  '</span>';

				$params		= ['to_email' => $to_email1,
								'to_email_second_recipient' =>$to_email2,
	                            'subject' => $subject,
	                            'message' => $message,
								'from_name' => $request->name,
	                            ];
	            // send mail
				$emailMsg 	= $this->sendMail($params);
				
				$response['status']		= true;
				$response['message'] 	= Config::get('constants.messages.registrationSuccess'); 
				return response()->json($response, 200); 
			} else {
				return response()->json(['status' => false,'message' => Config::get('constants.messages.wrong')]);
			}
        }
		
		
	}

	public function sendMail($params) {
        $fromEmail	= (isset($params['from_email']) && $params['from_email']!="") ? $params['from_email'] : config('mail.from.address');
		$fromName 	= (isset($params['from_name']) && $params['from_name']!="") ? $params['from_name'] : config('mail.from.name');
		
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
			$mail->Subject 	= $params['subject']; 

			/* Set the mail message body. */
			$mail->Body 	= $params['message'];

			/* Finally send the mail. */
			$result = $mail->send();
			if($result){
					$res['response']= $result;
					$res['error'] 	= 0;
					$res['msg'] 	= 'Message send successfully.';
			}else{
					$res['response'] = 0;
					$res['error'] 	= 1;
					$res['msg'] 	= 'Message could not be sent.';
			}

		}catch (Exception $ex) {
			$res['response']	= '';
			$res['error'] 		= 1;
			$res['msg'] 		= $ex->getMessage();
		}
	}

	public function equipmentTypeList() {
		$list = \App\EquipmentType::where('status','=',1)->get();
		$list = !empty($list) ? $list->toArray():[];
		return response()->json(['status' => true, 'list' => $list]);
	}

	public function checkValue(Request $request) {
		//return response()->json($request->all());
		$save = DB::table('store_clover_response')->insert([
			'data' => json_encode($request->all()),
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);

	}

	public function merchantList(Request $request) {
		$merchant = User::select(DB::Raw("id,merchant_first_name,merchant_last_name,name,REGEXP_REPLACE(contact_number,'[^\w]+','','g')  "))
					->where(['status' => 1, 'delete_status' => 0, 'user_type' => 2 ])
					->get();
		return response()->json($merchant);
	}
		
}
