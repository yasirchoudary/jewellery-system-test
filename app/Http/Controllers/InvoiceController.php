<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\tbl_challan_mst;
use App\Models\tbl_challan_details;
use App\Models\tbl_gst_code;
use App\Models\tbl_invoice_mst;
use App\Models\tbl_invoice_detail;
use App\Models\tbl_stock_ledger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\StockService;
use App\Services\KarigarService;
use App\Models\tbl_karigar_job;

class InvoiceController extends Controller
{
    protected StockService $stockService;
    protected KarigarService $karigarService;

    public function __construct(StockService $stockService, KarigarService $karigarService)
    {
        $this->stockService = $stockService;
        $this->karigarService = $karigarService;
    }

    public function getAvailableChallansForInvoice(Request $request)
    {
        $challans = tbl_challan_mst::query()
            ->join('tbl_customers', 'tbl_customers.customer_id', '=', 'tbl_challan_msts.customer_id')
            ->join('tbl_challan_details', function ($join) {
                $join->on('tbl_challan_details.challan_mst_id', '=', 'tbl_challan_msts.challan_mst_id')
                    ->where('tbl_challan_details.challan_details_status', '=', 1);
            })
            ->leftJoin('tbl_invoice_msts', function ($join) {
                $join->on('tbl_invoice_msts.invoice_mst_id', '=', 'tbl_challan_msts.challan_mst_id')
                    ->where('tbl_invoice_msts.invoice_mst_status', '=', 1);
            })
            ->where('tbl_challan_msts.challan_mst_status', 1)
            ->where('tbl_challan_msts.is_direct', 0)
            ->whereNull('tbl_invoice_msts.invoice_mst_id')
            ->select(
                'tbl_challan_msts.challan_mst_id',
                'tbl_challan_msts.challan_no',
                'tbl_challan_msts.challan_date',
                'tbl_customers.customer_company_name'
            )
            ->distinct()
            ->orderByDesc('tbl_challan_msts.challan_date')
            ->orderByDesc('tbl_challan_msts.challan_no')
            ->get();

        return response()->json($challans->map(function ($challan) {
            $date = Carbon::parse($challan->getRawOriginal('challan_date'))->timezone(config('app.timezone'))->format('d-m-Y, h:i a');

            return [
                'challan_mst_id' => (int) $challan->challan_mst_id,
                'challan_no' => (int) $challan->challan_no,
                'challan_date' => $date,
                'customer_company_name' => $challan->customer_company_name,
            ];
        })->values());
    }

    public function getFromInvoiceNo(Request $request, $invoiceNo){
        $challans = tbl_challan_mst::query()
            ->where('challan_no', '=', $invoiceNo)
            ->where('challan_mst_status', '=', 1)
            ->get(['challan_date']);

        $financialYears = [];
        foreach ($challans as $challan) {
            $rawDate = $challan->getRawOriginal('challan_date');
            $fy = $this->getFinancialYearOfDate($rawDate);
            $fromYear = (int) substr($fy['fromDate'], 0, 4);
            $toYear = (int) substr($fy['toDate'], 0, 4);
            $financialYears[] = $fromYear.'-'.$toYear;
        }

        return array_values(array_unique($financialYears));
    }

    public function getFromInvoiceNoAndFinancialYear(Request $request, $invoiceNo, $fromDate, $toDate){
        
        $displayData = array(); 
        try{
            $challanMstIdQuery = tbl_challan_mst::select('challan_mst_id')->where('challan_no', '=', $invoiceNo)
            ->whereBetween('challan_date', [$fromDate, $toDate])->first();

            if($challanMstIdQuery === null){
                $res = array(
                    "status" => -1,
                    "message" => "Data Not Found",
                    "errors" => "Data Not Found"
                );
                return response()->json($res);       
            }
    
            $challanMstId = $challanMstIdQuery->challan_mst_id;

            $invoiceDataQuery = tbl_challan_mst::join('tbl_brokers', 'tbl_challan_msts.broker_id', '=', 'tbl_brokers.broker_id')
            ->join('tbl_customers', 'tbl_customers.customer_id', '=', 'tbl_challan_msts.customer_id')
            ->join('tbl_sell_qualities', 'tbl_sell_qualities.sell_quality_id', '=', 'tbl_challan_msts.sell_quality_id')
            ->join('tbl_sell_quality_categories', 'tbl_sell_quality_categories.sell_quality_category_id', '=', 'tbl_sell_qualities.sell_quality_category_id')
            ->select('tbl_challan_msts.challan_mst_id', 'tbl_challan_msts.challan_no','tbl_challan_msts.challan_date', 'tbl_challan_msts.total_qty', 'tbl_challan_msts.weight_grams', 'tbl_challan_msts.challan_type',
            'tbl_brokers.broker_name', 'tbl_customers.customer_company_name', 'tbl_customers.customer_gst_no', 
            'tbl_customers.customer_gst_code', 'tbl_customers.customer_address', 'tbl_sell_qualities.sell_quality_id', 'tbl_sell_qualities.quality_name', 'tbl_sell_quality_categories.metal_type')
            ->where('tbl_challan_msts.challan_no', '=', $invoiceNo)
            ->whereBetween('tbl_challan_msts.challan_date', [$fromDate, $toDate])
            ->where('tbl_challan_msts.challan_mst_status',1)
            ->where('tbl_brokers.broker_status', 1)
            ->where('tbl_customers.customer_status', 1)
            ->where('tbl_sell_qualities.sell_quality_status', 1)
            ->first();
        }catch(QueryException $e){
            $res = array(
                "status" => -1,
                "message" => "Server Error",
                "errors" => $e
            );
            return response()->json($res, 500);
        }

        if (!$invoiceDataQuery) {
            return response()->json([
                'status' => -1,
                'message' => 'Data Not Found',
                'errors' => 'Data Not Found',
            ]);
        }

        $detailRows = tbl_challan_details::with([
                'quality:sell_quality_id,quality_name',
                'category:sell_quality_category_id,sell_category_name,metal_type',
            ])
            ->where('challan_mst_id', $invoiceDataQuery->challan_mst_id)
            ->where('challan_details_status', true)
            ->orderBy('no')
            ->get();

        $items = $detailRows->map(function ($row) use ($invoiceDataQuery) {
            return [
                'challanDetailsId' => $row->challan_details_id,
                'categoryId' => $row->sell_category_id ?: $invoiceDataQuery->challan_type,
                'qualityId' => $row->sell_quality_id ?: $invoiceDataQuery->sell_quality_id,
                'qualityName' => optional($row->quality)->quality_name ?: $invoiceDataQuery->quality_name,
                'metalType' => optional($row->category)->metal_type ?: $invoiceDataQuery->metal_type,
                'qty' => (float) $row->qty,
                'weightGrams' => (float) ($row->weight_grams ?: 0),
                'unit' => $row->qty_unit ?: 'pcs',
            ];
        })->values()->all();

        if (count($items) === 0) {
            $items[] = [
                'challanDetailsId' => null,
                'categoryId' => $invoiceDataQuery->challan_type,
                'qualityId' => $invoiceDataQuery->sell_quality_id,
                'qualityName' => $invoiceDataQuery->quality_name,
                'metalType' => $invoiceDataQuery->metal_type,
                'qty' => (float) $invoiceDataQuery->total_qty,
                'weightGrams' => (float) $invoiceDataQuery->weight_grams,
                'unit' => 'pcs',
            ];
        } elseif (count($items) === 1 && empty($items[0]['weightGrams'])) {
            $items[0]['weightGrams'] = (float) $invoiceDataQuery->weight_grams;
        }

        $qualityNames = collect($items)->pluck('qualityName')->filter()->unique()->values()->all();
        $invoiceDataQuery->items = $items;
        $invoiceDataQuery->quality_name = count($qualityNames) ? implode(', ', $qualityNames) : $invoiceDataQuery->quality_name;
        $invoiceDataQuery->has_multiple_products = count($qualityNames) > 1
            || count(collect($items)->pluck('qualityId')->unique()) > 1;

        array_push($displayData, $invoiceDataQuery->total_qty ?? 0);
        array_push($displayData, $invoiceDataQuery);

        return $displayData;
        
    }

