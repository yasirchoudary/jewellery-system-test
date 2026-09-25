<?php

// Controller for performing operations in Inward Module

namespace App\Http\Controllers;

// Using the tbl_inward_mst, tbl_inward_details, tbl_vendor, tbl_broker and tbl_inward_qualities tables for performing the operations
use Illuminate\Http\Request;
use App\Models\tbl_inward_mst;
use App\Models\tbl_inward_details;
use App\Models\tbl_vendor;
use App\Models\tbl_broker;
use App\Models\tbl_sell_quality;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

// Using the Carbon for manupulation of Date and its formats
use Carbon\Carbon;
use App\Services\StockService;
use App\Http\Controllers\Concerns\AuditsActions;

// InwardContoller Class
class InwardController extends Controller
{
    use AuditsActions;
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    // function to add new Inward in the database
    public function addNewInward(Request $request)
    {
        // First of all valdiate all the fields if they are in proper format or not
        $validated = validator($request->all(),[
            'date' => 'required | date',
            'invoiceNo' => 'nullable | max:20',
            'unit' => 'nullable | max:10',
            'quantity' => 'nullable | numeric',
            'rate' => 'required | numeric',
            'gstPercentage' => 'nullable | numeric | min:0',
            'metalType' => 'required | in:gold,silver',
            'weightGrams' => 'required | numeric | min:0.001',
            'itemTypeId' => 'required | numeric',
        ]);

        /* If any data is not in proper format then change the status to -1 and
        display errors and failed messaged*/
        if($validated->fails()){
            $res = array(
                "status" => -1,
                "message" => "Validation Failed!",
                "errors" => $validated->errors()
            );

            return response()->json($res);
        }

        //If all data is in proper format then input all of them in below variables
        $date = $request->input("date");
        $companyName = $request->input("companyName");
        $brokerName = $request->input("brokerName");
        $productQuality = $request->input("productQuality");
        $unit = $request->input("unit", "pcs");
        $quantity = $request->input("quantity", 1);
        $rate = $request->input("rate");
        $gstPercentage = $request->input('gstPercentage');
        $gstPercentage = ($gstPercentage === null || $gstPercentage === '') ? 0 : (float) $gstPercentage;
        $metalType = $request->input("metalType");
        $weightGrams = (float) $request->input("weightGrams");
        $itemTypeId = (int) $request->input("itemTypeId");

        /*Check in the DB if the Vendor Company Name available in DB. If
        No, then change the status to -1 and then display message that vendor not available*/
        if(!tbl_vendor::isThereCompanyNameWithVendorId($companyName)){
            return response()->json(array(
                "status" => -1,
                "message" => "Vendor Not Available!"
            ));
        }
        
        /*Check in the DB if the broker available in DB. If
        No, then change the status to -1 and then display message that broker not available*/
        if(!tbl_broker::isThereBrokerWithBrokerId($brokerName)){
            return response()->json(array(
                "status" => -1,
                "message" => "Broker Not Available!"
            ));
        }

        if(!tbl_sell_quality::where('sell_quality_id', $itemTypeId)->where('sell_quality_status', 1)->exists()){
            return response()->json(array(
                "status" => -1,
                "message" => "Jewelry item type not available!"
            ));
        }
        
        /*if (tbl_inward_details::join('tbl_inward_msts','tbl_inward_details.inward_mst_id',"=","tbl_inward_msts.inward_mst_id")
        ->where('inward_mst_date', "=", $date)
        ->where('inward_mst_invoice_no', "=", $invoiceNo)
        ->where('inward_mst_vendor_id', "=", $companyName)
        ->where('inward_mst_status', "=", '1')
        ->where('inward_details_status',"=",'1')
        ->exists()) {
            $res = array(
                "status" => 0,
                "message" => "Inward Record Already Exists!",
                "errors" => null
            );
            return response()->json($res);
        }
        }*/

        // If all validation are done and data is valid then add the data in the tables
        DB::beginTransaction();
        try{
            $financialYear = $this->getFinancialYearOfInwardDate($date);
            $invoiceNo = tbl_inward_mst::getNextInvoiceNo(
                $financialYear['fromDate'],
                $financialYear['toDate']
            );

            $inward_mst = new tbl_inward_mst();
            $inward_mst->inward_mst_date = $date;
            $inward_mst->inward_mst_invoice_no = $invoiceNo;
            $inward_mst->inward_mst_vendor_id = $companyName;
            $inward_mst->inward_mst_broker_id = $brokerName;
            $inward_mst->inward_mst_gst_percentage = $gstPercentage;
            $inward_mst->save();

            $mst_id = $inward_mst->inward_mst_id;

            $gross = (float) $request->input('grossWeight', $weightGrams);
            $stone = (float) $request->input('stoneWeight', 0);
            $purity = $request->input('purityKarat', '22K');
            $tagNumber = $request->input('tagNumber');

            $calc = app(\App\Services\JewelleryCalculationService::class);
            $weights = $calc->decomposeWeights($gross, $stone, $purity);

            $inward_detail = new tbl_inward_details();
            $inward_detail->inward_quality_id = (int) $request->input('inward_quality_id', $itemTypeId);
            $inward_detail->sell_quality_id = $itemTypeId;
            $inward_detail->metal_type = $metalType;
            $inward_detail->weight_grams = $weights['net_weight'];
            $inward_detail->gross_weight = $weights['gross_weight'];
            $inward_detail->stone_weight = $weights['stone_weight'];
            $inward_detail->net_weight = $weights['net_weight'];
            $inward_detail->purity_karat = $weights['purity_karat'];
            $inward_detail->fine_weight = $weights['fine_weight'];
            $inward_detail->qty = $quantity;
            $inward_detail->qty_unit = $unit;
            $inward_detail->rate = $rate;
            $inward_detail->inward_mst_id = $mst_id;
            $inward_detail->save();

            $purchaseAmount = round($weights['net_weight'] * $rate, 2);
            $this->stockService->addPurchase(
                $metalType,
                $itemTypeId,
                $weights['net_weight'],
                (int) $quantity,
                (float) $rate,
                $purchaseAmount,
                'purchase',
                $inward_detail->inward_details_id,
                optional($request->user())->id,
                $weights['gross_weight'],
                $weights['stone_weight'],
                $weights['purity_karat'],
                $tagNumber
            );

            DB::commit();

            $this->audit('create', 'purchase', (int) $mst_id, 'Purchase recorded: invoice ' . $invoiceNo);

            $res = array(
                "status" => 1,
                "message" => "Purchase record added and stock updated successfully.",
                "errors" => null
            );

            return response()->json($res, 200);
        }
        catch(\Exception $e){
            DB::rollBack();
            $res = array(
                "status" => -1,
                "message" => $e->getMessage() ?: "Server Error!",
                "errors" => "Exception Generated!"
            );
            return response()->json($res, 500);
        }
    }

