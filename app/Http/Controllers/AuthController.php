<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use App\Models\Customer;
use App\Models\Admin;

class AuthController extends Controller
{
    public function AdminLogin(Request $request){
        $request->validate([
            'email'=>"required|email|exists:admins,Email",
            "password"=>"required"
        ]);

        $user = Admin::where('Email', $request->email)->first();

        if(!$user || !Hash::check($request->password, $user->Password)){
            return response()->json([
                'message' => "The provided credentials are incorrect"
            ], 401);
        }

        $token = $user->createToken($user->Admin_lname);

        return response()->json([
            'user'=>$user,
            'token'=>$token->plainTextToken
        ]);
    }

    public function CustLogin(Request $request){
        $request->validate([
            'email'=> 'required|email|exists:customers,Cust_email',
            'password'=> 'required'
        ]);
        // $user = DB::table('customers')->where('Cust_email', $request->email)->first();
        $user = Customer::where('Cust_email', $request->email)->first();

        if(!$user || !Hash::check($request->password, $user->Cust_password)){
            return response()->json([
                'message' => "The provided credentials are incorrect"
            ], 401);
        }

        $custid = $user->Cust_ID;
        $token = $user->createToken($user->Cust_lname);
        return [
            'user'=>$user,
            'Cust_ID'=>$custid,
            'token'=>$token->plainTextToken
        ];
    }

    public function Logout(Request $request){
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        $personalAccessToken = PersonalAccessToken::findToken($token);

        if (!$personalAccessToken) {
            return response()->json(['error' => 'Token not found or invalid'], 401);
        }

        if ($request->user()->Admin_ID !== $personalAccessToken->tokenable_id) {
            return response()->json(['error' => 'Token does not belong to the authenticated user'], 403);
        }

        $personalAccessToken->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}
