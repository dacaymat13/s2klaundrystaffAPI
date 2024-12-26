<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
// use App\Models\Customer;

class Customer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // if(DB::table('customers')->where('id', auth()->id())->where('Cust_email')->exists()){
        //     return $next($request);
        // }

        // abort(401);

        $email = auth()->user()->Cust_email;
        $exists = DB::table('customers')
                    ->where('Cust_email', $email)
                    ->exists();

        if ($exists) {
            return $next($request);
        }

        abort(401);
        // return response()->json(['error' => 'Email not found in customer list'], 403);
    }
}
