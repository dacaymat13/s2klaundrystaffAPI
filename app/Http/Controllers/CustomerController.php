<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class CustomerController extends Controller
{
    // ---------------- For Sign-Up ---------------------------
    public function CustRegister(Request $request){
        $request->validate([
            'Cust_email'=> 'required|email|unique:customers',
            'Cust_fname'=> 'required|max:255',
            'Cust_mname'=> 'string|max:255',
            'Cust_lname'=> 'required|max:255',
            'Cust_phoneno'=> 'required|max:255',
            'Cust_address'=> 'required|max:255',
            'Cust_password'=> 'required|confirmed'
        ]);
        // $register = DB::table('customers')->insert($formField);
        // return 'Registered';

        $Custdata = $request->except('Cust_password_confirmation');
        $Custdata['Cust_password'] = Hash::make($request->Cust_password);

        $customer = DB::table('customers')->insertGetId($Custdata);

        $customerData = DB::table('customers')->where('Cust_ID', $customer)->first();

        $custList = DB::table('customers')->orderBy('created_at','desc')->get();

        return response()->json([
            'message' => 'Customer registered successfully',
            'customer' => $customerData,
            'custList' => $custList
        ], 201);
    }


    // ----------- For Getting Data of the Customer who Login ------------
    public function getCustUser($id){
        $custUser = DB::table('customers')
            ->select('*')
            ->where('Cust_ID', $id)
            ->get();

        return $custUser;
    }


    // ----------- For Getting the Laundry Categorie ---------------
    // ----- For New/Update Transaction(select), Display in Home Pricelist -----
    public function getLaundryCateg(){
        $laundryCateg = DB::table('laundry_categories')
            ->select('*')
            ->get();

        return $laundryCateg;
    }


    // -------- Creating Tracking Number ---------
    public function getTrackingNo(){
        $trackNo = DB::table('transactions')
        ->selectRaw("
            CONCAT(
                'S2K-',
                SUBSTRING(UPPER(MD5(RAND())), 1, 2),
                CHAR(64 + MONTH(NOW())),
                LPAD(DAY(NOW()), 2, '0'),
                (SELECT COUNT(*) + 1
                FROM transactions
                WHERE DAY(Transac_datetime) = DAY(NOW())
                AND MONTH(Transac_datetime) = MONTH(NOW())
                AND YEAR(Transac_datetime) = YEAR(NOW())),
                LPAD(Transac_ID, 6, '0')
            ) AS TrackingNumber
        ")
        ->orderByDesc('Transac_ID')
        ->limit(1)
        ->value('TrackingNumber');

        return response()->json($trackNo,200);
    }

    public function AddNewCustTransac(Request $request)
{
    try {
        Log::info('AddNewCustTransac method started.', ['request' => $request->all()]);

        $validatedData = $request->validate([
            'custid' => 'required',
            'trackingNo' => 'required',
        ]);

        Log::info('Request validated successfully.', ['validatedData' => $validatedData]);

        $timezone = 'Asia/Manila';
        $localTime = Carbon::now($timezone);

        $newTransac = DB::table('transactions')->insertGetId([
            'Cust_ID' => $validatedData['custid'],
            'Transac_datetime' => $localTime,
            'Tracking_number' => $validatedData['trackingNo'],
        ]);

        Log::info('Transaction inserted.', ['Transac_ID' => $newTransac]);

        $newTransDateTime = DB::table('transactions')
            ->where('Transac_ID', $newTransac)
            ->value('Transac_datetime');

        $transacStatus = 'pending';

        if ($newTransac) {
            DB::table('transaction_status')->insert([
                'Transac_ID' => $newTransac,
                'TransacStatus_name' => $transacStatus,
                'TransacStatus_datetime' => $newTransDateTime,
                'Admin_ID' => null,
            ]);

            Log::info('Transaction status inserted.', ['Transac_ID' => $newTransac, 'Status' => $transacStatus]);

            foreach ($request->dataSelEntry as $laundryDetail) {
                DB::table('transaction_details')->insert([
                    'Categ_ID' => $laundryDetail['laundryCategory'],
                    'Transac_ID' => $newTransac,
                    'Qty' => $laundryDetail['quantity'],
                ]);
            }

            Log::info('Transaction details inserted.', ['Details' => $request->dataSelEntry]);

            $insertData = array_map(function ($service) use ($newTransac) {
                return [
                    'Transac_ID' => $newTransac,
                    'AddService_name' => $service,
                ];
            }, $request->dataSelAdd);

            DB::table('additional_services')->insert($insertData);

            Log::info('Additional services inserted.', ['AdditionalServices' => $request->dataSelAdd]);
        }

        if ($newTransac) {
            Log::info('New transaction successfully created.', ['Transac_ID' => $newTransac]);
            return response()->json('New transaction created with Id: ' . $newTransac, 200);
        } else {
            Log::error('Transaction creation failed.');
            return response()->json(['message' => 'Insert failed.'], 500);
        }
    } catch (\Exception $e) {
        Log::error('Error occurred in AddNewCustTransac.', ['exception' => $e->getMessage()]);
        return response()->json(['message' => 'An error occurred.', 'error' => $e->getMessage()], 500);
    }
}

    public function getCurrentTransactions($id){

    }
}