    // Function to get all data of inward records
    public function getAllInwards(Request $request)
    {
        // Input the filter data in below variables
        $paginate = request("paginate", 10);
        $vendorId = $request->input("company");
        $categoryId = $request->input("category");
        $qualityId = $request->input("quality");
        $brokerId = $request->input('broker');
        

        // input the search term in below variables and remove spaces and search in the data available
        $search_term = request("search", "");
        $search_term = trim($search_term);
        $search_term = "%$search_term%";

        // Set the sort field and direction and input in below variables
        $sort_field = request("sortfield");
        $sort_direction = request("sortdirection");

        // Set the from date and to date and input them in below variables
        $from_date = request("fromdate", Carbon::now()->subDays(10));
        $to_date = request("todate", Carbon::now());
 
        // By default, set the direction to descending
        if(!in_array($sort_direction, ['asc', 'desc'])){
            $sort_direction = "desc";
        }

        /*Apply sort field to below column data and by
        default set the sort field to inward_mst_date*/
        if(!in_array($sort_field, ['inward_mst_id','inward_mst_date'])){
            $sort_field = 'inward_mst_date';
        }

        // Get all the data from tables
        return (tbl_inward_details::join('tbl_inward_msts','tbl_inward_details.inward_mst_id',"=","tbl_inward_msts.inward_mst_id")
        ->join('tbl_vendors','tbl_inward_msts.inward_mst_vendor_id','=','tbl_vendors.vendor_id')
        ->join('tbl_brokers','tbl_inward_msts.inward_mst_broker_id','=','tbl_brokers.broker_id')
        ->join('tbl_sell_qualities', 'tbl_inward_details.sell_quality_id', '=', 'tbl_sell_qualities.sell_quality_id')
        ->join('tbl_sell_quality_categories', 'tbl_sell_qualities.sell_quality_category_id', '=', 'tbl_sell_quality_categories.sell_quality_category_id')
        ->select('tbl_inward_details.inward_mst_id', 'inward_mst_date','inward_mst_invoice_no','vendor_company_name','broker_name','tbl_sell_qualities.quality_name','tbl_inward_details.weight_grams','tbl_inward_details.metal_type','qty','rate','inward_mst_gst_percentage', 'tbl_sell_quality_categories.sell_category_name')
        ->where('tbl_inward_msts.inward_mst_status', '=', 1)
        ->where('tbl_inward_details.inward_details_status', '=', 1)
        ->whereDate('tbl_inward_msts.inward_mst_date', '>=', $from_date)
        ->whereDate('tbl_inward_msts.inward_mst_date', '<=', $to_date)
        ->where(function($query) use ($search_term)
        {
            // Bring the data according to the filters applied using where conditions
            $query->where('tbl_inward_msts.inward_mst_date', 'like', $search_term)
            ->orWhere('tbl_inward_msts.inward_mst_invoice_no', 'like', $search_term)
            ->orWhere('tbl_inward_msts.inward_mst_vendor_id', 'like', $search_term)
            ->orWhere('tbl_inward_msts.inward_mst_broker_id', 'like', $search_term)
            ->orWhere('tbl_inward_details.inward_quality_id', 'like', $search_term)
            ->orWhere('tbl_inward_details.qty', 'like', $search_term)
            ->orWhere('tbl_inward_details.rate', 'like', $search_term);
        })
        ->when($vendorId, function($query) use ($vendorId) {
            $query->where('tbl_inward_msts.inward_mst_vendor_id', $vendorId);
        })
        ->when($categoryId, function($query) use ($categoryId) {
            $query->where('tbl_sell_qualities.sell_quality_category_id', $categoryId);
        })
        ->when($qualityId, function($query) use ($qualityId) {
            $query->where('tbl_inward_details.sell_quality_id', $qualityId);
        })
        ->when($brokerId, function($query) use ($brokerId) {
            $query->where('tbl_inward_msts.inward_mst_broker_id', $brokerId);
        })
        ->orderBy($sort_field, $sort_direction)    
        ->paginate($paginate));
    }