    public function getStateFromCode(Request $request, $gstCode){
        if ($gstCode === null || $gstCode === '') {
            return response()->json(['state_name' => '']);
        }

        return tbl_gst_code::select('state_name')->where('gst_code', '=', $gstCode)->first()
            ?: response()->json(['state_name' => '']);
    }

    public function addNewInvoiceFromChallan(Request $request){
        $hasItems = is_array($request->input('items')) && count($request->input('items')) > 0;

        $rules = [
            'invoiceId' => 'required | numeric',
            'invoiceDate' => 'required | date',
            'gstPercentage' => 'nullable | numeric | min:0',
            'bankId' => 'nullable | numeric',
            'dueDate' => 'required | date_format:Y-m-d',
            'fromDate' => 'required |date_format:Y-m-d',
            'toDate' => 'required |date_format:Y-m-d',
            'refineryCost' => 'nullable | numeric | min:0',
            'polishRatePerGram' => 'nullable | numeric | min:0',
            'mazduriCost' => 'nullable | numeric | min:0',
            'karigarJobId' => 'nullable | integer | exists:tbl_karigar_jobs,karigar_job_id',
        ];

        if ($hasItems) {
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.challanDetailsId'] = 'nullable|numeric';
            $rules['items.*.qualityId'] = 'required|numeric';
            $rules['items.*.categoryId'] = 'required|numeric';
            $rules['items.*.qty'] = 'required|numeric|min:0.001';
            $rules['items.*.weightGrams'] = 'required|numeric|min:0.001';
            $rules['items.*.rate'] = 'required|numeric|min:0';
            $rules['items.*.unit'] = 'nullable|max:20';
        } else {
            $rules['rate'] = 'required|numeric';
        }

        $validated = validator($request->all(), $rules);

        if($validated->fails()){
            $res = array(
                "status" => -1,
                "message" => "The given data was invalid.",
                "errors" => $validated->errors()
            );
            return response()->json($res);
        }

        $invoiceId = $request->input("invoiceId");
        $invoiceDate = $request->input("invoiceDate");
        $gstPercentage = (float) ($request->input('gstPercentage') ?? 0);
        $bankId = $request->input("bankId");
        $dueDate = $request->input("dueDate");
        $fromDate = $request->input("fromDate");
        $toDate = $request->input("toDate");

        if(tbl_invoice_mst::where('invoice_mst_id', '=', $invoiceId)->whereBetween('invoice_date', [$fromDate, $toDate])->exists()){
            $res = array(
                "status" => 0,
                "message" => "Record Already Exists",
                "errors" => null
            );
            return response()->json($res);
        }


        DB::beginTransaction();
        try{
            $challan = tbl_challan_mst::with(['challan_details'])
                ->where('challan_mst_id', $invoiceId)
                ->where('challan_mst_status', 1)
                ->first();

            if (!$challan) {
                DB::rollBack();
                return response()->json([
                    'status' => -1,
                    'message' => 'Sales bill not found for this invoice.',
                ]);
            }

            $lineInputs = [];
            if ($hasItems) {
                foreach ($request->input('items') as $index => $item) {
                    $lineWeight = (float) $item['weightGrams'];
                    if ($lineWeight <= 0) {
                        DB::rollBack();
                        return response()->json([
                            'status' => -1,
                            'message' => 'Line ' . ($index + 1) . ': weight must be greater than zero.',
                        ], 422);
                    }
                    $lineInputs[] = [
                        'categoryId' => (int) $item['categoryId'],
                        'qualityId' => (int) $item['qualityId'],
                        'qty' => (float) $item['qty'],
                        'unit' => $item['unit'] ?? 'pcs',
                        'lineWeight' => $lineWeight,
                        'rate' => (float) $item['rate'],
                        'pieces' => max(1, (int) round((float) $item['qty'])),
                    ];
                }
            } else {
                $weightGrams = (float) $challan->weight_grams;
                if ($weightGrams <= 0) {
                    DB::rollBack();
                    return response()->json([
                        'status' => -1,
                        'message' => 'Sales bill has no weight recorded. Edit the sales bill and add weight in grams.',
                    ]);
                }
                $lineInputs[] = [
                    'categoryId' => (int) $challan->challan_type,
                    'qualityId' => (int) $challan->sell_quality_id,
                    'qty' => (float) $challan->total_qty,
                    'unit' => $challan->qty_unit ?: 'pcs',
                    'lineWeight' => $weightGrams,
                    'rate' => (float) $request->input('rate'),
                    'pieces' => max(1, (int) round((float) $challan->total_qty)),
                ];
            }

            $totalWeightGrams = round(array_sum(array_column($lineInputs, 'lineWeight')), 3);
            $totalPieces = (int) array_sum(array_column($lineInputs, 'pieces'));
            $totalBaseAmount = 0.0;
            foreach ($lineInputs as $line) {
                $totalBaseAmount += round($line['lineWeight'] * $line['rate'], 2);
            }
            $totalBaseAmount = round($totalBaseAmount, 2);
            $gstAmount = round($totalBaseAmount * ($gstPercentage / 100), 2);
            $soldAmount = round($totalBaseAmount + $gstAmount, 2);

            $primaryMetalType = $this->stockService->resolveMetalTypeFromCategory($lineInputs[0]['categoryId']);
            $mazduriCost = $this->resolveKarigarMazduri($request);
            $processingCosts = $this->resolveSaleProcessingCosts(
                $primaryMetalType,
                $totalWeightGrams,
                $request->input('polishRatePerGram'),
                $mazduriCost,
                $request->filled('karigarJobId')
            );

            $totalCost = 0.0;
            $totalLineProfit = 0.0;
            $detailRows = [];

            foreach ($lineInputs as $line) {
                $metalType = $this->stockService->resolveMetalTypeFromCategory($line['categoryId']);
                $baseAmount = round($line['lineWeight'] * $line['rate'], 2);
                $lineGst = round($baseAmount * ($gstPercentage / 100), 2);
                $lineSold = round($baseAmount + $lineGst, 2);

                $grossW = (float) ($line['grossWeight'] ?? $line['lineWeight']);
                $stoneW = (float) ($line['stoneWeight'] ?? 0);
                $purityK = (float) ($line['purityKarat'] ?? 22.0);
                $tagNo = $line['tagNumber'] ?? null;

                $saleSummary = $this->stockService->recordSale(
                    $metalType,
                    $line['qualityId'],
                    $line['lineWeight'],
                    $line['pieces'],
                    $lineSold,
                    'sale_challan',
                    (int) $invoiceId,
                    optional($request->user())->id,
                    $grossW,
                    $stoneW,
                    $purityK,
                    $tagNo
                );

                $lineProfit = round($saleSummary['profit_amount'], 2);
                $totalCost += $saleSummary['cost_amount'];
                $totalLineProfit += $lineProfit;

                $calc = app(\App\Services\JewelleryCalculationService::class);
                $wDecomp = $calc->decomposeWeights($grossW, $stoneW, $purityK);

                $detailRows[] = [
                    'invoice_mst_id' => (int) $invoiceId,
                    'sell_quality_id' => $line['qualityId'],
                    'sell_category_id' => $line['categoryId'],
                    'qty' => $line['qty'],
                    'qty_unit' => $line['unit'],
                    'weight_grams' => $line['lineWeight'],
                    'gross_weight' => $wDecomp['gross_weight'],
                    'stone_weight' => $wDecomp['stone_weight'],
                    'net_weight' => $wDecomp['net_weight'],
                    'purity_karat' => $wDecomp['purity_karat'],
                    'fine_weight' => $wDecomp['fine_weight'],
                    'tag_number' => $tagNo,
                    'rate' => $line['rate'],
                    'base_amount' => $baseAmount,
                    'gst_percentage' => $gstPercentage,
                    'gst_amount' => $lineGst,
                    'sold_amount' => $lineSold,
                    'cost_amount' => $saleSummary['cost_amount'],
                    'profit_amount' => $lineProfit,
                    'invoice_detail_status' => true,
                ];
            }

            $invoice = new tbl_invoice_mst();
            $invoice->invoice_mst_id = $invoiceId;  
            $invoice->invoice_date = $invoiceDate;
            $invoice->no_of_units = $totalPieces;
            $invoice->rate = count($lineInputs) === 1 ? $lineInputs[0]['rate'] : 0;
            $invoice->weight_grams = $totalWeightGrams;
            $invoice->cost_amount = round($totalCost, 2);
            $invoice->sold_amount = $soldAmount;
            $invoice->profit_amount = round($totalLineProfit - $processingCosts['processing_cost_total'], 2);
            $invoice->refinery_cost = $processingCosts['refinery_cost'];
            $invoice->polish_rate_per_gram = $processingCosts['polish_rate_per_gram'];
            $invoice->polish_cost = $processingCosts['polish_cost'];
            $invoice->mazduri_cost = $processingCosts['mazduri_cost'];
            $invoice->gst_percentage = $gstPercentage;
            $invoice->bank_details_id = $bankId;
            $invoice->due_date = $dueDate;
            $invoice->save();

            foreach ($detailRows as $detailRow) {
                tbl_invoice_detail::create($detailRow);
            }

            if ($request->filled('karigarJobId')) {
                $this->karigarService->linkJobToInvoice((int) $request->input('karigarJobId'), (int) $invoiceId);
                $invoice->karigar_job_id = (int) $request->input('karigarJobId');
                $invoice->save();
            }

        }catch(\Exception $e){
            DB::rollBack();
            $res = array(
                "status" => -1,
                "message" => $e->getMessage() ?: "Server Error",
                "errors" => "Exception Generated"
            );
            return response()->json($res, 500);
        }

        DB::commit();

        $res = array(
            "status" => 1,
            "message" => "Invoice created, stock updated, profit recorded.",
            "profit" => $invoice->profit_amount ?? 0,
            "errors" => null
        );

        return response()->json($res, 200);


    }

