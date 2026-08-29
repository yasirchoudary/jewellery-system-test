<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PDFController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('app');
});

use App\Http\Controllers\Auth\CustomLoginController;

// Accept login by email or username
Route::post('/login', [CustomLoginController::class, 'login']);

Route::get('/challan/pdf/{challan_id}', [PDFController::class,"generateChallanPDF"])->middleware('pdf.auth');
Route::get('/invoice/pdf/{invoice_id}', [PDFController::class,"generateInvoicePDF"])->middleware('pdf.auth');
Route::get('/directinvoice/pdf/{invoice_id}', [PDFController::class,"generateDirectInvoicePDF"])->middleware('pdf.auth');
Route::get('/investor/pdf/{investor_id}/{period}', [PDFController::class, 'generateInvestorReportPDF'])->middleware('pdf.auth');