    public function getNextPurchaseInvoiceNumber(Request $request, $inwardDate)
    {
        $financialYear = $this->getFinancialYearOfInwardDate($inwardDate);

        return response()->json([
            'nextInvoiceNo' => tbl_inward_mst::getNextInvoiceNo(
                $financialYear['fromDate'],
                $financialYear['toDate']
            ),
        ]);
    }

    private function getFinancialYearOfInwardDate($date): array
    {
        $splitDate = explode('-', Carbon::parse($date)->format('Y-m-d'));
        $month = $splitDate[1];
        $year = $splitDate[0];

        $fromDate = $year;
        $toDate = $year;

        if ((int) $month < 4) {
            $fromDate = (int) $year - 1;
        } else {
            $toDate = (int) $year + 1;
        }

        return [
            'fromDate' => $fromDate . '-04-01',
            'toDate' => $toDate . '-03-31',
        ];
    }

    // function to display all data related to inward record
    public function viewInwardDetails(Request $request, $inwardMstId)
    {
        $inward = tbl_inward_mst::with([
            'inward_details.quality.category',
            'getBroker:broker_id,broker_name',
            'getVendor:vendor_id,vendor_company_name,vendor_contact_no,vendor_gst_no',
        ])
            ->where('inward_mst_id', $inwardMstId)
            ->where('inward_mst_status', 1)
            ->select(
                'inward_mst_id',
                'inward_mst_date',
                'inward_mst_invoice_no',
                'inward_mst_vendor_id',
                'inward_mst_broker_id',
                'inward_mst_gst_percentage'
            )
            ->first();

        if (!$inward || !$inward->inward_details) {
            return response()->json([
                'status' => 0,
                'message' => 'Purchase record not found.',
            ], 404);
        }

        return response()->json($inward);
    }

