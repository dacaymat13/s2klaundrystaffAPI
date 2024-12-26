<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class StaffController extends Controller
{
    public function getCustTransacHistory($id){
        $trans = DB::table('transactions')
            ->leftJoin('customers', 'customers.Cust_ID', '=', 'transactions.Cust_ID')
            ->leftJoin('customer_address', 'customers.Cust_ID', '=', 'customer_address.Cust_ID')
            ->leftJoin('transaction_details', 'transactions.Transac_ID', '=', 'transaction_details.Transac_ID')
            ->leftJoin('additional_services', 'transactions.Transac_ID', '=', 'additional_services.Transac_ID')
            ->leftJoin('laundry_categories', 'laundry_categories.Categ_ID', '=', 'transaction_details.Categ_ID')
            ->leftJoin('payments', 'transactions.Transac_ID', '=', 'payments.Transac_ID')
            ->leftJoin('proof_of_payments', 'payments.Payment_ID', '=', 'proof_of_payments.Payment_ID')
            ->leftJoin('admins', 'transactions.Admin_ID', '=', 'admins.Admin_ID')
            ->leftJoin(DB::raw("(SELECT Transac_ID, TransacStatus_name, MAX(TransacStatus_datetime) AS latest_status_date
                                FROM transaction_status
                                GROUP BY Transac_ID, TransacStatus_name) AS latest_status"),
                    'transactions.Transac_ID', '=', 'latest_status.Transac_ID')
            ->select(
                DB::raw("COUNT('*') AS TotTransacs"),
                'transactions.Transac_ID',
                'customers.Cust_ID',
                DB::raw("CONCAT(customers.Cust_fname, ' ', customers.Cust_mname, ' ', customers.Cust_lname) AS CustomerName"),
                'customers.Cust_fname',
                'customers.Cust_mname',
                'customers.Cust_lname',
                'customers.Cust_phoneno',
                DB::raw("CONCAT(customer_address.BuildingUnitStreet_No, ', ', customer_address.Barangay, ', ', customer_address.Town_City, ', ', customer_address.Province) AS CustAddress"),
                'customers.Cust_image',
                'customers.Cust_email',
                'transactions.Tracking_number',
                'transactions.Transac_datetime',
                'latest_status.TransacStatus_name AS latest_transac_status',
                DB::raw("IF(payments.Mode_of_Payment IS NULL, 'unpaid', payments.Mode_of_Payment) AS payment"),
                // receiving_type
                DB::raw("CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM additional_services
                                WHERE additional_services.AddService_name = 'PickUp-Service'
                                AND additional_services.Transac_ID = transactions.Transac_ID
                            )
                            THEN 'Pick-up Service'
                            ELSE 'Drop-off'
                        END AS receiving_type"),
                // releasing_type
                DB::raw("CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM additional_services
                                WHERE additional_services.AddService_name = 'Delivery-Service'
                                AND additional_services.Transac_ID = transactions.Transac_ID
                            )
                            THEN 'Delivery Service'
                            ELSE 'Customer Pick-up'
                        END AS releasing_type")
            )
            ->where('customers.Cust_ID', $id)
            ->groupBy(
                'transactions.Transac_ID',
                'customers.Cust_ID',
                'customers.Cust_fname',
                'customers.Cust_mname',
                'customers.Cust_lname',
                'customers.Cust_phoneno',
                'customer_address.Province',
                'customer_address.BuildingUnitStreet_No',
                'customer_address.Barangay',
                'customer_address.Town_City',
                'customers.Cust_image',
                'customers.Cust_email',
                'transactions.Tracking_number',
                'transactions.Transac_datetime',
                'latest_status.TransacStatus_name',
                'payments.Mode_of_Payment'
            )
            ->get();


        if(is_null($trans)){
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($trans,200);
    }

    public function getTransactionsRec(){
            $trans = DB::table('transactions')
                ->join('customers', 'customers.Cust_ID', '=', 'transactions.Cust_ID')
                // ->leftJoin('customer_address', 'customer_address.Cust_ID', '=', 'customers.Cust_ID')
                ->leftJoin('transaction_details', 'transactions.Transac_ID', '=', 'transaction_details.Transac_ID')
                ->leftJoin('transaction_status', function ($join) {
                    $join->on('transactions.Transac_ID', '=', 'transaction_status.Transac_ID')
                         ->whereRaw('transaction_status.TransacStatus_datetime = (
                             SELECT MAX(ts2.TransacStatus_datetime)
                             FROM transaction_status ts2
                             WHERE ts2.Transac_ID = transaction_status.Transac_ID
                         )');
                })
                ->join('additional_services', 'transactions.Transac_ID', '=', 'additional_services.Transac_ID')
                ->join('shipping_details', 'shipping_details.AddService_ID', '=', 'additional_services.AddService_ID')
                ->leftJoin('laundry_categories', 'laundry_categories.Categ_ID', '=', 'transaction_details.Categ_ID')
                ->leftJoin('payments', 'transactions.Transac_ID', '=', 'payments.Transac_ID')
                ->leftJoin('proof_of_payments', 'payments.Payment_ID', '=', 'proof_of_payments.Payment_ID')
                ->leftJoin('admins', 'transactions.Admin_ID', '=', 'admins.Admin_ID')
                ->select(
                    'admins.Admin_ID',
                    'transactions.Transac_ID',
                    'customers.Cust_ID',
                    DB::raw("CONCAT(customers.Cust_fname, ' ', customers.Cust_mname, ' ', customers.Cust_lname) AS CustomerName"),
                    'customers.Cust_fname',
                    'customers.Cust_mname',
                    'customers.Cust_lname',
                    'customers.Cust_phoneno',
                    // DB::raw("CONCAT(customer_address.BuildingUnitStreet_No, ', ', customer_address.Barangay, ', ', customer_address.Town_City, ', ', customer_address.Province) AS CustAddress"),
                    'customers.Cust_image',
                    'customers.Cust_email',
                    // 'additional_services.AddService_ID',
                    'proof_of_payments.Proof_filename',
                    'transactions.Tracking_number',
                    'transactions.Transac_datetime',
                    // DB::raw("(SELECT DISTINCT
                    //     CONCAT(pickup_ca.BuildingUnitStreet_No, ', ', pickup_ca.Barangay, ', ', pickup_ca.Town_City, ', ', pickup_ca.Province) 
                    //     FROM customer_address pickup_ca
                    //     INNER JOIN shipping_details sd ON sd.CustAdd_ID = pickup_ca.CustAdd_ID
                    //     WHERE sd.AddService_ID = (
                    //         SELECT AddService_ID 
                    //         FROM additional_services 
                    //         WHERE AddService_name = 'PickUp-Service' 
                    //         AND additional_services.Transac_ID = transactions.Transac_ID
                    //         LIMIT 1
                    //     )
                    // ) AS PickUpAddress"),
                    // DB::raw("(SELECT 
                    //     CONCAT(delivery_ca.BuildingUnitStreet_No, ', ', delivery_ca.Barangay, ', ', delivery_ca.Town_City, ', ', delivery_ca.Province) 
                    //     FROM customer_address delivery_ca
                    //     INNER JOIN shipping_details sd ON sd.CustAdd_ID = delivery_ca.CustAdd_ID
                    //     WHERE sd.AddService_ID = (
                    //         SELECT AddService_ID 
                    //         FROM additional_services 
                    //         WHERE AddService_name = 'Delivery-Service' 
                    //         AND additional_services.Transac_ID = transactions.Transac_ID
                    //         LIMIT 1
                    //     )
                    // ) AS DeliveryAddress"),
                    DB::raw("(
                        SELECT ts.TransacStatus_datetime
                        FROM transaction_status ts
                        WHERE ts.Transac_ID = transactions.Transac_ID
                        AND ts.TransacStatus_name = 'received'
                        ORDER BY ts.TransacStatus_datetime DESC
                        LIMIT 1
                    ) AS Received_datetime"),
                    DB::raw("CASE
                                WHEN EXISTS (
                                SELECT 1
                                FROM additional_services
                                WHERE additional_services.AddService_name = 'PickUp-Service'
                                AND additional_services.Transac_ID = transactions.Transac_ID
                                ) THEN 'Pick-Up Service' ELSE 'Drop-off' END AS receiving_type"),
                    DB::raw("SUM(DISTINCT transaction_details.Price) + SUM( additional_services.AddService_price) AS OverAllTotal"),
                    DB::raw("(SELECT SUM(DISTINCT transaction_details.Price) + SUM(DISTINCT additional_services.AddService_price) AS OverAllTotal) AS Overall"),
                    DB::raw("SUM(DISTINCT transaction_details.Price) as TotalPrice"), //optional
                    DB::raw("SUM(DISTINCT additional_services.AddService_price) as TotalService"), //optional
                    DB::raw("IF(payments.Mode_of_Payment IS NULL, 'unpaid', payments.Mode_of_Payment) AS payment"),
                    'transaction_status.TransacStatus_name AS latest_transac_status'
                )
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('transaction_status AS ts')
                          ->whereRaw('ts.Transac_ID = transactions.Transac_ID')
                          ->whereIn('ts.TransacStatus_name', ['completed', 'canceled', 'forRelease'])
                          ->where('ts.TransacStatus_datetime', '=', DB::raw('(SELECT MAX(ts2.TransacStatus_datetime) FROM transaction_status ts2 WHERE ts2.Transac_ID = ts.Transac_ID)'));
                })
                ->where(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('transaction_status as ts')
                          ->whereRaw('transaction_status.Transac_ID = transactions.Transac_ID')
                          ->whereIn('transaction_status.TransacStatus_name', ['pending', 'received', 'washing', 'folding'])
                          ->where('transaction_status.TransacStatus_datetime', '=', function ($subquery) {
                              $subquery->select(DB::raw('MAX(ts2.TransacStatus_datetime)'))
                                       ->from('transaction_status as ts2')
                                       ->whereRaw('ts2.Transac_ID = transaction_status.Transac_ID');
                          });
                })
                ->groupBy(
                    'transactions.Transac_ID',
                    'customers.Cust_ID',
                    'customers.Cust_fname',
                    'customers.Cust_mname',
                    'customers.Cust_lname',
                    'customers.Cust_phoneno',
                    // 'customer_address.Province',
                    // 'customer_address.BuildingUnitStreet_No',
                    // 'customer_address.Barangay',
                    // 'customer_address.Town_City',
                    // 'additional_services.AddService_ID',
                    'customers.Cust_email',
                    'customers.Cust_image',
                    'admins.Admin_ID',
                    'transaction_status.TransacStatus_name',
                    'transaction_status.TransacStatus_datetime',
                    'transaction_status.Transac_ID',
                    'transactions.Tracking_number',
                    'transactions.Transac_datetime',
                    'proof_of_payments.Proof_filename',
                    'payments.Mode_of_Payment'
                )
                ->get();

        if(is_null($trans)){
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($trans,200);
    }

    public function getTrasactionsRel(){
        $trans = DB::table('transactions')
            ->leftJoin('customers', 'customers.Cust_ID', '=', 'transactions.Cust_ID')
            ->leftJoin('customer_address', 'customer_address.Cust_ID', '=', 'customers.Cust_ID')
            ->leftJoin('transaction_details', 'transactions.Transac_ID', '=', 'transaction_details.Transac_ID')
            ->leftJoin('transaction_status', function ($join) {
                $join->on('transactions.Transac_ID', '=', 'transaction_status.Transac_ID')
                     ->whereRaw('transaction_status.TransacStatus_datetime = (
                         SELECT MAX(ts2.TransacStatus_datetime)
                         FROM transaction_status ts2
                         WHERE ts2.Transac_ID = transaction_status.Transac_ID
                     )');
            })
            ->leftJoin('additional_services', 'transactions.Transac_ID', '=', 'additional_services.Transac_ID')
            ->leftJoin('laundry_categories', 'laundry_categories.Categ_ID', '=', 'transaction_details.Categ_ID')
            ->leftJoin('payments', 'transactions.Transac_ID', '=', 'payments.Transac_ID')
            ->leftJoin('proof_of_payments', 'payments.Payment_ID', '=', 'proof_of_payments.Payment_ID')
            ->leftJoin('admins', 'transactions.Admin_ID', '=', 'admins.Admin_ID')
            ->select(
                'admins.Admin_ID',
                'transactions.Transac_ID',
                'customers.Cust_ID',
                DB::raw("CONCAT(customers.Cust_fname, ' ', customers.Cust_mname, ' ', customers.Cust_lname) AS CustomerName"),
                'customers.Cust_fname',
                'customers.Cust_mname',
                'customers.Cust_lname',
                'customers.Cust_phoneno',
                'additional_services.AddService_ID',
                // DB::raw("CONCAT(customer_address.BuildingUnitStreet_No, ', ', customer_address.Barangay, ', ', customer_address.Town_City, ', ', customer_address.Province) AS CustAddress"),
                'customers.Cust_image',
                'customers.Cust_email',
                'proof_of_payments.Proof_filename',
                'transactions.Tracking_number',
                'transactions.Transac_datetime',
                DB::raw("CASE
                            WHEN EXISTS (
                            SELECT 1
                            FROM additional_services
                            WHERE additional_services.AddService_name = 'Delivery-Service'
                            AND additional_services.Transac_ID = transactions.Transac_ID
                            ) THEN 'Delivery Service' ELSE 'Customer Pick-up' END AS releasing_type"),
                // DB::raw("SUM(DISTINCT transaction_details.Price) + SUM(DISTINCT COALESCE(additional_services.AddService_price, 0)) AS OverAllTotal"),
                DB::raw("(SELECT SUM(DISTINCT transaction_details.Price) + SUM(DISTINCT additional_services.AddService_price) AS OverAllTotal) AS Overall"),
                DB::raw("SUM(DISTINCT transaction_details.Price) as TotalPrice"), //reserve
                DB::raw("SUM(DISTINCT additional_services.AddService_price) as TotalService"), //reserve
                DB::raw("IF(payments.Mode_of_Payment IS NULL, 'unpaid', payments.Mode_of_Payment) AS payment"),
                'transaction_status.TransacStatus_name AS latest_transac_status',
            )
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('transaction_status AS ts')
                      ->whereRaw('ts.Transac_ID = transactions.Transac_ID')
                      ->whereIn('ts.TransacStatus_name', ['forRelease'])
                      ->where('ts.TransacStatus_datetime', '=', DB::raw('(SELECT MAX(ts2.TransacStatus_datetime) FROM transaction_status ts2 WHERE ts2.Transac_ID = ts.Transac_ID)'));
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('transaction_status AS ts')
                      ->whereRaw('ts.Transac_ID = transactions.Transac_ID')
                      ->whereIn('ts.TransacStatus_name', ['completed', 'canceled'])
                      ->where('ts.TransacStatus_datetime', '=', DB::raw('(SELECT MAX(ts2.TransacStatus_datetime) FROM transaction_status ts2 WHERE ts2.Transac_ID = ts.Transac_ID)'));
            })
            ->where('transaction_status.TransacStatus_name', 'forRelease')
            ->groupBy(
                'transactions.Transac_ID',
                'transactions.Admin_ID',
                'customers.Cust_ID',
                'customers.Cust_fname',
                'customers.Cust_mname',
                'customers.Cust_lname',
                'customers.Cust_phoneno',
                // 'customer_address.Province',
                // 'customer_address.BuildingUnitStreet_No',
                // 'customer_address.Barangay',
                // 'customer_address.Town_City',
                'additional_services.AddService_ID',
                'customers.Cust_email',
                'customers.Cust_image',
                'admins.Admin_ID',
                'transaction_status.TransacStatus_name',
                'transaction_status.TransacStatus_datetime',
                'transaction_status.Transac_ID',
                'transactions.Tracking_number',
                'proof_of_payments.Proof_filename',
                'transactions.Transac_datetime',
                'payments.Mode_of_Payment'
            )
            ->get();

        if(is_null($trans)){
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($trans,200);
    }



    //**** */
    public function showTransCust($id){
        $trans = DB::table('transactions')
            ->leftJoin('customers', 'customers.Cust_ID', '=', 'transactions.Cust_ID')
            ->leftJoin('customer_address', 'customer_address.Cust_ID', '=', 'customers.Cust_ID')
            ->leftJoin('transaction_details', 'transactions.Transac_ID', '=', 'transaction_details.Transac_ID')
            ->leftJoin('transaction_status', 'transactions.Transac_ID', '=', 'transaction_status.Transac_ID')
            ->leftJoin('additional_services', 'transactions.Transac_ID', '=', 'additional_services.Transac_ID')
            ->leftJoin('laundry_categories', 'laundry_categories.Categ_ID', '=', 'transaction_details.Categ_ID')
            ->leftJoin('payments', 'transactions.Transac_ID', '=', 'payments.Transac_ID')
            ->leftJoin('proof_of_payments', 'payments.Payment_ID', '=', 'proof_of_payments.Payment_ID')
            ->leftJoin('admins', 'transactions.Admin_ID', '=', 'admins.Admin_ID')
            ->select(
                'transactions.Transac_ID',
                'customers.Cust_ID',
                DB::raw("CONCAT(customers.Cust_fname, ' ', customers.Cust_mname, ' ', customers.Cust_lname) AS CustomerName"),
                'customers.Cust_fname',
                'customers.Cust_mname',
                'customers.Cust_lname',
                'customers.Cust_phoneno',
                DB::raw("CONCAT(customer_address.BuildingUnitStreet_No, ', ', customer_address.Barangay, ', ', customer_address.Town_City, ', ', customer_address.Province) AS CustAddress"),
                'customers.Cust_image',
                'customers.Cust_email',
                'transactions.Tracking_number',
                'transactions.Transac_datetime',
                DB::raw("(SELECT TransacStatus_name
                    FROM transaction_status
                    WHERE transaction_status.Transac_ID = transactions.Transac_ID
                    AND transaction_status.TransacStatus_datetime = (
                        SELECT MAX(transaction_status.TransacStatus_datetime)
                        FROM transaction_status
                        WHERE transaction_status.Transac_ID = transactions.Transac_ID
                    )
                    LIMIT 1) AS latest_transac_status"),
                DB::raw("SUM(CASE
                    WHEN transaction_status.TransacStatus_datetime IS NOT NULL
                        AND transaction_status.TransacStatus_name = 'received'
                        AND transactions.Admin_ID = admins.Admin_ID
                    THEN transaction_details.Price ELSE 0 END) AS amount"),
                DB::raw("SUM(DISTINCT transaction_details.Price) as TotalPrice"),
                DB::raw("SUM(DISTINCT additional_services.AddService_price) as TotalService"),
                DB::raw("CASE
                    WHEN transaction_status.TransacStatus_name = 'received'
                        AND transactions.Admin_ID = admins.Admin_ID
                    THEN transaction_status.TransacStatus_datetime ELSE NULL END AS Received_datetime"),
                DB::raw("IF(payments.Mode_of_Payment IS NULL, 'unpaid', payments.Mode_of_Payment) AS payment"),
                DB::raw("CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM additional_services
                        WHERE additional_services.AddService_name = 'PickUp-Service'
                        AND additional_services.Transac_ID = transactions.Transac_ID
                    ) THEN 'Pick-up Service' ELSE 'Drop-off' END AS receiving_type")
            )
            ->whereNotIn('transaction_status.TransacStatus_name', ['completed', 'canceled', 'forRelease'])
            ->where('transactions.Transac_ID', $id)
            ->groupBy(
                'transactions.Transac_ID',
                'customers.Cust_ID',
                'customers.Cust_fname',
                'customers.Cust_mname',
                'customers.Cust_lname',
                'customers.Cust_phoneno',
                'customer_address.Province',
                'customer_address.BuildingUnitStreet_No',
                'customer_address.Barangay',
                'customer_address.Town_City',
                'customers.Cust_email',
                'customers.Cust_image',
                'admins.Admin_ID',
                'transactions.Admin_ID',
                'transaction_status.TransacStatus_name',
                'transactions.Tracking_number',
                'transactions.Transac_datetime',
                'transaction_status.TransacStatus_name',
                'transaction_status.TransacStatus_datetime',
                'payments.Mode_of_Payment'
            )
            ->get();

        if(is_null($trans)){
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($trans,200);
    }


    public function showLaundryDetails($id){
        $laundryDetails = DB::table('transaction_details')
            ->join('laundry_categories', 'transaction_details.Categ_ID', '=', 'laundry_categories.Categ_ID')
            ->select(
                'laundry_categories.Categ_ID',
                'laundry_categories.Category',
                'laundry_categories.Price',
                'laundry_categories.Minimum_weight',
                'transaction_details.Qty',
                'transaction_details.Weight',
                'transaction_details.Price AS EachTotalPrice',
                'transaction_details.Price AS EachPrice',
                DB::raw('(laundry_categories.Price * laundry_categories.Minimum_weight) as PriceperMin'),
                DB::raw('SUM(DISTINCT transaction_details.Price) AS TotalPrice'),
                'transaction_details.TransacDet_ID'
            )
            ->where('transaction_details.Transac_ID', $id)
            ->groupBy(
                'laundry_categories.Categ_ID',
                'laundry_categories.Category',
                'laundry_categories.Price',
                'laundry_categories.Minimum_weight',
                'transaction_details.Qty',
                'transaction_details.Weight',
                'transaction_details.Price',
                'transaction_details.TransacDet_ID'
            )
            ->get();

            if(is_null($laundryDetails)){
                return response()->json(['message' => 'Laundry Details not found'], 404);
            }
            return response()->json($laundryDetails,200);
    }

    // public function getAddService($id){
    //     $service = DB::table('additional_services')
    //     ->select(
    //         'AddService_ID',
    //         'AddService_name AS result',
    //         'AddService_price AS service_price',
    //         DB::raw('SUM(AddService_price) AS TotalPrice'),
    //         'Transac_ID'
    //     )
    //     ->groupBy('Transac_ID', 'AddService_ID', 'AddService_name', 'AddService_price')
    //     ->where('Transac_ID', $id)
    //     ->unionAll(
    //         DB::table(DB::raw('(SELECT NULL AS AddService_ID, "none" AS result, NULL AS service_price, 0 as TotalPrice, NULL AS Transac_ID) AS sub'))
    //         ->whereNotExists(function ($query) use ($id) {
    //             $query->select(DB::raw(1))
    //                 ->from('additional_services')
    //                 ->where('Transac_ID', $id);
    //         })
    //     )
    //     ->get();

    //     if ($service->isEmpty()) {
    //         return response()->json([
    //             'data' => 0,
    //             'message' => 'Additional Service not found'], 404);
    //     }

    //     return response()->json($service, 200);
    // }

    public function getAddService($id)
    {
        $service = DB::table('additional_services')
            ->leftJoin('shipping_details', 'additional_services.AddService_ID', '=', 'shipping_details.AddService_ID')
            ->leftJoin('customer_address AS pickup_ca', function ($join) {
                $join->on('shipping_details.CustAdd_ID', '=', 'pickup_ca.CustAdd_ID')
                    ->where('additional_services.AddService_name', '=', 'PickUp-Service');
            })
            ->leftJoin('customer_address AS delivery_ca', function ($join) {
                $join->on('shipping_details.CustAdd_ID', '=', 'delivery_ca.CustAdd_ID')
                    ->where('additional_services.AddService_name', '=', 'Delivery-Service');
            })
            ->select(
                'additional_services.AddService_ID',
                DB::raw("CASE 
                            WHEN additional_services.AddService_name = 'PickUp-Service' THEN 'Pick-Up Service'
                            WHEN additional_services.AddService_name = 'Delivery-Service' THEN 'Delivery Service'
                            WHEN additional_services.AddService_name = 'Rush-Jobs' THEN 'Rush Jobs'
                             
                        END AS AddServiceName"),
                'additional_services.AddService_price AS service_price',
                DB::raw('SUM(DISTINCT additional_services.AddService_price) AS TotalPrice'),
                'additional_services.Transac_ID',
                DB::raw("CASE 
                            WHEN additional_services.AddService_name = 'PickUp-Service' THEN 
                                CONCAT(pickup_ca.BuildingUnitStreet_No, ', ', pickup_ca.Barangay, ', ', pickup_ca.Town_City, ', ', pickup_ca.Province)
                            WHEN additional_services.AddService_name = 'Delivery-Service' THEN 
                                CONCAT(delivery_ca.BuildingUnitStreet_No, ', ', delivery_ca.Barangay, ', ', delivery_ca.Town_City, ', ', delivery_ca.Province)
                            
                         END AS Address")
            )
            ->groupBy(
                'additional_services.Transac_ID',
                'additional_services.AddService_ID',
                'additional_services.AddService_name',
                'additional_services.AddService_price',
                'pickup_ca.BuildingUnitStreet_No',
                'pickup_ca.Barangay',
                'pickup_ca.Town_City',
                'pickup_ca.Province',
                'delivery_ca.BuildingUnitStreet_No',
                'delivery_ca.Barangay',
                'delivery_ca.Town_City',
                'delivery_ca.Province'
            )
            ->where('additional_services.Transac_ID', $id)
    
            // Union All with matching number of columns
            ->unionAll(
                DB::table(DB::raw('(SELECT NULL AS AddService_ID, null AS AddServiceName, NULL AS service_price, 0 as TotalPrice, NULL AS Transac_ID, NULL AS Address) AS sub'))
                    ->whereNotExists(function ($query) use ($id) {
                        $query->select(DB::raw(1))
                            ->from('additional_services')
                            ->where('Transac_ID', $id);
                    })
            )
            ->get();
    
        // If the service data is empty, return 404
        if ($service->isEmpty()) {
            return response()->json([
                'data' => 0
            ], 200);
        }
    
        // Return the services data with 200 OK
        return response()->json([
            'data' => $service
        ], 200);
    }



    public function totalPriceLaundry($id){
        try {
            Log::info('totalPriceLaundry method started.', ['Transac_ID' => $id]);

            $totalPrice = DB::table('transaction_details')
                ->where('Transac_ID', $id)
                ->select(
                    DB::raw("SUM(Price) as LaundryTotal")
                )
                ->get();
                // ->sum('Price');

            Log::info('Total price calculated.', ['Transac_ID' => $id, 'TotalPrice' => $totalPrice]);

            if ($totalPrice === 0) { 
                Log::warning('No laundry total found for the transaction.', ['Transac_ID' => $id]);
                return response()->json(['message' => 'No laundry total found for this transaction.'], 404);
            }

            Log::info('Laundry total retrieved successfully.', ['Transac_ID' => $id, 'LaundryTotal' => $totalPrice]);

            return response()->json($totalPrice, 200);
        } catch (\Exception $e) {
            Log::error('Error occurred in totalPriceLaundry.', ['Transac_ID' => $id, 'exception' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAddServiceTotal($id)
    {
        $totalPrice = DB::table('additional_services')
            ->where('Transac_ID', $id)
            ->sum('AddService_price');

        // If there are no services, return 0 or a suitable response
        if ($totalPrice == 0) {
            return response()->json([
                'data' => 0,
                'message' => 'No additional services found'
            ], 200);
        }

        // Return the total price with 200 OK
        return response()->json([
            'data' => $totalPrice,
            'message' => 'Total price of additional services retrieved successfully'
        ], 200);
    }

    public function getLaundryDetTotal($id){
        $totalPrice = DB::table('transaction_details')
            ->where('Transac_ID', $id)
            ->sum('Price');

        // If there are no services, return 0 or a suitable response
        if ($totalPrice == 0) {
            return response()->json([
                'data' => 0,
                'message' => 'No additional services found'
            ], 404);
        }

        // Return the total price with 200 OK
        return response()->json([
            'data' => $totalPrice,
            'message' => 'Total price of additional services retrieved successfully'
        ], 200);
    }

    public function saveLaundryDetails(Request $request)
    {
        foreach ($request->laundryDetails as $laundryDetail) {
            DB::table('transaction_details')
                ->where('TransacDet_ID', $laundryDetail['TransacDet_ID'])
                ->update([
                    'Weight' => $laundryDetail['WeightEdit'],
                    'Price' => $laundryDetail['LaundryChargeEdit'],
                ]);
        }

        return response()->json(['message' => 'Laundry details updated successfully.']);
    }

    public function saveServiceData(Request $request)
    {
        foreach ($request->services as $services) {
            DB::table('additional_services')
                ->where('Transac_ID', $services['Transac_ID'])
                ->where('AddService_ID', $services['AddService_ID'])
                ->update([
                    'AddService_price' => $services['AddService_price']
                ]);
        }

        return response()->json(['message' => 'Laundry details updated successfully.']);
    }

    public function submitLaundryTrans(Request $request, $id){

        $validatedData = $request->validate([
            'staffId' => 'required',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $staffId = $validatedData['staffId'];
        $amount = $request->input('amount', 0);

        try {
            $timezone = 'Asia/Manila';
            $localTime = Carbon::now($timezone);

            $result = DB::transaction(function () use ($id, $staffId, $amount, $localTime) {

                $updated = DB::table('transactions')
                    ->where('Transac_ID', $id)
                    ->update([
                        'Admin_ID' => $staffId,
                    ]);

                $insertTransStatus = DB::table('transaction_status')
                    ->insert([
                        'Transac_ID' => $id,
                        'Admin_ID' => $staffId,
                        'TransacStatus_name' => 'received',
                        'TransacStatus_datetime' => $localTime,
                    ]);

                $insertPayment = true;
                if ($amount > 0) {
                    $insertPayment = DB::table('payments')
                        ->insert([
                            'Transac_ID' => $id,
                            'Admin_ID' => $staffId,
                            'Amount' => $amount,
                            'Mode_of_Payment' => 'cash',
                            'Datetime_of_Payment' => $localTime,
                        ]);
                }

                return $updated > 0 || $insertTransStatus > 0 || $insertPayment;
            });

            if ($result) {
                return response()->json(['message' => 'Laundry Details updated successfully.'], 200);
            } else {
                return response()->json(['message' => 'No changes were made.'], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




    public function addPayment(Request $request){
        $validatedData = $request->validate([
            'id'=> 'required',
            'total' => 'required',
            'staffId' => 'required'
        ]);

        $timezone = 'Asia/Manila';
        $localTime = Carbon::now($timezone);

        $payment = DB::table('payments')
        ->insert([
            'Transac_ID' => $validatedData['id'],
            'Amount' => $validatedData['total'],
            'Admin_ID' => $validatedData['staffId'],
            'Mode_of_Payment' => 'cash',
            'Payment_status' => 'approved',
            'Datetime_of_Payment' => $localTime]);

            if ($payment) {
                return response()->json(['message' => 'Payment entered successfully.'], 200);
            } else {
                return response()->json(['message' => 'No changes were made.'], 200);
            }
    }

    // public function updateStatus(Request $request, $id){
    //     $validatedData = $request->validate([
    //         'status'=> 'required',
    //         'staffID' => 'required'
    //     ]);


    //     $validatedData['staffID'] != null;
    //     $staffId = $validatedData['staffID'];

    //     $localTime = Carbon::now();

    //     $statusDatetime = DB::table('transaction_status')
    //         ->where('Transac_ID', $id)
    //         ->first();

    //     if($validatedData['status'] === 'received'){
    //         $del = DB::table('transaction_status')
    //             ->where('Transac_ID', $id)
    //             ->whereIn('TransacStatus_name', ['washing', 'folding', 'forRelease', 'completed'])
    //             ->delete();
    //     }

    //     if($validatedData['status'] === 'washing'){
    //         $del = DB::table('transaction_status')
    //             ->where('Transac_ID', $id)
    //             ->where('TransacStatus_name', ['folding', 'forRelease', 'completed'])
    //             ->delete();
    //     }

    //     if($validatedData['status'] === 'folding'){
    //         $del = DB::table('transaction_status')
    //             ->where('Transac_ID', $id)
    //             ->where('TransacStatus_name', ['forRelease', 'completed'])
    //             ->delete();
    //     }


    //         $status = DB::table('transaction_status')
    //         ->updateOrInsert(
    //             [
    //                 'Transac_ID' => $id,
    //                 'Admin_ID' => $staffId,
    //                 'TransacStatus_name' => $validatedData['status']
    //             ],
    //             [
    //                 'Transac_ID'=> $id,
    //                 'Admin_ID' => $staffId,
    //                 'TransacStatus_name' => $validatedData['status'],
    //                 'TransacStatus_datetime' => $localTime
    //             ]
    //         );
            

    //     return response()->json(['message' => 'Transaction status updated successfully.'], 200);
    // }

    public function updateStatus(Request $request, $id)
    {
        $validatedData = $request->validate([
            'status' => 'required',
            'staffID' => 'required'
        ]);

        $staffId = $validatedData['staffID'];
        $localTime = Carbon::now();

        $currentStatus = DB::table('transaction_status')
            ->where('Transac_ID', $id)
            ->first();

        $statusesToDelete = [];

        if ($validatedData['status'] === 'received') {
            $statusesToDelete = ['washing', 'folding', 'forRelease', 'completed'];
        } elseif ($validatedData['status'] === 'washing') {
            $statusesToDelete = ['folding', 'forRelease', 'completed'];
        } elseif ($validatedData['status'] === 'folding') {
            $statusesToDelete = ['forRelease', 'completed'];
        }

        if (!empty($statusesToDelete)) {
            DB::table('transaction_status')
                ->where('Transac_ID', $id)
                ->whereIn('TransacStatus_name', $statusesToDelete)
                ->delete();
        }

        if ($currentStatus && $currentStatus->TransacStatus_name !== $validatedData['status']) {
            DB::table('transaction_status')
                ->updateOrInsert(
                    ['Transac_ID' => $id, 'TransacStatus_name' => $validatedData['status']],
                    [
                        'Admin_ID' => $staffId,
                        'TransacStatus_datetime' => $localTime
                    ]
                );
        }

        return response()->json(['message' => 'Transaction status updated successfully.'], 200);
    }





    public function getForRel($id){
        $laundryDetails = DB::table('transaction_details')
            ->join('laundry_categories', 'transaction_details.Categ_ID', '=', 'laundry_categories.Categ_ID')
            ->select(
                'laundry_categories.Categ_ID',
                'laundry_categories.Category',
                'laundry_categories.Price',
                'transaction_details.Qty',
                'transaction_details.Weight',
                'transaction_details.Price AS EachPrice',
                DB::raw('SUM(transaction_details.Price) AS TotalPrice'),
                'transaction_details.TransacDet_ID'
            )
            ->where('transaction_details.Transac_ID', $id)
            ->groupBy(
                'laundry_categories.Categ_ID',
                'laundry_categories.Category',
                'laundry_categories.Price',
                'transaction_details.Qty',
                'transaction_details.Weight',
                'transaction_details.Price',
                'transaction_details.TransacDet_ID'
            )
            ->get();

            if(is_null($laundryDetails)){
                return response()->json(['message' => 'Laundry Details not found'], 404);
            }
            return response()->json($laundryDetails,200);

    }

    public function getAddServRel($id){
        $addServInfo = DB::table('additional_services')
            ->select(
                'AddService_ID',
                'Transac_ID',
                'AddService_name',
                'AddService_price',
                DB::raw('SUM(AddService_price) AS TotalServ_Price')
            )
            ->groupBy(
                'AddService_ID',
                'Transac_ID',
                'AddService_name',
                'AddService_price'
            )
            ->where('Transac_ID', $id)
            ->get();

        if(is_null($addServInfo)){
            return response()->json(['message' => 'No Additional Services are in for this transaction'], 404);
        }
        return response()->json($addServInfo, 200);
    }





    public function totalPriceService($id){
        $totalPrice = DB::table('additional_services')
            ->select(DB::raw('SUM(AddService_price) AS Total'))
            ->where('Transac_ID', $id)
            ->groupBy('Transac_ID')
            ->get();

            // if(is_null($totalPrice)){
            //     return response()->json(['message' => 'Laundry Details not found'], 404);
            // }
            if ($totalPrice->isEmpty()) {
                $totalPrice = 0;
            }

            return response()->json($totalPrice,200);
    }

    // public function paymentStatus($id){
    //     $paymentStatus = DB::table('payments')
    //         ->select(
    //             'Payment_ID',
    //             'Admin_ID',
    //             'Transac_ID',
    //             'Amount',
    //             'Mode_of_Payment',
    //             'Datetime_of_Payment'
    //         )
    //         ->where('Transac_ID', $id)
    //         ->first();

    //         return response()->json($paymentStatus, 200);
    // }

    public function paymentStatus($id)
    {
        $paymentStatus = DB::table('payments')
            ->select(
                'Payment_ID',
                'Admin_ID',
                'Transac_ID',
                'Amount',
                'Mode_of_Payment',
                'Datetime_of_Payment'
            )
            ->where('Transac_ID', $id)
            ->first();

        if (!$paymentStatus) {
            return response()->json(0, 200); // Return 0 if no record is found
        }

        return response()->json($paymentStatus, 200);
}












    

    public function getExpenses($id)
    {
        $expList = DB::table('expenses')
        ->leftJoin('admins', 'expenses.Admin_ID', '=', 'admins.Admin_ID')
        ->select(
            'expenses.Expense_ID',
            DB::raw("CONCAT(admins.Admin_fname, ' ', admins.Admin_mname, ' ', admins.Admin_lname) AS AdminName"),
            'expenses.Amount',
            'expenses.Desc_reason',
            'expenses.Receipt_filenameimg',
            'expenses.Datetime_taken',
            DB::raw("SUM(DISTINCT expenses.Amount) as overallPrice")
        )
        ->where('expenses.Admin_ID', $id)
        ->groupBy(
            'expenses.Expense_ID',
            'admins.Admin_fname',
            'admins.Admin_mname',
            'admins.Admin_lname',
            'expenses.Amount',
            'expenses.Desc_reason',
            'expenses.Receipt_filenameimg',
            'expenses.Datetime_taken'
        )
        ->get();

        return $expList;
    }

    public function addExpense(Request $request)
    {
        $request->validate([
            'Admin_ID' => "required",
            'Amount' => "required",
            'Desc_reason' => "required|max:255",
            'Receipt_filenameimg' => "required|string",
            'Datetime_taken' => "date"
        ]);

        $data = $request->all();

        $timezone = 'Asia/Manila';
        $localTime = Carbon::now($timezone);
        
        $data['Datetime_taken'] = $localTime;

        $exp = DB::table('expenses')
            ->insertGetId($data);

        return response()->json([
            'message' => 'Expense added Successfully',
            'Expense' => $exp
        ], 201);
    }

    public function uploadExpImg(Request $request)
    {
        $request->validate([
            'Expense_ID' => 'required',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp'
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            // $timezone = 'Asia/Manila';
            // $localTime = Carbon::now($timezone);
            
            $filename = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('expReceipts', $filename, 'public');

            $expense = DB::table('expenses')
                        ->where('Expense_ID', $request->input('Expense_ID'))
                        ->first();

            if (!$expense) {
                return response()->json([
                    'message' => 'Expense not found.'
                ], 404);
            }

            DB::table('expenses')
                ->where('Expense_ID', $request->input('Expense_ID'))
                ->update(['Receipt_filenameimg' => $filePath]);

            return response()->json([
                'message' => 'Expense Receipt Uploaded.',
                'file' => $filePath
            ], 200);
        }

        return response()->json([
            'message' => 'No file uploaded.'
        ], 400);
    }

    public function getExpReceipt($id)
    {
        $expList = DB::table('expenses')
        ->leftJoin('admins', 'expenses.Admin_ID', '=', 'admins.Admin_ID')
        ->select(
            'expenses.Expense_ID',
            DB::raw("CONCAT(admins.Admin_fname, ' ', admins.Admin_mname, ' ', admins.Admin_lname) AS AdminName"),
            'expenses.Amount',
            'expenses.Desc_reason',
            'expenses.Receipt_filenameimg',
            'expenses.Datetime_taken'
        )
        ->where('expenses.Expense_ID', $id)
        ->groupBy(
            'expenses.Expense_ID',
            'admins.Admin_fname',
            'admins.Admin_mname',
            'admins.Admin_lname',
            'expenses.Amount',
            'expenses.Desc_reason',
            'expenses.Receipt_filenameimg',
            'expenses.Datetime_taken'
        )
        ->get();

        return $expList;
    }



    // -------------- Customer page -------------------------

    public function getCustomer(){
        $custList = DB::table('customers')
            ->select('*')
            ->get();
        return $custList;
    }

    public function getTotalTransactions($id){
        $totalTransacs = DB::table('transactions')
            ->where('Cust_ID', $id)
            ->count();

        return response()->json($totalTransacs,200);
    }

    public function getCustomerData($id){
        $custData = DB::table('customers')
            ->select('*')
            ->where('Cust_ID', $id)
            ->first();

        if(is_null($custData)){
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json($custData, 200);
    }





    // public function getIncomeRepPayments($id){
    //     $curDate = Carbon::now();

    //     $payments = DB::table('payments')
    //         ->select(
    //             'Payment_ID',
    //             'Admin_ID',
    //             'Transac_ID',
    //             'Amount',
    //             'Mode_of_Payment',
    //             'Datetime_of_Payment',
    //             DB::raw("SUM(DISTINCT Amount) as totalDayPayments")
    //         )
    //         ->where('Admin_ID', $id)
    //         ->where('Datetime_of_Payment', $curDate)
    //         ->get();

    //     if (!$payments->isEmpty()) {
    //         return response()->json(['data' => $payments, 'message' => 'Payment Details retrieved successfully.'], 200);
    //     }else{
    //         return response()->json(['message' => 'No payment details found.'], 400);
    //     }
    // }

    public function getIncomeRepPayments($id) {
        $curDate = Carbon::now()->toDateString(); // Get only the date part
    
        $payments = DB::table('payments')
            ->leftJoin('transactions', 'payments.Transac_ID', '=', 'transactions.Transac_ID')
            ->select(
                'payments.Payment_ID',
                'payments.Admin_ID',
                'transactions.Transac_ID',
                'payments.Amount',
                'transactions.Tracking_number',
                'payments.Mode_of_Payment',
                'payments.Datetime_of_Payment'
            )
            ->where('payments.Admin_ID', $id)
            ->whereDate('payments.Datetime_of_Payment', $curDate) // Use whereDate for date-only filtering
            ->get();
    
        $totalDayPayments = $payments->sum('Amount'); // Calculate sum in PHP instead of query
    
        if (!$payments->isEmpty()) {
            return response()->json([
                'data' => $payments,
                'totalDayPayments' => $totalDayPayments,
                'message' => 'Payment Details retrieved successfully.'
            ], 200);
        } else {
            return response()->json([
                'data' => [],
                'message' => 'No payment details found.'
            ], 200); // Return 200 with empty data
        }
    }

    // public function getIncomeRepExpenses($id){
    //     $curDate = Carbon::now();

    //     $expenses = DB::table('expenses')
    //         ->select(
    //             'Expense_ID',
    //             'Admin_ID',
    //             'Amount',
    //             'Desc_reason',
    //             'Receipt_filenameimg',
    //             'Datetime_taken',
    //             DB::raw("SUM(DISTINCT Amount) as totalDayExp")
    //         )
    //         ->where('Admin_ID', $id)
    //         ->where('Datetime_taken', $curDate)
    //         ->get();

    //     if (!$expenses->isEmpty()) {
    //         return response()->json(['data' => $expenses, 'message' => 'Payment Details retrieved successfully.'], 200);
    //     }else{
    //         return response()->json(['message' => 'No payment details found.'], 400);
    //     }
    // }


    public function getIncomeRepExpenses($id) {
        $curDate = Carbon::now()->toDateString(); // Extract only the date
    
        $expenses = DB::table('expenses')
            ->select(
                'Expense_ID',
                'Admin_ID',
                'Amount',
                'Desc_reason',
                'Receipt_filenameimg',
                'Datetime_taken'
            )
            ->where('Admin_ID', $id)
            ->whereDate('Datetime_taken', $curDate) // Filter by date only
            ->get();
    
        $totalDayExpenses = $expenses->sum('Amount'); // Calculate sum in PHP for clarity
    
        if (!$expenses->isEmpty()) {
            return response()->json([
                'data' => $expenses,
                'totalDayExpenses' => $totalDayExpenses,
                'message' => 'Expense details retrieved successfully.'
            ], 200);
        } else {
            return response()->json([
                'data' => [],
                'message' => 'No expense details found.'
            ], 200); // Return 200 with empty data
        }
    }

    public function getCash($id){
        $curDate = Carbon::now()->format('Y-m-d');

        $cash = DB::table('cash')
        ->leftJoin('admins', 'cash.Admin_ID', '=', 'admins.Admin_ID')
        ->select(
            'cash.Cash_ID',
            'admins.Admin_ID',
            DB::raw("CONCAT(admins.Admin_fname, ' ', admins.Admin_lname) as adminName"),
            'cash.Staff_ID',
            'cash.Initial_amount',
            'Remittance',
            'Datetime_InitialAmo',
            'Datetime_Remittance',
            'Received_datetime',
            'Cash_status'
        )
        ->where('cash.Staff_ID', $id)
        ->whereDate(DB::raw('DATE(Datetime_InitialAmo)'), '=', $curDate)
        ->get();

        if (!$cash->isEmpty()) {
            return response()->json([
                'data' => $cash,
                'message' => 'Cash details retrieved successfully.'
            ], 200);
        } else {
            return response()->json([
                'data' => $curDate,
                'message' => 'No cash details found.'
            ], 200); // Return 200 with empty data
        }
    }

    public function receiveInitial($id, Request $request){
        $curDate = Carbon::now();

        $request->validate([
            'iniDate' => 'required'
        ]);

        $recIni = DB::table('cash')
            ->where('cash.Staff_ID', $id)
            ->where('Datetime_InitialAmo', '=', $request['iniDate'])
            ->update([
                'Received_datetime' => $curDate,
                'Cash_status' => 'Received'
            ]);

        if ($recIni) {
            return response()->json([
                'data' => $recIni,
                'message' => 'Received successfully.'
            ], 200);
        } else {
            return response()->json([
                'data' => 0,
                'message' => 'Receiving Inital Amount Unsuccessful.'
            ], 400);
        }
    }

    public function enterAmount(Request $request, $id){
        $curDate = Carbon::now();

        $request->validate([
            'amount' => 'required',
            'staffId' => 'required'
        ]);


        $recRemit = DB::table('cash')
        ->where('Staff_ID', $request['staffId'])
        ->where('Cash_ID', $id)
        ->update([
            'Remittance' => $request['amount'],
            'Datetime_Remittance' => $curDate,
            'Cash_status' => 'Remitted'
        ]);

        if ($recRemit) {
            return response()->json([
                'data' => $recRemit,
                'message' => 'Received successfully.'
            ], 200);
        } else {
            return response()->json([
                'data' => 0,
                'message' => 'Receiving Inital Amount Unsuccessful.'
            ], 400);
        }
    }


    public function findtrans($id)
    {
        $query = "
            SELECT 
                customer_address.province,
                transactions.Transac_ID,
                transactions.Tracking_number,
                transactions.Transac_datetime,
                additional_services.AddService_price,
                (
                    SELECT TransacStatus_name
                    FROM transaction_status AS ts
                    WHERE ts.Transac_ID = transactions.Transac_ID
                      AND ts.TransacStatus_datetime = (
                          SELECT MAX(TransacStatus_datetime)
                          FROM transaction_status
                          WHERE Transac_ID = transactions.Transac_ID
                      )
                    LIMIT 1
                ) AS latest_transac_status,
                (
                    SELECT TransacStatus_datetime
                    FROM transaction_status AS ts
                    WHERE ts.Transac_ID = transactions.Transac_ID
                      AND ts.TransacStatus_datetime = (
                          SELECT MAX(TransacStatus_datetime)
                          FROM transaction_status
                          WHERE Transac_ID = transactions.Transac_ID
                            AND TransacStatus_name = 'completed'
                      )
                    LIMIT 1
                ) AS latest_transac_datetime,
                (
                    SELECT CONCAT(a.Admin_fname, ' ', a.Admin_mname, ' ', a.Admin_lname)
                    FROM transaction_status AS ts
                    LEFT JOIN admins AS a ON a.Admin_ID = ts.Admin_ID
                    WHERE ts.Transac_ID = transactions.Transac_ID
                      AND ts.TransacStatus_datetime = (
                          SELECT MAX(TransacStatus_datetime)
                          FROM transaction_status
                          WHERE Transac_ID = transactions.Transac_ID
                      )
                    LIMIT 1
                ) AS latest_admin_name,
                customers.Cust_ID,
                customers.Cust_fname,
                customers.Cust_lname,
                admins.Admin_fname,
                admins.Admin_mname,
                admins.Admin_lname,
                GROUP_CONCAT(DISTINCT CONCAT(transaction_details.Qty, 'kgs ', laundry_categories.Category) SEPARATOR ', ') AS Category,
                SUM(DISTINCT transaction_details.Price) + SUM(DISTINCT additional_services.AddService_price) AS totalprice
            FROM transactions
            LEFT JOIN transaction_status ON transactions.Transac_ID = transaction_status.Transac_ID
            LEFT JOIN customers ON transactions.Cust_ID = customers.Cust_ID
            LEFT JOIN transaction_details ON transactions.Transac_ID = transaction_details.Transac_ID
            LEFT JOIN laundry_categories ON transaction_details.Categ_ID = laundry_categories.Categ_ID
            LEFT JOIN additional_services ON transaction_details.Transac_ID = additional_services.Transac_ID
            LEFT JOIN customer_address ON customers.Cust_ID = customer_address.Cust_ID
            JOIN admins ON admins.Admin_ID = transactions.Admin_ID
            WHERE transactions.Cust_ID = ?
            GROUP BY
                customer_address.province,
                transactions.Transac_ID,
                transactions.Tracking_number,
                transactions.Transac_datetime,
                additional_services.AddService_price,
                customers.Cust_ID,
                customers.Cust_fname,
                customers.Cust_lname,
                admins.Admin_fname,
                admins.Admin_mname,
                admins.Admin_lname
        ";
    
        $transaction = DB::select($query, [$id]);
    
        $totalprice = DB::table('transaction_details')
                        ->where('transaction_details.Transac_ID', $id)
                        ->sum('Price');
    
        if (empty($transaction)) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
    
        return response()->json(['trans' => $transaction, 'price' => $totalprice], 200);
    }

    public function printtrans($id)
    {
        // Calculate total price from transaction details
        $totalprice = DB::table('transaction_details')
            ->where('transaction_details.Transac_ID', $id)
            ->sum('Price');

        // Fetch additional services and calculate total additional service price
        $additionalServices = DB::table('additional_services')
            ->where('additional_services.Transac_ID', $id)
            ->select('additional_services.*')
            ->get();

        $totaladdprice = $additionalServices->sum('AddService_price');

        // Main query to fetch transaction details
        $result = DB::table('transactions')
            ->where('transactions.Transac_ID', $id)
            ->join('customers', 'transactions.Cust_ID', '=', 'customers.Cust_ID')
            ->join('transaction_details', 'transactions.Transac_ID', '=', 'transaction_details.Transac_ID')
            ->join('transaction_status', 'transactions.Transac_ID', '=', 'transaction_status.Transac_ID')
            ->join('admins', 'admins.Admin_ID', '=', 'transactions.Admin_ID')
            ->join('laundry_categories', 'transaction_details.Categ_ID', '=', 'laundry_categories.Categ_ID')
            ->leftJoin('additional_services', 'transaction_details.Transac_ID', '=', 'additional_services.Transac_ID')
            ->leftJoin('payments', 'transactions.Transac_ID', '=', 'payments.Transac_ID')
            ->select(
                'transaction_details.Categ_ID',
                'transactions.Transac_ID',
                'transactions.Tracking_number',
                DB::raw("(SELECT TransacStatus_name
                    FROM transaction_status AS ts
                    WHERE ts.Transac_ID = transactions.Transac_ID
                    AND ts.TransacStatus_datetime = (SELECT MAX(TransacStatus_datetime)
                                        FROM transaction_status
                                        WHERE Transac_ID = transactions.Transac_ID)
                    LIMIT 1) AS latest_transac_status"),
                'transaction_details.Qty',
                'transaction_details.Weight',
                'transaction_details.Price',
                'customers.Cust_Phoneno',
                'admins.Admin_fname',
                'admins.Admin_mname',
                'admins.Admin_lname',
                DB::raw('SUM(transaction_details.Price) as totalPrice'),
                DB::raw('GROUP_CONCAT(DISTINCT laundry_categories.Category SEPARATOR ", ") as Categories'),
                DB::raw('COUNT(DISTINCT transactions.Transac_ID) as total_count'),
                DB::raw('SUM(DISTINCT payments.Amount) as totalPaymentAmount'),
                DB::raw('SUM(DISTINCT additional_services.AddService_price) as totaladdserviceAmount'),
                DB::raw('SUM(payments.Amount) - SUM(transaction_details.Price) as balanceAmount')
            )
            ->groupBy(
                'transaction_details.Categ_ID',
                'transactions.Transac_ID',
                DB::raw('latest_transac_status'),
                'transactions.Tracking_number',
                'transaction_details.Qty',
                'transaction_details.Weight',
                'transaction_details.Price',
                'customers.Cust_Phoneno',
                'admins.Admin_fname',
                'admins.Admin_mname',
                'admins.Admin_lname'
            )
            ->get();

        if ($result->isEmpty()) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        // Calculate total amount (transaction price + additional services price)
        $totalamount = $totalprice + $totaladdprice;

        return response()->json([
            'data' => $result,
            'price' => $totalprice,
            'addprice' => $totaladdprice,
            'totalamount' => $totalamount,
            'servicedata' => $additionalServices,
        ], 200);
    }

    
}