<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StaffController;
use App\Http\Middleware\Admin;
use App\Http\Middleware\Customer;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/', function () {
    return "API";
});

Route::post('CustRegister', [CustomerController::class, 'CustRegister']);
Route::post('/CustLogin', [AuthController::class, 'CustLogin']);
Route::post('AdminLogin', [AuthController::class, 'AdminLogin']);

Route::post('logout', [AuthController::class, 'Logout'])->middleware('auth:sanctum');;


Route::middleware(['auth:sanctum'])->group(function (){

    Route::middleware([Admin::class])->group(function(){
        Route::get('user', function (Request $request) {
            return response()->json($request->user());
        });
        Route::get('/Transaction', [StaffController::class, 'getTransactions']); // Home - Receivings
        Route::get('TransactionRel', [StaffController::class, 'getTrasactionsRel']); //Home - Receivings

        Route::get('getTransCust/{id}', [StaffController::class, 'showTransCust']);
        Route::post('updateStatus/{id}', [StaffController::class, 'updateStatus']); //Home - Receivings & Releasings - modal
        Route::get('getLaundryDetails/{id}', [StaffController::class, 'showLaundryDetails']);
        Route::get('getTransactionsRec', [StaffController::class, 'getTransactionsRec']);

        Route::get('totalPrice/{id}', [StaffController::class, 'totalPrice']);
        Route::get('getAddService/{id}', [StaffController::class, 'getAddService']);
        Route::post('saveLaundryDetails', [StaffController::class, 'saveLaundryDetails']);
        Route::post('saveServiceData', [StaffController::class, 'saveServiceData']);
        
        Route::post('addRem', [StaffController::class, 'addRem']);
        Route::post('submitLaundryTrans/{id}', [StaffController::class, 'submitLaundryTrans']);
        Route::post('updateStatus/{id}', [StaffController::class, 'updateStatus']);
        Route::get('getForRel/{id}', [StaffController::class, 'getForRel']);
        Route::get('getAddServRel/{id}', [StaffController::class, 'getAddServRel']);
        Route::get('totalPriceLaundry/{id}', [StaffController::class, 'totalPriceLaundry']);
        Route::get('totalPriceService/{id}', [StaffController::class, 'totalPriceService']);
        Route::get('paymentStatus/{id}', [StaffController::class, 'paymentStatus']);
        Route::post('doneTransac/{id}', [StaffController::class, 'updateStatus']);
        Route::post('addPayment', [StaffController::class, 'addPayment']);
        Route::get('getAddServiceTotal/{id}', [StaffController::class, 'getAddServiceTotal']);
        Route::get('getLaundryDetTotal/{id}', [StaffController::class, 'getLaundryDetTotal']);

        Route::get('getCustomer', [StaffController::class, 'getCustomer']);
        Route::get('getTotalTransactions/{id}', [StaffController::class, 'getTotalTransactions']);
        Route::get('getCustomerData/{id}', [StaffController::class, 'getCustomerData']);
        Route::get('getCustTransacHistory/{id}', [StaffController::class, 'getCustTransacHistory']);
        Route::get('findtrans/{id}', [StaffController::class, 'findtrans']);
        Route::get('printtrans/{id}', [StaffController::class, 'printtrans']);

        Route::get('getExpenses/{id}', [StaffController::class, 'getExpenses']);
        Route::post('addExpense', [StaffController::class, 'addExpense']);
        Route::post('uploadExpImg', [StaffController::class, 'uploadExpImg']);
        Route::get('getExpReceipt/{id}', [StaffController::class, 'getExpReceipt']);

        Route::get('getIncomeRepPayments/{id}', [StaffController::class, 'getIncomeRepPayments']);
        Route::get('getIncomeRepExpenses/{id}', [StaffController::class, 'getIncomeRepExpenses']);
        Route::get('getCash/{id}', [StaffController::class, 'getCash']);
        Route::post('receiveInitial/{id}', [StaffController::class, 'receiveInitial']);
        Route::post('enterAmount/{id}', [StaffController::class, 'enterAmount']);
    });

    Route::middleware([Customer::class])->group(function(){
        Route::get('getCustUser/{id}', [CustomerController::class, 'getCustUser']);
        Route::get('getLaundryCateg', [CustomerController::class, 'getLaundryCateg']);
        Route::get('getTrackingNo', [CustomerController::class, 'getTrackingNo']);
        Route::post('AddNewCustTransac', [CustomerController::class, 'AddNewCustTransac']);
    });

});