    // function to update inward record
    public function updateInward(Request $request, $inwardId)
    {
        /* first of all validate all the data and check that if all of them are
        in proper format or not*/
        $validated = validator($request->all(),[
            'inwardMstId' => 'required | numeric',
            'inwardDate' => 'required | date',
            'invoiceNo' => 'required | max:20',
            'company' => 'required | numeric',
            'broker' => 'required | numeric',
            'category' => 'required | numeric',
            'quality' => 'required | numeric',
            'unit' => 'required | max:10',
            'rate' => 'required | numeric',
            'gstPercentage' => 'nullable | numeric | min:0',
            'qty' => 'required | numeric',
            'weightGrams' => 'required | numeric | min:0.001',
            'metalType' => 'required | in:gold,silver',
        ]);

        /* If the Validation failed then status would be -1 and Validation Failed messaged and display
        Errors */
        
        if($validated->fails()){
            $res = array(
                "status" => -1,
                "message" => "Validation Failed!",
                "errors" => $validated->errors()
            );

            return response()->json($res);
        }

        // if all the data are as per the format then input all of them in below variables
        $date = $request->input("inwardDate");
        $invoiceNo = $request->input("invoiceNo");
        $companyName = $request->input("company");
        $brokerName = $request->input("broker");
        $productQuality = $request->input("quality");
        $unit = $request->input("unit");
        $quantity = $request->input("qty");
        $rate = $request->input("rate");
        $gstPercentage = $request->input('gstPercentage');
        $gstPercentage = ($gstPercentage === null || $gstPercentage === '') ? 0 : (float) $gstPercentage;
        $weightGrams = (float) $request->input("weightGrams");
        $metalType = $request->input("metalType");
        $inwardMstId = $request->input("inwardMstId");

        if(!tbl_vendor::isThereCompanyNameWithVendorId($companyName)){
            return response()->json(array(
                "status" => -1,
                "message" => "Vendor Not Available!"
            ));
        }

        /*Check in the DB if the broker available in DB. If
        No, then change the status to -1 and then display message that broker not available*/
        if(!tbl_broker::isThereBrokerWithBrokerId($brokerName)){
            return response()->json(array(
                "status" => -1,
                "message" => "Broker Not Available!"
            ));
        }

        /*Check in the DB if the product quality available in DB. If
        No, then change the status to -1 and then display message that quality not available*/
        if(!tbl_sell_quality::where('sell_quality_id', $productQuality)->where('sell_quality_status', 1)->exists()){
            return response()->json(array(
                "status" => -1,
                "message" => "Item type not available!"
            ));
        }

        tbl_inward_details::join('tbl_inward_msts','tbl_inward_details.inward_mst_id',"=","tbl_inward_msts.inward_mst_id")
        ->where('tbl_inward_details.inward_mst_id', '=', $inwardMstId)
        ->update([
            'inward_mst_date' => $date,
            'inward_mst_invoice_no' => $invoiceNo,
            'inward_mst_vendor_id' => $companyName,
            'inward_mst_broker_id' => $brokerName,
            'sell_quality_id' => $productQuality,
            'metal_type' => $metalType,
            'weight_grams' => $weightGrams,
            'qty' => $quantity,
            'qty_unit' => $unit,
            'rate' => $rate,
            'inward_mst_gst_percentage' => $gstPercentage,
        ]);
        return response()->json(array(
            "status" => 1,
            "message"=> "Inward Record Updated Succesfully..."
        ));
    }

    // Function is to delete respective inward using inward id
    public function deleteInward(Request $request, $inwardId)
    {
        $inward = tbl_inward_mst::with(['inward_details'])
            ->where('inward_mst_id', $inwardId)
            ->where('inward_mst_status', 1)
            ->first();

        if (!$inward || !$inward->inward_details || !$inward->inward_details->inward_details_status) {
            return response()->json([
                'status' => -1,
                'message' => 'Purchase record not found or already deleted.',
            ]);
        }

        DB::beginTransaction();

        try {
            $detail = $inward->inward_details;

            $this->stockService->reversePurchase(
                'purchase',
                (int) $detail->inward_details_id,
                optional($request->user())->id
            );

            $inward->inward_mst_status = 0;
            $inward->save();

            tbl_inward_details::where('inward_mst_id', $inwardId)
                ->update(['inward_details_status' => 0]);

            DB::commit();

            $this->audit(
                'delete',
                'purchase',
                (int) $inwardId,
                'Purchase deleted: invoice ' . $inward->inward_mst_invoice_no
            );

            return response()->json([
                'status' => 1,
                'message' => 'Purchase deleted and stock updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => -1,
                'message' => $e->getMessage() ?: 'Purchase deletion failed.',
            ]);
        }
    }
}
