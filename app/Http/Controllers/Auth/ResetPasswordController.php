<?php


namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Hash;

class ResetPasswordController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('guest');
    }
	
	public function change_password(Request $request) {
		
		// Apply validator
		$validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
			'new_password' => 'required|string',
			'confirm_password' => 'required|string|same:new_password'
        ]);
		
		// check if validator passes
		if($validator->passes())
		{
			$userdata = Auth::user();
			if (Hash::check($request->old_password, $userdata->password)) {
				$userdata->password = bcrypt($request->new_password);
				$userdata->save();
				$response['success']= true;
				$response['msg'] 	= "Your Password has been updated successfully!";
			} else {
				$response['success']= false;
				$response['msg'] 	= "Your old password is incorrect. Please try again!";
			}
		}
		else
		{
			$response['success']= false;
			$response['msg'] 	= "Passwords do not match! Please try again.";
		}
        return response()->json($response, 200);
    }
}
