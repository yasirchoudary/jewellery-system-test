<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\InvestorExpenseAllocationController;
use App\Http\Controllers\InwardQualityController;
use App\Http\Controllers\InwardController;
use App\Http\Controllers\SellQualityController;
use App\Http\Controllers\BrokerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\BankDetailsController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\ExpenseCategoryControler;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ChallanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LabJobController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\KarigarController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\JewelleryInventoryController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/stock/balances', [StockController::class, 'balances']);
    Route::get('/stock/quality/{sellQualityId}', [StockController::class, 'qualityBalance']);
    Route::get('/stock/ledger', [StockController::class, 'ledger'])
        ->middleware(['role:admin,worker', 'permission:stock']);

    Route::middleware('role:admin,worker')->prefix('inventory')->group(function () {
        Route::post('/calculate', [JewelleryInventoryController::class, 'calculate']);
        Route::get('/tags/{tagNumber}', [JewelleryInventoryController::class, 'findTag']);
        Route::post('/exchange', [JewelleryInventoryController::class, 'recordExchange'])->middleware('permission:sales');

        Route::middleware('permission:stock')->group(function () {
            Route::get('/valuation', [JewelleryInventoryController::class, 'valuation']);
            Route::get('/tags', [JewelleryInventoryController::class, 'tags']);
            Route::post('/tags', [JewelleryInventoryController::class, 'storeTag']);
            Route::post('/opening-stock', [JewelleryInventoryController::class, 'recordOpeningStock']);
            Route::post('/adjustment', [JewelleryInventoryController::class, 'recordAdjustment']);
        });
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/users/workers', [UserController::class, 'workers']);
        Route::post('/users/workers', [UserController::class, 'storeWorker']);
        Route::put('/users/workers/{userId}/permissions', [UserController::class, 'updatePermissions']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::put('/settings', [AppSettingsController::class, 'update']);
    });

    Route::get('/settings', [AppSettingsController::class, 'show'])->middleware('role:admin,worker');

    Route::get('/investor/summary', [InvestorController::class, 'summary'])->middleware('role:investor,admin');
    Route::get('/investor/transactions', [InvestorController::class, 'transactions'])->middleware('role:investor,admin');
    Route::get('/investor/list', [InvestorController::class, 'index'])->middleware('role:admin,worker');

    Route::middleware('role:admin')->prefix('investor')->group(function () {
        Route::post('/create', [InvestorController::class, 'store']);
        Route::put('/update/{investorId}', [InvestorController::class, 'update']);
        Route::post('/transaction', [InvestorController::class, 'addTransaction']);
        Route::delete('/transaction/{transactionId}', [InvestorController::class, 'deleteTransaction']);
        Route::get('/expense-allocations', [InvestorExpenseAllocationController::class, 'index']);
        Route::get('/expenses-for-allocation', [InvestorExpenseAllocationController::class, 'expensesForAllocation']);
        Route::post('/expense-allocations', [InvestorExpenseAllocationController::class, 'store']);
        Route::put('/expense-allocations/{allocationId}', [InvestorExpenseAllocationController::class, 'update']);
        Route::delete('/expense-allocations/{allocationId}', [InvestorExpenseAllocationController::class, 'destroy']);
    });

    Route::get('/lab/jobs', [LabJobController::class, 'index'])->middleware('role:admin,worker,investor');
    Route::get('/lab/summary', [LabJobController::class, 'summary'])->middleware('role:admin,worker,investor');
    Route::get('/lab/preview-shares', [LabJobController::class, 'previewShares'])->middleware('role:admin,worker');

    Route::middleware('role:admin,worker')->prefix('karigar')->group(function () {
        Route::get('/list', [KarigarController::class, 'listKarigars'])->middleware('permission:karigar');
        Route::get('/jobs/pending-for-sale', [KarigarController::class, 'getPendingJobsForSale'])->middleware('permission:invoices');
        Route::get('/jobs', [KarigarController::class, 'getJobs'])->middleware('permission:karigar');
        Route::post('/jobs/issue', [KarigarController::class, 'issueJob'])->middleware('permission:karigar');
        Route::post('/jobs/{jobId}/add-issue', [KarigarController::class, 'addIssueToJob'])->middleware('permission:karigar');
        Route::post('/jobs/{jobId}/return', [KarigarController::class, 'returnJob'])->middleware('permission:karigar');
        Route::delete('/jobs/{jobId}', [KarigarController::class, 'deleteJob'])->middleware('permission:karigar');
        Route::get('/', [KarigarController::class, 'getKarigars'])->middleware('permission:karigar');
        Route::post('/', [KarigarController::class, 'storeKarigar'])->middleware('permission:karigar');
        Route::put('/{karigarId}', [KarigarController::class, 'updateKarigar'])->middleware('permission:karigar');
        Route::delete('/{karigarId}', [KarigarController::class, 'deleteKarigar'])->middleware('permission:karigar');
    });

    Route::middleware('role:admin,worker')->prefix('lab')->group(function () {
        Route::post('/jobs', [LabJobController::class, 'store'])->middleware('permission:laboratory');
        Route::put('/jobs/{labJobId}', [LabJobController::class, 'update'])->middleware('permission:laboratory');
        Route::delete('/jobs/{labJobId}', [LabJobController::class, 'destroy'])->middleware('permission:laboratory_delete');
    });

    Route::middleware('role:admin,worker')->group(function () {
        Route::get('/brokerslist', [BrokerController::class, 'getBrokersList']);
        Route::get('/getBrokers', [BrokerController::class, 'getBrokers']);
        Route::get('/customerlist', [CustomerController::class, 'getCustomersList']);
        Route::get('/selectedcustomerdata/{customer_id}', [CustomerController::class, 'getSelectedCustomerData']);
        Route::get('/vendorcompanies', [VendorController::class, 'getCompanyNames']);
        Route::get('/selectedvendordata/{vendor_id}', [VendorController::class, 'getSelectedVendorData']);
        Route::get('/sellqualitycategories', [SellQualityController::class, 'getQualityCategories']);
        Route::get('/sellqualityofcategory/{sell_quality_category_id}', [SellQualityController::class, 'getSellQualityOfGivenCategory']);
        Route::get('/expensecategorieslist', [ExpenseCategoryControler::class, 'getAllExpenseCategoriesList']);
    });

    Route::middleware(['role:admin,worker', 'permission:dashboard'])->get('/dashboarddata', [DashboardController::class, 'dashboardCalculations']);

    Route::middleware(['role:admin,worker', 'permission:masters'])->group(function () {
        Route::get('/inwardqualitycategories', [InwardQualityController::class, 'getQualityCategories']);
        Route::get('/inwardqualities', [InwardQualityController::class, 'getAllInwardQualities']);
        Route::get('/productqualitycategories', [InwardQualityController::class, 'getProductQualityCategories']);
        Route::get('/inwardqualityofcategories/{inward_quality_category_id}', [InwardQualityController::class, 'getSelectedProductQualities']);
        Route::prefix('/inwardquality')->group(function () {
            Route::post('/insert', [InwardQualityController::class, 'insertInwardQuality']);
            Route::put('/update/{inward_quality_id}', [InwardQualityController::class, 'updateInwardQuality']);
            Route::delete('/delete/{inward_quality_id}', [InwardQualityController::class, 'deleteInwardQuality']);
        });

        Route::get('/sellqualities', [SellQualityController::class, 'getAllSellQualities']);
        Route::prefix('/sellquality')->group(function () {
            Route::post('/insert', [SellQualityController::class, 'insertSellQuality']);
            Route::put('/update/{sell_quality_id}', [SellQualityController::class, 'updateSellQuality']);
            Route::delete('/delete/{sell_quality_id}', [SellQualityController::class, 'deleteSellQuality']);
        });

        Route::get('/brokers', [BrokerController::class, 'getAllBrokers']);
        Route::prefix('/broker')->group(function () {
            Route::post('/insert', [BrokerController::class, 'insertBroker']);
            Route::put('/update/{broker_id}', [BrokerController::class, 'updateBroker']);
            Route::delete('/delete/{broker_id}', [BrokerController::class, 'deleteBroker']);
        });

        Route::post('/customer', [CustomerController::class, 'addNewCustomer']);
        Route::get('/customers', [CustomerController::class, 'getAllCustomers']);
        Route::put('/customer/update/{customer_id}', [CustomerController::class, 'updateCustomer']);
        Route::delete('/customer/delete/{customer_id}', [CustomerController::class, 'deleteCustomer']);
        Route::get('/customers', [CustomerController::class, 'getAllCustomers']);

        Route::post('/vendor', [VendorController::class, 'addNewVendor']);
        Route::get('/vendors', [VendorController::class, 'getAllVendors']);
        Route::put('/vendor/update/{vendor_id}', [VendorController::class, 'updateVendor']);
        Route::delete('/vendor/delete/{vendor_id}', [VendorController::class, 'deleteVendor']);
    });

    Route::middleware(['role:admin,worker', 'permission:purchases'])->group(function () {
        Route::post('/inward', [InwardController::class, 'addNewInward']);
        Route::get('/inward/next-invoice-no/{inwarddate}', [InwardController::class, 'getNextPurchaseInvoiceNumber']);
        Route::get('/inwards', [InwardController::class, 'getAllInwards']);
        Route::get('/inward/view/{inward_mst_id}', [InwardController::class, 'viewInwardDetails']);
        Route::put('/inward/update/{inward_details_id}', [InwardController::class, 'updateInward']);
    });
    Route::delete('/inward/delete/{inward_details_id}', [InwardController::class, 'deleteInward'])
        ->middleware(['role:admin,worker', 'permission:purchases_delete']);
    Route::delete('/inward/delete/{inward_mst_id}', [InwardController::class, 'deleteInward'])
        ->middleware(['role:admin,worker', 'permission:purchases_delete']);

    Route::middleware(['role:admin,worker', 'permission:sales'])->group(function () {
        Route::get('/getfinancialyear/{challandate}', [ChallanController::class, 'getFinancialYearOfChallanDate']);
        Route::get('/challan/next-no/{challandate}', [ChallanController::class, 'getNextChallanNumber']);
        Route::get('/verifychallan/{challanno}/{fromdate}/{todate}', [ChallanController::class, 'verifyChallanNumber']);
        Route::post('/challan/insert', [ChallanController::class, 'addNewChallan']);
        Route::get('/challans', [ChallanController::class, 'getChallans']);
        Route::get('/challan/{challanid}', [ChallanController::class, 'getChallanDataOfChallanId']);
        Route::put('/challan', [ChallanController::class, 'updateChallan']);
    });
    Route::delete('/challan/{challanId}', [ChallanController::class, 'deleteChallan'])
        ->middleware(['role:admin,worker', 'permission:sales_delete']);

    Route::middleware(['role:admin,worker', 'permission:invoices'])->group(function () {
        Route::post('/directinvoice', [InvoiceController::class, 'addNewDirectInvoice']);
        Route::get('/directinvoices', [InvoiceController::class, 'getAllDirectInvoices']);
        Route::get('/directinvoice/{invoiceid}', [InvoiceController::class, 'getDirectInvoiceOfInvoiceId']);
        Route::put('/directinvoice', [InvoiceController::class, 'updateDirectInvoice']);

        Route::get('/invoice/available-challans', [InvoiceController::class, 'getAvailableChallansForInvoice']);
        Route::get('/invoice/getfinancialyear/{invoiceno}', [InvoiceController::class, 'getFromInvoiceNo']);
        Route::get('/invoice/challandata/{invoiceno}/{fromdate}/{todate}', [InvoiceController::class, 'getFromInvoiceNoAndFinancialYear']);
        Route::get('/getstate/{code}', [InvoiceController::class, 'getStateFromCode']);
        Route::post('/invoice/insert', [InvoiceController::class, 'addNewInvoiceFromChallan']);
        Route::get('/verifyinvoicedate/{invoicedate}/{fromdate}/{todate}', [InvoiceController::class, 'verifyInvoiceDate']);
        Route::get('/invoices', [InvoiceController::class, 'getAllChallanInvoices']);
        Route::put('/invoice', [InvoiceController::class, 'updateChallanInvoice']);
        Route::get('/invoice/{invoiceid}', [InvoiceController::class, 'getChallanInvoiceOfInvoiceid']);
        Route::put('/invoice', [InvoiceController::class, 'updateInvoiceForChallan']);
    });
    Route::delete('/directinvoice/{invoiceMstId}', [InvoiceController::class, 'deleteDirectInvoice'])
        ->middleware(['role:admin,worker', 'permission:invoices_delete']);
    Route::delete('/invoice/{invoiceMstId}', [InvoiceController::class, 'deleteChallanInvoice'])
        ->middleware(['role:admin,worker', 'permission:invoices_delete']);

    Route::middleware(['role:admin,worker', 'permission:expenses'])->group(function () {
        Route::get('/expensecategories', [ExpenseCategoryControler::class, 'getAllExpenseCategories']);
        Route::get('/expensecategorieslist', [ExpenseCategoryControler::class, 'getAllExpenseCategoriesList']);
        Route::post('/expense', [ExpenseController::class, 'addExpense']);
        Route::get('/expense', [ExpenseController::class, 'getExpenses']);
        Route::put('/expense', [ExpenseController::class, 'updateExpenses']);
        Route::get('/totalexpenseamount', [ExpenseController::class, 'getTotalAmountOfGivenDateRangeAndCategory']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::put('/expensecategory/update/{expense_category_id}', [ExpenseCategoryControler::class, 'updateExpenseCategory']);
        Route::post('/expensecategory', [ExpenseCategoryControler::class, 'addNewCategory']);
        Route::delete('/expensecategory/{expense_category_id}', [ExpenseCategoryControler::class, 'deleteCategory']);

        Route::prefix('/bankdetails')->group(function () {
            Route::post('/insert', [BankDetailsController::class, 'insertBankDetails']);
        });
        Route::get('/bankdetails', [BankDetailsController::class, 'getAllBankDetails']);
        Route::put('/bankdetail/update/{bank_details_id}', [BankDetailsController::class, 'updateBankDetail']);
        Route::delete('/bankdetail/delete/{bank_details_id}', [BankDetailsController::class, 'deleteBankDetail']);

        Route::prefix('/credit')->group(function () {
            Route::post('/insert', [CreditController::class, 'insertCredit']);
        });
        Route::get('/credits', [CreditController::class, 'getAllCreditDetails']);
        Route::put('/credit/update/{credit_id}', [CreditController::class, 'updateCreditDetail']);
        Route::delete('/credit/delete/{credit_id}', [CreditController::class, 'deleteDetail']);
    });
    Route::get('/bankinfo', [BankDetailsController::class, 'getBankInfo'])
        ->middleware(['role:admin,worker', 'permission:invoices']);
    Route::get('/bankbranch/{bankId}', [BankDetailsController::class, 'getBankBranch'])
        ->middleware(['role:admin,worker', 'permission:invoices']);
    Route::delete('/expense/{expenseid}', [ExpenseController::class, 'deleteExpenses'])
        ->middleware(['role:admin,worker', 'permission:expenses_delete']);
});
