<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Auth;
use App\User;
class Active
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(!empty(Auth::user() && (Auth::user()->status == 0 || Auth::user()->delete_status == 1))) {				
            $response['status']= 'deactive';
            $response['msg'] 	= 'Your account is inactive. Please contact your administrator to activate it.';					           
            return response()->json($response,200);
        }
        return $next($request);
    }
}
