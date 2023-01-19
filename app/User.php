<?php
##################
# 	User Model   #
##################
namespace App;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use PHPMailer\PHPMailer\PHPMailer;
use DB;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password','status','contact_number','user_type','credits','monthly_credit_quota','add_to_monthly_credit_quota','one_time_credit_add','total_credits_this_month','monthly_credit_create_date','monthly_credit_next_date','merchant_first_name','merchant_last_name','facebook_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
	 'remember_token',
    ];
    
	protected $table = 'users';
    protected $guarded = ["id"];

    const TABLE = 'users';
	
	public static function isActive($email){
        return DB::table(self::TABLE . '')
        ->where('email', $email)
		->where('status', 1)
		->where('delete_status', 0)
		->pluck('id')->first();
    }
	
	/**
	 * Send an Registration Email.
	 *
	 * @param  Request  $request
	 * @param  int  $id
	 * @return Response
	 */
	static function sendMail($params=[])
	{
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
	
	/**
	 * Change password of User.
	 *
	 * @return true/false
	*/
	static function changePassword($user_id,$password){

		$fields['password'] = $password ? Hash::make($password): "";
		if(isset($user_id) && $fields['password']){return User::where('id', $user_id)->update($fields);}else{return false;}
	}
	
	/**
	 * Generate password string.
	 *
	 * @return password string
	*/
	static function generatePassword($len=6)
	{
		$charSet 	= "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUV0123456789";
		$shuffStr	= str_shuffle($charSet);
		$password 	= substr($shuffStr, 0, $len);
		return $password;
	}
}
