<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Database\Query\Builder;
use App\Models\tbl_challan_details;
use App\Models\tbl_challan_mst;
use App\Models\tbl_sell_quality;
use App\Services\StockService;
use Carbon\Carbon;
use RuntimeException;

class ChallanController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /* will sends challans to datatale according to request recieved */
    function getChallans(Request $req){
        $paginate = request("paginate", 10);
        $company = request("company","");
        $category = request("category","");
        $quality = request("quality","");
        $broker = request("broker","");


        $search_term = request("search", "");
        $search_term = trim($search_term);
        $search_term = "%$search_term%";

        $sort_field = request("sortfield");
        $sort_direction = request("sortdirection");

        $from_date = request("fromdate", Carbon::now()->subDays(10));
        $to_date = request("todate", Carbon::now());

 
        if(!in_array($sort_direction, ['asc', 'desc'])){
            $sort_direction = "desc";
        }

        if(!in_array($sort_field, ['challan_date','challan_no'])){
            $sort_field = 'challan_date';
        }

        $query = tbl_challan_mst::join('tbl_challan_details', 'tbl_challan_details.challan_mst_id', "=", 'tbl_challan_msts.challan_mst_id')
        ->join('tbl_customers', 'tbl_customers.customer_id',"=", 'tbl_challan_msts.customer_id')
        ->join('tbl_brokers', 'tbl_brokers.broker_id', "=", 'tbl_challan_msts.broker_id')
        ->join('tbl_sell_qualities', 'tbl_sell_qualities.sell_quality_id',"=", 'tbl_challan_msts.sell_quality_id')
        ->join('tbl_sell_quality_categories', 'tbl_sell_quality_categories.sell_quality_category_id', "=", "tbl_sell_qualities.sell_quality_category_id")
        ->where('tbl_challan_msts.challan_mst_status', true)
        ->where('tbl_challan_msts.is_direct', false)
        ->where('tbl_challan_details.challan_details_status', true)
        ->whereDate('tbl_challan_msts.challan_date', "<=", $to_date)
        ->whereDate('tbl_challan_msts.challan_date', ">=", $from_date)
        ->when($company, function($query) use ($company){
            $query->where("tbl_challan_msts.customer_id", '=', $company);
        })
        ->when($category, function($query) use ($category){
            $query->where('tbl_sell_qualities.sell_quality_category_id', $category);
        })
        ->when($quality, function($query) use ($quality){
            $query->where(function ($q) use ($quality) {
                $q->where('tbl_challan_msts.sell_quality_id', $quality)
                    ->orWhere('tbl_challan_details.sell_quality_id', $quality);
            });
        })
        ->when($broker, function($query) use ($broker){
            $query->where('tbl_challan_msts.broker_id', $broker);
        })
        ->orderBy($sort_field, $sort_direction)
        ->select('tbl_challan_msts.challan_mst_id', 'tbl_challan_msts.challan_no','tbl_challan_msts.challan_date','customer_company_name','tbl_brokers.broker_name',DB::raw("SUM(tbl_challan_details.qty) as totalqty"), 'tbl_challan_msts.weight_grams', 'tbl_sell_qualities.quality_name', 'tbl_sell_quality_categories.sell_category_name')
        ->groupBy('tbl_challan_msts.challan_mst_id', 'tbl_customers.customer_company_name', 'tbl_brokers.broker_name', 'tbl_sell_qualities.quality_name', 'tbl_challan_msts.challan_date','tbl_challan_msts.challan_no', 'tbl_sell_quality_categories.sell_category_name', 'tbl_challan_msts.weight_grams');

        $paginator = $query->paginate($paginate);
        $paginator->getCollection()->transform(function ($row) {
            $names = tbl_challan_details::where('challan_mst_id', $row->challan_mst_id)
                ->where('challan_details_status', true)
                ->whereNotNull('sell_quality_id')
                ->join('tbl_sell_qualities', 'tbl_sell_qualities.sell_quality_id', 'tbl_challan_details.sell_quality_id')
                ->orderBy('tbl_challan_details.no')
                ->pluck('quality_name')
                ->unique()
                ->values()
                ->all();
            if (count($names) > 1) {
                $row->quality_name = implode(', ', $names);
            } elseif (count($names) === 1) {
                $row->quality_name = $names[0];
            }
            return $row;
        });

        return $paginator;
    }

    // will send challan data of requested challan
    function getChallanDataOfChallanId(Request $req, $challanId){

        $dataToBeValidate = array(
            "challanid" => $challanId
        );

        $validated = validator($dataToBeValidate,[
            'challanid' => 'numeric'
        ]);

        if($validated->fails()){
            $res = array(
                "status" => -1,
                "message" => "Challan Id Is Not In Valid Format"
            );

            return response()->json($res);
        }
        
        $challanDataFetched = tbl_challan_mst::with([
            'challan_details',
            'customer_relation:customer_id,customer_company_name',
            'broker:broker_id,broker_name',
            'quality:sell_quality_id,quality_name,sell_quality_category_id',
        ])
        ->where("tbl_challan_msts.challan_mst_id", $challanId)
        ->first();

        if(is_null($challanDataFetched)){
            return response()->json(array(
                "status" => 0,
                "message" => "Challan Not Found"
            ));
        }

        $items = collect($challanDataFetched->challan_details)->map(function ($row) use ($challanDataFetched) {
            return [
                'challanDetailsId' => $row->challan_details_id,
                'no' => $row->no,
                'qty' => $row->qty,
                'weightGrams' => $row->weight_grams,
                'unit' => $row->qty_unit ?: $challanDataFetched->qty_unit,
                'categoryId' => $row->sell_category_id ?: $challanDataFetched->challan_type,
                'qualityId' => $row->sell_quality_id ?: $challanDataFetched->sell_quality_id,
                'qualityName' => optional($row->quality)->quality_name
                    ?: optional($challanDataFetched->quality)->quality_name,
                'categoryName' => optional($row->category)->sell_category_name,
            ];
        })->values()->all();

        $challanData = array(
            "challanid" => (int)$challanId,
            "challanno" => $challanDataFetched->challan_no,
            "challandate" => $challanDataFetched->challan_date,
            "customer" => $challanDataFetched->customer_relation,
            "broker"=>$challanDataFetched->broker,
            "quality" => $challanDataFetched->quality,
            "unit" => $challanDataFetched->qty_unit,
            "weight_grams" => $challanDataFetched->weight_grams,
            "total_qty" => $challanDataFetched->total_qty,
            "challandetails"=>$challanDataFetched->challan_details,
            "items" => $items,
            "quality_names" => collect($items)->pluck('qualityName')->filter()->unique()->values()->all(),
        );

        return response()->json($challanData);
    }

    /* will return starting and ending date of financial year of given date*/
    public function getFinancialYearOfChallanDate($challanDate){

        $challanSplitDate = explode("-", Carbon::parse($challanDate)->format('Y-m-d'));
        $challanMonth = $challanSplitDate[1];
        $challanYear = $challanSplitDate[0];
        
        $fromDate = $challanYear;
        $toDate = $challanYear;
        if((int)$challanMonth<4){
            $fromDate = (int)$challanYear - 1;
        }else{
            $toDate = (int)$challanYear + 1;
        }
        
        $fromDate = $fromDate."-04-01";
        $toDate = $toDate."-03-31";
        
        return response()->json(array(
            "fromDate" => $fromDate, 
            "toDate" => $toDate
        ));
    }

    /* will return starting and ending date of financial year of given date in array form */
    public function getFinancialYearOfChallanDateInArray($challanDate){

        $challanSplitDate = explode("-", Carbon::parse($challanDate)->format('Y-m-d'));
        $challanMonth = $challanSplitDate[1];
        $challanYear = $challanSplitDate[0];
        
        $fromDate = $challanYear;
        $toDate = $challanYear;
        if((int)$challanMonth<4){
            $fromDate = (int)$challanYear - 1;
        }else{
            $toDate = (int)$challanYear + 1;
        }
        
        $fromDate = $fromDate."-04-01";
        $toDate = $toDate."-03-31";
        
        // return json_encode(array(
        //     "fromDate" => $fromDate, 
        //     "toDate" => $toDate
        // ));
        
        return array(
            "fromDate" => $fromDate,
            "toDate" => $toDate
        );
    }

    /* will  check whether given challan no already exists or not */
    public function verifyChallanNumber(Request $request, $challanNo, $fromDate, $toDate){
        if(tbl_challan_mst::where('challan_no', '=', $challanNo)->where('challan_mst_status', '=', 1)->whereDate('challan_date', '>=', $fromDate)->whereDate('challan_date', '<=', $toDate)->exists()){
            return response()->json(array(
                "status" => 0,
                "message"=> "Entered Challan Number Already Exists!!!"
            ));
        }else{
            return response()->json(array(
                "status" => 1
            ));
        }
    }

    public function getNextChallanNumber(Request $request, $challanDate)
    {
        $financialYear = $this->getFinancialYearOfChallanDateInArray($challanDate);

        return response()->json([
            'nextChallanNo' => tbl_challan_mst::getNextChallanNo(
                $financialYear['fromDate'],
                $financialYear['toDate']
            ),
        ]);
    }

    /* Will add new challan when requested with challan data from front-end */
    public function addNewChallan(Request $request){
        $hasItems = is_array($request->input('items')) && count($request->input('items')) > 0;

        if ($hasItems) {
            $validated = validator($request->all(), [
                'challanDate' => 'required|date',
                'customerId' => 'required|numeric',
                'brokerId' => 'required|numeric',
                'items' => 'required|array|min:1',
                'items.*.categoryId' => 'required|numeric',
                'items.*.qualityId' => 'required|numeric',
                'items.*.qty' => 'required|numeric|min:0.001',
                'items.*.unit' => 'required|max:20',
                'items.*.weightGrams' => 'required|numeric|min:0.001',
            ]);
        } else {
            $validated = validator($request->all(), [
                'challanNo' => 'nullable|numeric',
                'challanDate' => 'required|date',
                'customerId' => 'required|numeric',
                'sellCategoryId' => 'required|numeric',
                'sellQualityId' => 'required|numeric',
                'qtyUnit' => 'required|max:10',
                'totalQty' => 'required|numeric',
                'weightGrams' => 'required|numeric|min:0.001',
                'brokerId' => 'required|numeric',
            ]);
        }

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'The given data was invalid.',
                'errors' => $validated->errors(),
            ]);
        }

        $challanDate = $request->input('challanDate');
        $customerId = $request->input('customerId');
        $brokerId = $request->input('brokerId');
        $financialYear = $this->getFinancialYearOfChallanDateInArray($challanDate);
        $challanNo = tbl_challan_mst::getNextChallanNo(
            $financialYear['fromDate'],
            $financialYear['toDate']
        );

        if ($hasItems) {
            $normalizedItems = [];
            $totalWeightGrams = 0.0;
            $totalQty = 0.0;

            foreach ($request->input('items') as $index => $item) {
                $qty = (float) $item['qty'];
                $weightPerPiece = (float) $item['weightGrams'];
                $lineWeight = round($weightPerPiece * $qty, 3);
                $pieces = max(1, (int) round($qty));

                if ($lineWeight <= 0) {
                    return response()->json([
                        'status' => -1,
                        'message' => 'Line ' . ($index + 1) . ': total weight must be greater than zero (weight per piece × qty).',
                    ], 422);
                }

                try {
                    $this->stockService->assertSaleWeightMatchesStockRatio(
                        (int) $item['qualityId'],
                        $lineWeight,
                        $pieces
                    );
                } catch (RuntimeException $e) {
                    return response()->json([
                        'status' => -1,
                        'message' => 'Line ' . ($index + 1) . ': ' . $e->getMessage(),
                    ]);
                }

                $grossW = (float) ($item['grossWeight'] ?? $lineWeight);
                $stoneW = (float) ($item['stoneWeight'] ?? 0);
                $purityK = $item['purityKarat'] ?? '22K';
                $tagNo = $item['tagNumber'] ?? null;

                $normalizedItems[] = [
                    'categoryId' => (int) $item['categoryId'],
                    'qualityId' => (int) $item['qualityId'],
                    'qty' => $qty,
                    'unit' => $item['unit'],
                    'lineWeight' => $lineWeight,
                    'grossWeight' => $grossW,
                    'stoneWeight' => $stoneW,
                    'purityKarat' => $purityK,
                    'tagNumber' => $tagNo,
                ];
                $totalWeightGrams += $lineWeight;
                $totalQty += $qty;
            }

            $first = $normalizedItems[0];
            $totalWeightGrams = round($totalWeightGrams, 3);

            $calc = app(\App\Services\JewelleryCalculationService::class);

            DB::beginTransaction();
            try {
                $challanMst = new tbl_challan_mst();
                $challanMst->challan_no = $challanNo;
                $challanMst->challan_date = $challanDate;
                $challanMst->customer_id = $customerId;
                $challanMst->sell_quality_id = $first['qualityId'];
                $challanMst->qty_unit = $first['unit'];
                $challanMst->total_qty = $totalQty;
                $challanMst->weight_grams = $totalWeightGrams;
                $challanMst->broker_id = $brokerId;
                $challanMst->challan_type = $first['categoryId'];
                $challanMst->is_direct = 0;
                $challanMst->save();

                foreach ($normalizedItems as $i => $item) {
                    $wDecomp = $calc->decomposeWeights($item['grossWeight'], $item['stoneWeight'], $item['purityKarat']);

                    tbl_challan_details::create([
                        'no' => $i + 1,
                        'qty' => $item['qty'],
                        'weight_grams' => $item['lineWeight'],
                        'gross_weight' => $wDecomp['gross_weight'],
                        'stone_weight' => $wDecomp['stone_weight'],
                        'net_weight' => $wDecomp['net_weight'],
                        'purity_karat' => $wDecomp['purity_karat'],
                        'fine_weight' => $wDecomp['fine_weight'],
                        'tag_number' => $item['tagNumber'],
                        'qty_unit' => $item['unit'],
                        'challan_mst_id' => $challanMst->challan_mst_id,
                        'challan_type' => $item['categoryId'],
                        'sell_quality_id' => $item['qualityId'],
                        'sell_category_id' => $item['categoryId'],
                        'challan_details_status' => true,
                    ]);
                }

                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                return response()->json([
                    'status' => -1,
                    'message' => 'Server Error',
                    'errors' => $e,
                ], 500);
            }

            return response()->json([
                'status' => 1,
                'message' => 'Sales bill added successfully.',
                'errors' => null,
            ], 200);
        }

        // Legacy single-product payload
        $sellCategoryId = $request->input('sellCategoryId');
        $sellQualityId = $request->input('sellQualityId');
        $qtyUnit = $request->input('qtyUnit');
        $totalQty = $request->input('totalQty');
        $weightGrams = (float) $request->input('weightGrams');
        $allData = $request->input('allData') ?: [];

        if (count($allData) > 0) {
            for ($i = 0; $i < count($allData); $i++) {
                if (!is_numeric($allData[$i]['qty']) || (float) $allData[$i]['qty'] <= 0) {
                    return response()->json([
                        'status' => -1,
                        'message' => 'Row ' . ($i + 1) . ' quantity is invalid.',
                        'errors' => null,
                    ]);
                }
            }
        }

        try {
            $this->stockService->assertSaleWeightMatchesStockRatio(
                (int) $sellQualityId,
                $weightGrams,
                max(1, (int) round((float) $totalQty))
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => -1,
                'message' => $e->getMessage(),
            ]);
        }

        DB::beginTransaction();
        try {
            $challanMst = new tbl_challan_mst();
            $challanMst->challan_no = $challanNo;
            $challanMst->challan_date = $challanDate;
            $challanMst->customer_id = $customerId;
            $challanMst->sell_quality_id = $sellQualityId;
            $challanMst->qty_unit = $qtyUnit;
            $challanMst->total_qty = $totalQty;
            $challanMst->weight_grams = $weightGrams;
            $challanMst->broker_id = $brokerId;
            $challanMst->challan_type = $sellCategoryId;
            $challanMst->is_direct = 0;
            $challanMst->save();

            if (count($allData) === 0) {
                $allData = [['qty' => $totalQty]];
            }

            $detailCount = count($allData);
            $allocated = 0.0;
            for ($i = 0; $i < $detailCount; $i++) {
                $qty = (float) $allData[$i]['qty'];
                if ($i === $detailCount - 1) {
                    $lineWeight = round($weightGrams - $allocated, 3);
                } else {
                    $lineWeight = (float) $totalQty > 0
                        ? round($weightGrams * ($qty / (float) $totalQty), 3)
                        : 0.0;
                    $allocated += $lineWeight;
                }

                $grossW = (float) ($allData[$i]['grossWeight'] ?? $lineWeight);
                $stoneW = (float) ($allData[$i]['stoneWeight'] ?? 0);
                $purityK = $allData[$i]['purityKarat'] ?? '22K';
                $tagNo = $allData[$i]['tagNumber'] ?? null;
                $wDecomp = $calc->decomposeWeights($grossW, $stoneW, $purityK);

                tbl_challan_details::create([
                    'no' => $i + 1,
                    'qty' => $qty,
                    'weight_grams' => $lineWeight,
                    'gross_weight' => $wDecomp['gross_weight'],
                    'stone_weight' => $wDecomp['stone_weight'],
                    'net_weight' => $wDecomp['net_weight'],
                    'purity_karat' => $wDecomp['purity_karat'],
                    'fine_weight' => $wDecomp['fine_weight'],
                    'tag_number' => $tagNo,
                    'qty_unit' => $qtyUnit,
                    'challan_mst_id' => $challanMst->challan_mst_id,
                    'challan_type' => $sellCategoryId,
                    'sell_quality_id' => $sellQualityId,
                    'sell_category_id' => $sellCategoryId,
                    'challan_details_status' => true,
                ]);
            }
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'status' => -1,
                'message' => 'Server Error',
                'errors' => $e,
            ], 500);
        }

        DB::commit();

        return response()->json([
            'status' => 1,
            'message' => 'Sales bill added successfully.',
            'errors' => null,
        ], 200);
    }

    /* updates challan according to data recieved */
    function updateChallan(Request $req){

        // validate data recived from request
        $validated = validator($req->all(),[
            'challanMstId' => 'required | numeric',
            'oldChallanNo' => 'required | numeric',
            'challanNo' => 'required | numeric',
            'challandate' => 'required | date',
            'oldChallanDate' => 'required | date',
            'company' => 'required | numeric',
            'category' => 'required | numeric',
            'quality' => 'required | numeric',
            'unit' => 'required |  max:10',
            'broker' => 'required | numeric'
        ]);

        if($validated->fails()){
            // if validation fails send negative response with err msg
            $res = array(
                "status" => -1,
                "statuscode" => 1,
                "message" => "The given data was invalid.",
                "errors" => $validated->errors()
            );
            return response()->json($res);
        }

        // recieve data from th request body into local variabvles
        $challanMstId = $req->input('challanMstId');
        $challanDate = $req->input('challandate');
        $oldChallanDate = $req->input('oldChallanDate');
        $challanNo = $req->input('challanNo');
        $oldChallanNo = $req->input("oldChallanNo");
        $company = $req->input('company');
        $broker = $req->input('broker');
        $category = $req->input('category');
        $quality = $req->input('quality');
        $unit = $req->input('unit');
        $challanDetails = $req->input('challanDetails');
        $newProductDetails = $req->input('newProductDetails');
        $challanDetailsIdsToBeDeleted = $req->input('challanDetailsIdsToBeDeleted');


        $notValidInEditing = array();
        $notValidInNew = array();

        // check whether all the challan details entries have valid quantity
        foreach($challanDetails as $index=>$challanDetail){
            if(empty($challanDetail['qty']) || !is_numeric($challanDetail['qty']) || (float) $challanDetail['qty'] <= 0){
                array_push($notValidInEditing, $index);
            }
        }

        foreach($newProductDetails as $index=>$newProductDetail){
            if(empty($newProductDetail['qty']) || !is_numeric($newProductDetail['qty']) || (float) $newProductDetail['qty'] <= 0){
                array_push($notValidInNew, $index);
            }
        }

        if(count($notValidInEditing) != 0 || count($notValidInNew) != 0){
            // if validation for new and updated challan no fails then return response with given field has error
            return response()->json(array(
                "status" => -1,
                "statuscode" => 2,
                "message" => "Some Of Given Field Is Empty",
                "notValidInEditing" => $notValidInEditing,
                "notValidInNew" => $notValidInNew
            ));
        }

        DB::beginTransaction(); // database transaction starts 

        try{

            // find challan with challan mst id
            $challanMst = tbl_challan_mst::find($challanMstId);

            $oldSellQuality = $challanMst->sell_quality_id;
            $oldCategory = tbl_sell_quality::getCategory($oldSellQuality);


            $financialYearOfNewChallanDate = $this->getFinancialYearOfChallanDateInArray($challanDate);
            $financialYearOfOldChallanDate = $this->getFinancialYearOfChallanDateInArray($oldChallanDate);
            

            if($financialYearOfNewChallanDate["fromDate"] == $financialYearOfOldChallanDate["fromDate"] && $financialYearOfNewChallanDate["toDate"] == $financialYearOfOldChallanDate["toDate"]){
                if((int)$challanNo != (int)$oldChallanNo){
                    if(tbl_challan_mst::isChallanNoExists($challanNo,$financialYearOfNewChallanDate['fromDate'], $financialYearOfNewChallanDate['toDate'])){
                        return response()->json(array(
                            "status" => -1,
                            "statuscode" => 3,
                            "message" => "Challan No Already Existes"
                        ));
                    }
                }
            }
            else{
                if(tbl_challan_mst::isChallanNoExists($challanNo,$financialYearOfNewChallanDate['fromDate'], $financialYearOfNewChallanDate['toDate'])){
                    return response()->json(array(
                        "status" => -1,
                        "statuscode" => 3,
                        "message" => "Challan No Already Existes"
                    ));
                }
            }
            


            $challanMst->challan_date = $challanDate;
            $challanMst->customer_id = $company;
            $challanMst->broker_id = $broker;
            $challanMst->sell_quality_id = $quality;
            $challanMst->qty_unit = $unit;
            $challanMst->challan_type = $category;
            $challanMst->challan_no = $challanNo;
            $challanMst->save(); // save challan update

            $n = count($challanDetailsIdsToBeDeleted);
        
            for($i=0; $i<$n; $i++){
                // update each challan details
                $challanDetailsEntry = tbl_challan_details::find($challanDetailsIdsToBeDeleted[$i]);
                $challanDetailsEntry->challan_details_status = false;
                $challanDetailsEntry->save(); // save changes
            }

            $noExists = array();
            $noError = array();
            $serialNo = 1;

            $n = count($challanDetails);
            for($i=0; $i<$n; $i++){
                $challanDetailsEntry = tbl_challan_details::find($challanDetails[$i]['challanDetailsId']);
                $challanDetailsEntry->no = $serialNo++;
                $challanDetailsEntry->qty = (float)$challanDetails[$i]['qty'];
                $challanDetailsEntry->challan_type = $category;
                if(!($challanDetailsEntry->save())){
                    array_push($noError, $serialNo - 1);
                }
            }

            $n = count($newProductDetails);
            for($i=0; $i<$n; $i++){
                $challanDetailsEntry = new tbl_challan_details();
                $challanDetailsEntry->no = $serialNo++;
                $challanDetailsEntry->qty = $newProductDetails[$i]['qty'];
                $challanDetailsEntry->challan_mst_id = $challanMstId;
                $challanDetailsEntry->challan_type = $category;
                $challanDetailsEntry->save();
            }

            $challanMst->total_qty = tbl_challan_details::where('challan_mst_id', $challanMstId)
                ->where('challan_details_status', 1)
                ->sum('qty');
            $challanMst->save();

            if(count($noExists) == 0 && count($noError) == 0){
                DB::commit(); // commit the database if everything goes right and send success response
                return response()->json(array(
                    "status" => 1,
                    "message" => "Challan Updated Successfully"
                ));
            }
            else{
                DB::rollback(); // rollback the changes if any exception occures and send the repsonse with err message
                return response()->json(array(
                    "status" => -1,
                    "statuscode" => 4,
                    "message" => "Something Went Wrong",
                    "noExists" => $noExists,
                    "noError" => $noError
                ));
            }
        }
        catch(Exception $e){
            DB::rollback(); // rollback the changes if any exception occures
            return response()->json(array(
                "status" => -1,
                "statuscode" => 5,
                "message" => "Something Went Wrong"
            ));
        }
    }

    /* will delete challan whenever called of given challan mst id */
    public function deleteChallan(Request $req, $challanMstId){

        // Database transaction Starts
        DB::beginTransaction();
        try{
            tbl_challan_details::where("challan_mst_id", $challanMstId)->update(['challan_details_status'=>false]);
            
            // find challan of given challan Mst Id
            $challan = tbl_challan_mst::find($challanMstId);
            $challanNo = $challan->challan_no;
            $challan->challan_mst_status = false;
            $challan->save(); // save the changes

            DB::commit(); // commit the changes if everuthing goes right

            return response()->json(array( // send response back
                "status" => 1,
                "challanNo" => $challanNo,
                "message" => "Challan Deleted Successfully" 
            ));
        }
        catch(Exception $e){
            DB::rollback(); // if any exception occures then rollback the database changes
            return response()->json(array(
                "status" => -1,
                "message" => "Challan Deletation Failed" 
            ));
        }
    }
}