    public function verifyInvoiceDate(Request $request, $invoiceDate, $fromDate, $toDate){
        $invoiceSplitDate = explode("-", $invoiceDate);
        $invoiceMonth = $invoiceSplitDate[1];
        $invoiceYear = $invoiceSplitDate[0];

        $fromDateInvoice = $invoiceYear;
        $toDateInvoice = $invoiceYear;
        if((int)$invoiceMonth<4){
            $fromDateInvoice = (int)$invoiceYear - 1;
        }else{
            $toDateInvoice = (int)$invoiceYear + 1;
        }

        $fromDateInvoice = $fromDateInvoice."-04-01";
        $toDateInvoice = $toDateInvoice."-03-31";

        if($fromDateInvoice === $fromDate && $toDateInvoice === $toDate){
            $res = array(
                "status" => "1",
                "message" => "Invoice Date is Valid.",
            );

            return response()->json($res);
        }else{
            $res = array(
                "status" => "0",
                "message" => "Invoice Date is Invalid. It must be in the entered Financial Year.",
            );

            return response()->json($res);
        }
    }

    public function getAllChallanInvoices(Request $req){

        $fromDate = request('fromdate', '2019-10-04');
        $toDate = request('todate', '2022-10-15');

        $customer = request('customer');
        $category = request('category');
        $quality = request('quality');
        $broker = request('broker');

        $paginate = request("paginate", 10);

        $sortDirection = request('sortdirection');
        if(!in_array($sortDirection,['asc', 'desc'])){
            $sortDirection = "desc";
        }

        $sortField = request('sortfield');
        if(!in_array($sortField,['invoice_date', 'invoice_no'])){
            $sortField = "invoice_date";
        }


        
        return tbl_invoice_mst::join('tbl_challan_msts', 'tbl_challan_msts.challan_mst_id', 'tbl_invoice_msts.invoice_mst_id')        
        ->join('tbl_sell_qualities', 'tbl_sell_qualities.sell_quality_id', 'tbl_challan_msts.sell_quality_id')
        ->join('tbl_sell_quality_categories', 'tbl_sell_quality_categories.sell_quality_category_id', 'tbl_sell_qualities.sell_quality_category_id')
        ->join('tbl_customers', 'tbl_challan_msts.customer_id', 'tbl_customers.customer_id')
        ->join('tbl_brokers', 'tbl_challan_msts.broker_id', 'tbl_brokers.broker_id')
        ->where('tbl_invoice_msts.invoice_mst_status', true)
        ->where('tbl_challan_msts.is_direct', false)
        ->whereDate('invoice_date', "<=", $toDate)
        ->whereDate('invoice_date', ">=", $fromDate)
        ->when($customer, function ($query) use ($customer){
            $query->where('tbl_challan_msts.customer_id',$customer);
        })
        ->when($category, function ($query) use ($category){
            $query->where('tbl_sell_quality_categories.sell_quality_category_id', $category);
        })
        ->when($quality, function ($query) use ($quality){
            $query->where('tbl_challan_msts.sell_quality_id', $quality);
        })
        ->when($broker, function ($query) use ($broker){
            $query->where('tbl_challan_msts.broker_id', $broker);
        })
        ->orderBy($sortField, $sortDirection)
        ->select('invoice_mst_id', 'invoice_date', 'due_date','challan_no','quality_name',  'tbl_challan_msts.customer_id', 'customer_company_name', 'tbl_challan_msts.broker_id', 'broker_name', 'tbl_sell_quality_categories.sell_category_name', 'tbl_challan_msts.total_qty', 'tbl_challan_msts.weight_grams', 'tbl_invoice_msts.rate', 'tbl_invoice_msts.gst_percentage', 'tbl_invoice_msts.sold_amount', 'tbl_invoice_msts.profit_amount')
        ->paginate($paginate);
    }

    public function getFinancialYearOfChallanDateInArray($challanDate){

        $challanSplitDate = explode("-",$challanDate);
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

    public function updateChallanInvoice(Request $req){
        $validated = validator($req->all(),[
            'invoiceId' => "required | numeric",
            'invoiceDate' => 'required | date',
            'oldInvoiceDate' => 'required | date_format:Y-m-d',
            'invoiceDueDate' => 'required | date_format:Y-m-d',
            'rate' => "required | numeric",
            'gstPercentages' => "required | numeric",
            'bankId' => "required | numeric"
        ]);

        if($validated->fails()){
            $res = array(
                "status" => -1,
                "statusCode" => 1,
                "message" => "The given data was invalid.",
                "errors" => $validated->errors()
            );

            return response()->json($res);
        }

        $invoiceId = $req->input('invoiceId');
        $invoiceDate = $req->input('invoiceDate');
        $oldInvoiceDate = $req->input('oldInvoiceDate');
        $invoiceDueDate = $req->input('invoiceDueDate');
        $rate = $req->input('rate');
        $gstPercentage = $req->input('gstPercentage');
        $bankId = $req -> input("bankId");

        

        DB::beginTransaction();

        try{
            
            $challanMst = tbl_challan_mst::find($invoiceId);
            $challanDate = $challanMst -> challan_date;

            if($oldInvoiceDate != $invoiceDate ){
                $financialYearOfChallanDate = $this->getFinancialYearOfChallanDateInArray($challanDate);
                $financialYearOfInvoiceDate = $this->getFinancialYearOfChallanDateInArray($invoiceDate);

                if($financialYearOfChallanDate['fromDate'] != $financialYearOfInvoiceDate['fromDate'] || $financialYearOfChallanDate['toDate'] != $financialYearOfInvoiceDate['toDate']){
                    return response()->json(array(
                        "status" => 0,
                        "message" => `Challan Belongs To Financial Year ${$financialYearOfChallanDate['fromDate']} to ${$financialYearOfChallanDate['toDate']}`
                    ));
                }
            }

            $invoiceMst = tbl_invoice_mst::find($invoiceId)->where('invoice_mst_status', true);
            $invoiceMst->invoice_date = $invoiceDate;
            $invoiceMst->rate = $rate;
            $invoiceMst->gst_percentage = $gstPercentage;
            $invoiceMst->bank_detials_id = $bankId;

            $invoiceMst->save();
            DB::commit();

            return response()->json(array(
                "status" => 1,
                "message" => "Invoice Updated Successfully"
            ));
        }
        catch(Exception $e){
            DB::rollback();
            return response()->json(array(
                'status' => -1,
                "statusCode" => 0,
                "message" => "Something Went Wrong While Generating Invoice"
            ));
        }
    }

    public function getChallanInvoiceOfInvoiceId(Request $req, $invoiceMstId){
        
        $data = tbl_invoice_mst::with(['challanMstForInvoiceFromChallan', 'bank:bank_details_id,bank_name,branch_name,account_no'])
            ->where('invoice_mst_status', true)
            ->where('invoice_mst_id', $invoiceMstId)
            ->select('invoice_mst_id', 'invoice_date', 'rate', 'gst_percentage', 'due_date', 'bank_details_id', 'weight_grams')
            ->first();

        if(!$data){
            return response()->json(array(
                "status" => 0,
                "message" => "Invoice With Given Invoice No Doesnt Exists"
            ));
        }

        return response()->json(array(
            "status" => 1,
            "message" => "Invoice Data Fetched",
            "data" => $data
        ));
    }

    public function updateInvoiceForChallan(Request $req){
        $validated = validator($req->all(),[
            'invoiceId' => 'required | numeric',
            'dueDate' => 'required | date_format:Y-m-d',
            'rate' => 'required | numeric',
            'gstPercentage' => 'required | numeric',
            'bankId' => 'required | numeric'
        ]);

        if($validated->fails()){
            $res = array(
                "status" => 0,
                "message" => "The given data was invalid.",
                "errors" => $validated->errors()
            );
            return response()->json($res);
        }

        $invoiceId = $req->input('invoiceId');
        $dueDate = $req->input('dueDate');
        $rate = $req->input('rate');
        $gstPercentage = $req->input('gstPercentage');
        $bankId = $req->input('bankId');

        DB::beginTransaction();

        try{
            $invoice = tbl_invoice_mst::where('invoice_mst_id', $invoiceId)
                ->where('invoice_mst_status', true)
                ->first();

            if (!$invoice) {
                DB::rollBack();

                return response()->json([
                    'status' => -1,
                    'message' => 'Invoice not found.',
                ]);
            }

            $amounts = $this->recalculateInvoiceAmounts($invoice, (float) $rate, (float) $gstPercentage);

            $invoice->due_date = $dueDate;
            $invoice->rate = $rate;
            $invoice->gst_percentage = $gstPercentage;
            $invoice->bank_details_id = $bankId;
            $invoice->sold_amount = $amounts['sold_amount'];
            $invoice->profit_amount = $amounts['profit_amount'];
            $invoice->refinery_cost = $amounts['processing_costs']['refinery_cost'];
            $invoice->polish_rate_per_gram = $amounts['processing_costs']['polish_rate_per_gram'];
            $invoice->polish_cost = $amounts['processing_costs']['polish_cost'];
            $invoice->mazduri_cost = $amounts['processing_costs']['mazduri_cost'];
    
            $invoice->save();

            $this->syncStockLedgerSaleAmount(
                (int) $invoiceId,
                $amounts['reference_type'],
                $amounts['weight_grams'],
                $amounts['sold_amount'],
                $amounts['cost_amount'],
                $amounts['profit_amount']
            );

            DB::commit();

            return response()->json(array(
                "status" => 1,
                "message" => "Invoice Updated Successfully",
                "sold_amount" => $amounts['sold_amount'],
                "profit_amount" => $amounts['profit_amount'],
            ));
        }
        catch(\Exception $e){
            DB::rollback();

            return response()->json(array(
                "status" => -1,
                "message" => $e->getMessage() ?: "Err In Updating Invoice"
            ));
        }
        
    }

    public function getFinancialYearOfDate($date){

        $date = explode("-",$date);
        $month = $date[1];
        $year = $date[0];
        
        $fromDate = $year;
        $toDate = $year;
        if((int)$month<4){
            $fromDate = (int)$year - 1;
        }else{
            $toDate = (int)$year + 1;
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

    public function addNewDirectInvoice(Request $req){
        $payload = $req->all();
        if (empty($payload['items']) || !is_array($payload['items'])) {
            if ($req->filled('qualityId')) {
                $payload['items'] = [[
                    'categoryId' => $req->input('categoryId'),
                    'qualityId' => $req->input('qualityId'),
                    'qty' => $req->input('qty'),
                    'unit' => $req->input('unit'),
                    'weightGrams' => $req->input('weightGrams'),
                    'rate' => $req->input('rate'),
                ]];
            }
        }

        $validated = validator($payload, [
            'invoiceDate' => 'required|date',
            'invoiceNo' => 'required|numeric',
            'customerId' => 'required|numeric',
            'brokerId' => 'required|numeric',
            'gstPercentage' => 'required|numeric',
            'refineryCost' => 'nullable|numeric|min:0',
            'polishRatePerGram' => 'nullable|numeric|min:0',
            'mazduriCost' => 'nullable|numeric|min:0',
            'karigarJobId' => 'nullable|integer|exists:tbl_karigar_jobs,karigar_job_id',
            'items' => 'required|array|min:1',
            'items.*.categoryId' => 'required|numeric',
            'items.*.qualityId' => 'required|numeric',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required',
            'items.*.weightGrams' => 'required|numeric|min:0.001',
            'items.*.rate' => 'required|numeric|min:0',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'The given data was invalid.',
                'errors' => $validated->errors(),
            ]);
        }

        $invoiceNo = $req->input('invoiceNo');
        $invoiceDate = $req->input('invoiceDate');
        $customerId = $req->input('customerId');
        $brokerId = $req->input('brokerId');
        $gstPercentage = (float) $req->input('gstPercentage');
        $items = $payload['items'];

        $normalizedItems = [];
        $totalWeightGrams = 0.0;
        $totalPieces = 0;
        $totalBaseAmount = 0.0;

        foreach ($items as $index => $item) {
            $qty = (float) $item['qty'];
            $weightPerPiece = (float) $item['weightGrams'];
            $rate = (float) $item['rate'];
            $lineWeight = round($weightPerPiece * $qty, 3);
            $pieces = max(1, (int) round($qty));
            $baseAmount = round($lineWeight * $rate, 2);

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
            } catch (\RuntimeException $e) {
                return response()->json([
                    'status' => -1,
                    'message' => 'Line ' . ($index + 1) . ': ' . $e->getMessage(),
                ]);
            }

            $normalizedItems[] = [
                'categoryId' => (int) $item['categoryId'],
                'qualityId' => (int) $item['qualityId'],
                'qty' => $qty,
                'unit' => $item['unit'],
                'weightPerPiece' => $weightPerPiece,
                'lineWeight' => $lineWeight,
                'pieces' => $pieces,
                'rate' => $rate,
                'baseAmount' => $baseAmount,
            ];

            $totalWeightGrams += $lineWeight;
            $totalPieces += $pieces;
            $totalBaseAmount += $baseAmount;
        }

        $totalWeightGrams = round($totalWeightGrams, 3);
        $totalBaseAmount = round($totalBaseAmount, 2);
        $gstAmount = round($totalBaseAmount * ($gstPercentage / 100), 2);
        $soldAmount = round($totalBaseAmount + $gstAmount, 2);

        $financialYear = $this->getFinancialYearOfDate($invoiceDate);

        if (tbl_invoice_mst::hasChallanOrInvoiceWithInGivenInvoceNoAndFinancialYear($invoiceNo, $financialYear)) {
            return response()->json([
                'status' => 0,
                'message' => 'Invoice No. Already Exists Or Challan For This Invoice Is Generated.',
            ]);
        }

        $bankDetailsId = DB::table('tbl_bank_details')
            ->where('bank_details_status', 1)
            ->value('bank_details_id');

        DB::beginTransaction();

        try {
            $first = $normalizedItems[0];
            $primaryMetalType = $this->stockService->resolveMetalTypeFromCategory($first['categoryId']);
            $mazduriCost = $this->resolveKarigarMazduri($req);
            $processingCosts = $this->resolveSaleProcessingCosts(
                $primaryMetalType,
                $totalWeightGrams,
                $req->input('polishRatePerGram'),
                $mazduriCost,
                $req->filled('karigarJobId')
            );

            $challanMst = new tbl_challan_mst();
            $challanMst->challan_no = $invoiceNo;
            $challanMst->challan_date = $invoiceDate;
            $challanMst->customer_id = $customerId;
            $challanMst->sell_quality_id = $first['qualityId'];
            $challanMst->qty_unit = $first['unit'];
            $challanMst->total_qty = array_sum(array_column($normalizedItems, 'qty'));
            $challanMst->weight_grams = $totalWeightGrams;
            $challanMst->broker_id = $brokerId;
            $challanMst->challan_type = $first['categoryId'];
            $challanMst->is_direct = 1;
            $challanMst->save();

            $totalCost = 0.0;
            $totalLineProfit = 0.0;
            $detailRows = [];

            foreach ($normalizedItems as $item) {
                $metalType = $this->stockService->resolveMetalTypeFromCategory($item['categoryId']);
                $lineGst = round($item['baseAmount'] * ($gstPercentage / 100), 2);
                $lineSold = round($item['baseAmount'] + $lineGst, 2);

                $grossW = (float) ($item['grossWeight'] ?? $item['lineWeight']);
                $stoneW = (float) ($item['stoneWeight'] ?? 0);
                $purityK = (float) ($item['purityKarat'] ?? 22.0);
                $tagNo = $item['tagNumber'] ?? null;

                $saleSummary = $this->stockService->recordSale(
                    $metalType,
                    $item['qualityId'],
                    $item['lineWeight'],
                    $item['pieces'],
                    $lineSold,
                    'sale',
                    $challanMst->challan_mst_id,
                    optional($req->user())->id,
                    $grossW,
                    $stoneW,
                    $purityK,
                    $tagNo
                );

                $lineProfit = round($saleSummary['profit_amount'], 2);
                $totalCost += $saleSummary['cost_amount'];
                $totalLineProfit += $lineProfit;

                $calc = app(\App\Services\JewelleryCalculationService::class);
                $wDecomp = $calc->decomposeWeights($grossW, $stoneW, $purityK);

                $detailRows[] = [
                    'invoice_mst_id' => $challanMst->challan_mst_id,
                    'sell_quality_id' => $item['qualityId'],
                    'sell_category_id' => $item['categoryId'],
                    'qty' => $item['qty'],
                    'qty_unit' => $item['unit'],
                    'weight_grams' => $item['lineWeight'],
                    'gross_weight' => $wDecomp['gross_weight'],
                    'stone_weight' => $wDecomp['stone_weight'],
                    'net_weight' => $wDecomp['net_weight'],
                    'purity_karat' => $wDecomp['purity_karat'],
                    'fine_weight' => $wDecomp['fine_weight'],
                    'tag_number' => $tagNo,
                    'rate' => $item['rate'],
                    'base_amount' => $item['baseAmount'],
                    'gst_percentage' => $gstPercentage,
                    'gst_amount' => $lineGst,
                    'sold_amount' => $lineSold,
                    'cost_amount' => $saleSummary['cost_amount'],
                    'profit_amount' => $lineProfit,
                    'invoice_detail_status' => true,
                ];
            }

            $invoiceMst = new tbl_invoice_mst();
            $invoiceMst->invoice_mst_id = $challanMst->challan_mst_id;
            $invoiceMst->invoice_date = $invoiceDate;
            $invoiceMst->no_of_units = $totalPieces;
            $invoiceMst->rate = count($normalizedItems) === 1 ? $first['rate'] : 0;
            $invoiceMst->weight_grams = $totalWeightGrams;
            $invoiceMst->cost_amount = round($totalCost, 2);
            $invoiceMst->sold_amount = $soldAmount;
            $invoiceMst->profit_amount = round($totalLineProfit - $processingCosts['processing_cost_total'], 2);
            $invoiceMst->refinery_cost = $processingCosts['refinery_cost'];
            $invoiceMst->polish_rate_per_gram = $processingCosts['polish_rate_per_gram'];
            $invoiceMst->polish_cost = $processingCosts['polish_cost'];
            $invoiceMst->mazduri_cost = $processingCosts['mazduri_cost'];
            $invoiceMst->bank_details_id = $bankDetailsId;
            $invoiceMst->gst_percentage = $gstPercentage;
            $invoiceMst->save();

            foreach ($detailRows as $detailRow) {
                tbl_invoice_detail::create($detailRow);
            }

            if ($req->filled('karigarJobId')) {
                $this->karigarService->linkJobToInvoice((int) $req->input('karigarJobId'), (int) $challanMst->challan_mst_id);
                $invoiceMst->karigar_job_id = (int) $req->input('karigarJobId');
                $invoiceMst->save();
            }

            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Sale bill created, stock updated, profit recorded.',
                'profit' => $invoiceMst->profit_amount,
                'sold_amount' => $soldAmount,
                'processing_cost_total' => $processingCosts['processing_cost_total'],
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return response()->json([
                'status' => -1,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => -1,
                'message' => $e->getMessage() ?: 'Something went wrong while generating invoice.',
            ], 500);
        }
    }

    public function getAllDirectInvoices(Request $req){
        
        $fromDate = request('fromdate', Carbon::now()->subDays(10));
        $toDate = request('todate', Carbon::now());

        //$fromDate = request('fromdate', '2019-10-04');
        //$toDate = request('todate', '2022-10-15');

        $customer = request('customer');
        $category = request('category');
        $quality = request('quality');
        $broker = request('broker');

        $paginate = request("paginate", 10);

        $sortDirection = request('sortdirection');
        if(!in_array($sortDirection,['asc', 'desc'])){
            $sortDirection = "desc";
        }

        $sortField = request('sortfield');
        if(!in_array($sortField,['invoice_date', 'invoice_no'])){
            $sortField = "invoice_date";
        }


        
        $query = tbl_invoice_mst::join('tbl_challan_msts', 'tbl_challan_msts.challan_mst_id', 'tbl_invoice_msts.invoice_mst_id')        
        ->join('tbl_sell_qualities', 'tbl_sell_qualities.sell_quality_id', 'tbl_challan_msts.sell_quality_id')
        ->join('tbl_sell_quality_categories', 'tbl_sell_quality_categories.sell_quality_category_id', 'tbl_sell_qualities.sell_quality_category_id')
        ->join('tbl_customers', 'tbl_challan_msts.customer_id', 'tbl_customers.customer_id')
        ->join('tbl_brokers', 'tbl_challan_msts.broker_id', 'tbl_brokers.broker_id')
        ->where('tbl_invoice_msts.invoice_mst_status', true)
        ->where('tbl_challan_msts.is_direct', true)
        ->whereDate('invoice_date', "<=", $toDate)
        ->whereDate('invoice_date', ">=", $fromDate)
        ->when($customer, function ($query) use ($customer){
            $query->where('tbl_challan_msts.customer_id',$customer);
        })
        ->when($category, function ($query) use ($category){
            $query->where('tbl_sell_quality_categories.sell_quality_category_id', $category);
        })
        ->when($quality, function ($query) use ($quality){
            $query->where(function ($q) use ($quality) {
                $q->where('tbl_challan_msts.sell_quality_id', $quality)
                    ->orWhereExists(function ($sub) use ($quality) {
                        $sub->select(DB::raw(1))
                            ->from('tbl_invoice_details')
                            ->whereColumn('tbl_invoice_details.invoice_mst_id', 'tbl_invoice_msts.invoice_mst_id')
                            ->where('tbl_invoice_details.invoice_detail_status', true)
                            ->where('tbl_invoice_details.sell_quality_id', $quality);
                    });
            });
        })
        ->when($broker, function ($query) use ($broker){
            $query->where('tbl_challan_msts.broker_id', $broker);
        })
        ->orderBy($sortField, $sortDirection)
        ->select('invoice_mst_id', 'invoice_date', 'challan_no','quality_name',  'tbl_challan_msts.customer_id', 'customer_company_name', 'tbl_challan_msts.broker_id', 'broker_name', 'tbl_sell_quality_categories.sell_category_name', 'tbl_challan_msts.total_qty', 'tbl_challan_msts.weight_grams', 'tbl_invoice_msts.rate', 'tbl_invoice_msts.gst_percentage', 'tbl_invoice_msts.sold_amount', 'tbl_invoice_msts.profit_amount');

        $paginator = $query->paginate($paginate);

        $paginator->getCollection()->transform(function ($row) {
            $names = tbl_invoice_detail::where('invoice_mst_id', $row->invoice_mst_id)
                ->where('invoice_detail_status', true)
                ->join('tbl_sell_qualities', 'tbl_sell_qualities.sell_quality_id', 'tbl_invoice_details.sell_quality_id')
                ->pluck('quality_name')
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

    public function getDirectInvoiceOfInvoiceId(Request $req, $invoiceMstId){
        
        $data = tbl_invoice_mst::with(['challanMstForInvoice', 'details'])
            ->where('invoice_mst_status', true)
            ->where('invoice_mst_id', $invoiceMstId)
            ->select(
                'invoice_mst_id',
                'invoice_date',
                'no_of_units',
                'rate',
                'gst_percentage',
                'weight_grams',
                'sold_amount',
                'profit_amount',
                'polish_rate_per_gram',
                'polish_cost',
                'mazduri_cost',
                'karigar_job_id'
            )
            ->first();

        if(!$data){
            return response()->json(array(
                "status" => 0,
                "message" => "Invoice With Given Invoice No Doesnt Exists"
            ));
        }

        $payload = $data->toArray();
        $payload['quality_names'] = collect($data->details)->map(function ($row) {
            return optional($row->quality)->quality_name;
        })->filter()->values()->all();

        if (!$payload['quality_names'] && optional($data->challanMstForInvoice)->quality) {
            $payload['quality_names'] = [$data->challanMstForInvoice->quality->quality_name];
        }

        return response()->json(array(
            "status" => 1,
            "message" => "Invoice Data Fetched",
            "data" => $payload
        ));
    }

    public function updateDirectInvoice(Request $req){
        $validated = validator($req->all(),[
            'invoiceId' => 'required | numeric',
            'invoiceDate' => 'required | date',
            'oldInvoiceDate' => 'required | date_format:Y-m-d',
            'invoiceNo' => 'required | numeric',
            'oldInvoiceNo' => 'required | numeric',
            'customer' => 'required | numeric',
            'broker' => 'required | numeric',
            'category' => 'required | numeric',
            'quality' => 'required | numeric',
            'qty' => "required | numeric | min:0.001",
            "unit" => "required",
            'rate' => "required | numeric",
            'gstPercentage' => "required | numeric"
        ]);

        if($validated->fails()){
            $res = array(
                "status" => -1,
                "statusCode" => 1,
                "message" => "The given data was invalid.",
                "errors" => $validated->errors()
            );

            return response()->json($res);
        }

        $invoiceId = $req->input('invoiceId');
        $invoiceNo = $req->input('invoiceNo');
        $oldInvoiceNo = $req->input('oldInvoiceNo');
        $invoiceDate = $req->input('invoiceDate');
        $oldInvoiceDate = $req->input('oldInvoiceDate');
        $customerId = $req->input('customer');
        $brokerId = $req->input('broker');
        $categoryId = $req->input('category');
        $qualityId = $req->input('quality');
        $qty = $req->input('qty');
        $unit = $req->input('unit');
        $rate = $req->input('rate');
        $gstPercentage = $req->input('gstPercentage');


        $financialYearOfNewInvoiceDate = $this->getFinancialYearOfDate($invoiceDate);
        $financialYearOfOldInvoiceDate = $this->getFinancialYearOfDate($oldInvoiceDate);
        
        if($financialYearOfNewInvoiceDate['fromDate'] == $financialYearOfOldInvoiceDate['fromDate'] && $financialYearOfNewInvoiceDate['toDate'] == $financialYearOfOldInvoiceDate['toDate']){
            if($oldInvoiceNo != $invoiceNo){
                if(tbl_invoice_mst::hasChallanOrInvoiceWithInGivenInvoceNoAndFinancialYear($invoiceNo, $financialYearOfNewInvoiceDate)){
                    return response()->json(array(
                        'status' => 0,
                        "message" => "Invoice No. Already Exists Or Challan For This Invoice Is Generated."
                    )); 
                }
        
            }
        }else{
            if(tbl_invoice_mst::hasChallanOrInvoiceWithInGivenInvoceNoAndFinancialYear($invoiceNo, $financialYearOfNewInvoiceDate)){
                return response()->json(array(
                    'status' => 0,
                    "message" => "Invoice No. Already Exists Or Challan For This Invoice Is Generated."
                )); 
            }
        }

        
        DB::beginTransaction();

        try{
            

            $invoiceMst = tbl_invoice_mst::find($invoiceId);
            $invoiceMst->invoice_date = $invoiceDate;
            $invoiceMst->no_of_units = max(1, (int) round((float) $qty));
            $invoiceMst->rate = $rate;
            $invoiceMst->gst_percentage = $gstPercentage;

            $amounts = $this->recalculateInvoiceAmounts($invoiceMst, (float) $rate, (float) $gstPercentage);
            $invoiceMst->sold_amount = $amounts['sold_amount'];
            $invoiceMst->profit_amount = $amounts['profit_amount'];
            $invoiceMst->save();

            $this->syncStockLedgerSaleAmount(
                (int) $invoiceId,
                $amounts['reference_type'],
                $amounts['weight_grams'],
                $amounts['sold_amount'],
                $amounts['cost_amount'],
                $amounts['profit_amount']
            );

            $challanMst = tbl_challan_mst::find($invoiceId);
            $challanMst->challan_no = $invoiceNo;
            $challanMst->challan_date = $invoiceDate;
            $challanMst->customer_id = $customerId;
            $challanMst->sell_quality_id = $qualityId;
            $challanMst->qty_unit = $unit;
            $challanMst->total_qty = $qty;
            $challanMst->broker_id = $brokerId;
            $challanMst->challan_type = $categoryId;
            $challanMst->is_direct = 1;

            $challanMst->save();



            DB::commit();

            return response()->json(array(
                "status" => 1,
                "message" => "Invoice Updated Successfully"
            ));
        }
        catch(Exception $e){
            DB::rollback();
            return response()->json(array(
                'status' => -1,
                "statusCode" => 0,
                "message" => "Something Went Wrong While Generating Invoice"
            ));
        }

    }

    public function deleteDirectInvoice(Request $req, $invoiceMstId){
        
        DB::beginTransaction();
        try{
            $invoiceMst = tbl_invoice_mst::find($invoiceMstId);

            if (!$invoiceMst || !$invoiceMst->invoice_mst_status) {
                DB::rollBack();
                return response()->json([
                    'status' => -1,
                    'message' => 'Invoice not found or already deleted.',
                ]);
            }

            $challanMst = tbl_challan_mst::find($invoiceMstId);

            if (!$challanMst || !$challanMst->challan_mst_status) {
                DB::rollBack();
                return response()->json([
                    'status' => -1,
                    'message' => 'Sales bill not found for this invoice.',
                ]);
            }

            $this->stockService->reverseSale(
                'sale',
                (int) $invoiceMstId,
                optional($req->user())->id
            );

            tbl_invoice_detail::where('invoice_mst_id', $invoiceMstId)
                ->update(['invoice_detail_status' => false]);

            if ($invoiceMst->karigar_job_id) {
                tbl_karigar_job::where('karigar_job_id', $invoiceMst->karigar_job_id)
                    ->update(['invoice_mst_id' => null]);
            }

            $invoiceMst->invoice_mst_status = false;
            $invoiceMst->save();

            $invoiceNo = $challanMst->challan_no;
            $challanMst->challan_mst_status = false;
            $challanMst->save();

            DB::commit();

            return response()->json(array(
                "status" => 1,
                'invoiceNo' => $invoiceNo,
                "message" => "Invoice deleted and stock restored."
            ));
        }
        catch(\Exception $e){
            DB::rollback();
            return response()->json(array(
                "status" => -1,
                "message" => $e->getMessage() ?: "Invoice deletion failed."
            ));
        }
    }

    /**
     * Delete invoice created from a sales bill.
     * Restores stock and removes the invoice, but keeps the sales bill so it can be invoiced again.
     */
    public function deleteChallanInvoice(Request $req, $invoiceMstId)
    {
        DB::beginTransaction();
        try {
            $invoiceMst = tbl_invoice_mst::find($invoiceMstId);

            if (!$invoiceMst || !$invoiceMst->invoice_mst_status) {
                DB::rollBack();
                return response()->json([
                    'status' => -1,
                    'message' => 'Invoice not found or already deleted.',
                ]);
            }

            $challanMst = tbl_challan_mst::find($invoiceMstId);

            if (!$challanMst || !$challanMst->challan_mst_status || $challanMst->is_direct) {
                DB::rollBack();
                return response()->json([
                    'status' => -1,
                    'message' => 'Sales bill invoice not found.',
                ]);
            }

            $this->stockService->reverseSale(
                'sale_challan',
                (int) $invoiceMstId,
                optional($req->user())->id
            );

            tbl_invoice_detail::where('invoice_mst_id', $invoiceMstId)
                ->update(['invoice_detail_status' => false]);

            if ($invoiceMst->karigar_job_id) {
                tbl_karigar_job::where('karigar_job_id', $invoiceMst->karigar_job_id)
                    ->update(['invoice_mst_id' => null]);
            }

            $invoiceNo = $challanMst->challan_no;
            $invoiceMst->invoice_mst_status = false;
            $invoiceMst->save();

            DB::commit();

            return response()->json([
                'status' => 1,
                'invoiceNo' => $invoiceNo,
                'message' => 'Invoice deleted and stock restored. Sales bill kept for re-invoicing.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => -1,
                'message' => $e->getMessage() ?: 'Invoice deletion failed.',
            ]);
        }
    }

    private function recalculateInvoiceAmounts(
        tbl_invoice_mst $invoice,
        float $rate,
        float $gstPercentage,
        $refineryCost = null,
        $polishRatePerGram = null,
        $mazduriCost = null
    ): array {
        $challan = tbl_challan_mst::where('challan_mst_id', $invoice->invoice_mst_id)
            ->where('challan_mst_status', true)
            ->first();

        if (!$challan) {
            throw new \RuntimeException('Sales bill not found for this invoice.');
        }

        $weightGrams = (float) ($invoice->weight_grams ?: $challan->weight_grams);
        if ($weightGrams <= 0) {
            throw new \RuntimeException('Invoice has no weight recorded.');
        }

        $metalType = $this->stockService->resolveMetalTypeFromCategory((int) $challan->challan_type);
        $processingCosts = $this->resolveSaleProcessingCosts(
            $metalType,
            $weightGrams,
            $polishRatePerGram ?? $invoice->polish_rate_per_gram,
            $mazduriCost ?? $invoice->mazduri_cost,
            (bool) $invoice->karigar_job_id
        );

        $baseAmount = round($weightGrams * $rate, 2);
        $gstAmount = round($baseAmount * ($gstPercentage / 100), 2);
        $soldAmount = round($baseAmount + $gstAmount, 2);
        $costAmount = (float) ($invoice->cost_amount ?? 0);
        $profitAmount = round($soldAmount - $costAmount - $processingCosts['processing_cost_total'], 2);

        return [
            'weight_grams' => $weightGrams,
            'sold_amount' => $soldAmount,
            'cost_amount' => $costAmount,
            'profit_amount' => $profitAmount,
            'processing_costs' => $processingCosts,
            'reference_type' => $challan->is_direct ? 'sale' : 'sale_challan',
        ];
    }

    private function syncStockLedgerSaleAmount(
        int $invoiceId,
        string $referenceType,
        float $weightGrams,
        float $soldAmount,
        float $costAmount,
        float $profitAmount
    ): void {
        $entry = tbl_stock_ledger::where('reference_type', $referenceType)
            ->where('reference_id', $invoiceId)
            ->where('transaction_type', 'sale')
            ->first();

        if (!$entry) {
            return;
        }

        $entry->amount = $soldAmount;
        $entry->rate_per_gram = $weightGrams > 0 ? round($soldAmount / $weightGrams, 2) : 0;
        $entry->notes = 'Cost: ' . $costAmount . ', Profit: ' . $profitAmount;
        $entry->save();
    }

    private function resolveSaleProcessingCosts(
        string $metalType,
        float $weightGrams,
        $polishRatePerGram,
        $mazduriCost,
        bool $karigarJobLinked = false
    ): array {
        $polishRate = $metalType === 'gold'
            ? round(max((float) ($polishRatePerGram ?? 0), 0), 2)
            : 0.0;
        $polishTotal = $metalType === 'gold'
            ? round($polishRate * $weightGrams, 2)
            : 0.0;

        $mazduri = 0.0;
        if ($metalType === 'silver') {
            $mazduri = round(max((float) ($mazduriCost ?? 0), 0), 2);
        } elseif ($karigarJobLinked) {
            $mazduri = round(max((float) ($mazduriCost ?? 0), 0), 2);
        }

        return [
            'refinery_cost' => 0.0,
            'polish_rate_per_gram' => $polishRate,
            'polish_cost' => $polishTotal,
            'mazduri_cost' => $mazduri,
            'processing_cost_total' => round($polishTotal + $mazduri, 2),
        ];
    }

    private function resolveKarigarMazduri(Request $request): float
    {
        if ($request->filled('karigarJobId')) {
            $job = tbl_karigar_job::where('karigar_job_id', (int) $request->input('karigarJobId'))
                ->where('karigar_job_status', true)
                ->where('job_status', 'returned')
                ->whereNull('invoice_mst_id')
                ->first();

            if ($job) {
                return (float) $job->mazduri_cost;
            }
        }

        return round(max((float) ($request->input('mazduriCost') ?? 0), 0), 2);
    }
}