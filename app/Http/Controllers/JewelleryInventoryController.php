<?php

namespace App\Http\Controllers;

use App\Models\tbl_inventory_tag;
use App\Models\tbl_metal_balance;
use App\Models\tbl_sell_quality;
use App\Models\tbl_stock_ledger;
use App\Services\JewelleryCalculationService;
use App\Services\StockService;
use App\Http\Controllers\Concerns\AuditsActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JewelleryInventoryController extends Controller
{
    use AuditsActions;

    protected StockService $stockService;
    protected JewelleryCalculationService $calcService;

    public function __construct(StockService $stockService, JewelleryCalculationService $calcService)
    {
        $this->stockService = $stockService;
        $this->calcService = $calcService;
    }

    protected function logAction(Request $request, string $action, string $module, ?int $recordId = null, ?string $description = null, array $metadata = []): void
    {
        $this->audit($action, $module, $recordId, $description, $metadata, $request);
    }

    /**
     * Live Calculation Helper for UI.
     */
    public function calculate(Request $request)
    {
        $gross = (float) $request->input('gross_weight', 0);
        $stone = (float) $request->input('stone_weight', 0);
        $purity = $request->input('purity_karat', '22K');

        try {
            $weights = $this->calcService->decomposeWeights($gross, $stone, $purity);
            return response()->json([
                'status' => 1,
                'data' => $weights,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => -1,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Stock Valuation across Gross, Stone, Net, Fine, and Monetary Value.
     */
    public function valuation(Request $request)
    {
        $goldBalance = tbl_metal_balance::where('metal_type', 'gold')->first();
        $silverBalance = tbl_metal_balance::where('metal_type', 'silver')->first();

        $goldAvgRate = $this->stockService->getAverageRate('gold');
        $silverAvgRate = $this->stockService->getAverageRate('silver');

        // Fine gold and purity breakdown from available tags & ledger
        $fineGoldWeight = (float) tbl_stock_ledger::where('metal_type', 'gold')
            ->where('transaction_type', 'purchase')
            ->sum('fine_weight');

        $byQuality = $this->stockService->getAllQualityBalances();

        return response()->json([
            'status' => 1,
            'summary' => [
                'gold' => [
                    'total_weight_grams' => (float) ($goldBalance->total_weight_grams ?? 0),
                    'total_pieces' => (int) ($goldBalance->total_pieces ?? 0),
                    'average_rate' => $goldAvgRate,
                    'estimated_cost_value' => round(((float) ($goldBalance->total_weight_grams ?? 0)) * $goldAvgRate, 2),
                ],
                'silver' => [
                    'total_weight_grams' => (float) ($silverBalance->total_weight_grams ?? 0),
                    'total_pieces' => (int) ($silverBalance->total_pieces ?? 0),
                    'average_rate' => $silverAvgRate,
                    'estimated_cost_value' => round(((float) ($silverBalance->total_weight_grams ?? 0)) * $silverAvgRate, 2),
                ],
                'by_quality' => $byQuality,
                'total_inventory_cost_value' => round(
                    (((float) ($goldBalance->total_weight_grams ?? 0)) * $goldAvgRate) +
                    (((float) ($silverBalance->total_weight_grams ?? 0)) * $silverAvgRate),
                    2
                ),
            ]
        ]);
    }

    /**
     * List / Filter Inventory Tags & Barcodes.
     */
    public function tags(Request $request)
    {
        $paginate = (int) $request->input('paginate', 15);
        $query = tbl_inventory_tag::with('quality:sell_quality_id,quality_name')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('metal_type')) {
            $query->where('metal_type', $request->metal_type);
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('tag_number', 'like', $term)
                    ->orWhere('barcode', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        return response()->json($query->paginate($paginate));
    }

    /**
     * Search an active tag for POS sale.
     */
    public function findTag(string $tagNumber)
    {
        $tag = tbl_inventory_tag::with('quality')
            ->where(function ($q) use ($tagNumber) {
                $q->where('tag_number', $tagNumber)
                    ->orWhere('barcode', $tagNumber);
            })
            ->where('status', 'in_stock')
            ->first();

        if (!$tag) {
            return response()->json([
                'status' => -1,
                'message' => 'Tag not found or item is not currently in stock.',
            ], 404);
        }

        return response()->json([
            'status' => 1,
            'tag' => $tag,
        ]);
    }

    /**
     * Create / Register New Tag.
     */
    public function storeTag(Request $request)
    {
        $validated = validator($request->all(), [
            'tag_number' => 'required|string|max:50|unique:tbl_inventory_tags,tag_number',
            'barcode' => 'nullable|string|max:50',
            'sell_quality_id' => 'required|integer|exists:tbl_sell_qualities,sell_quality_id',
            'metal_type' => 'required|in:gold,silver',
            'gross_weight' => 'required|numeric|min:0.001',
            'stone_weight' => 'nullable|numeric|min:0',
            'purity_karat' => 'nullable',
            'pieces' => 'nullable|integer|min:1',
            'cost_rate' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'Validation Failed',
                'errors' => $validated->errors(),
            ], 422);
        }

        $gross = (float) $request->input('gross_weight');
        $stone = (float) $request->input('stone_weight', 0);
        $purity = $request->input('purity_karat', '22K');
        $weights = $this->calcService->decomposeWeights($gross, $stone, $purity);

        $tag = tbl_inventory_tag::create([
            'tag_number' => $request->input('tag_number'),
            'barcode' => $request->input('barcode') ?: $request->input('tag_number'),
            'sell_quality_id' => (int) $request->input('sell_quality_id'),
            'metal_type' => $request->input('metal_type'),
            'gross_weight' => $weights['gross_weight'],
            'stone_weight' => $weights['stone_weight'],
            'net_weight' => $weights['net_weight'],
            'purity_karat' => $weights['purity_karat'],
            'fine_weight' => $weights['fine_weight'],
            'pieces' => (int) $request->input('pieces', 1),
            'cost_rate' => $request->filled('cost_rate') ? (float) $request->input('cost_rate') : null,
            'cost_amount' => $request->filled('cost_rate') ? round($weights['net_weight'] * (float) $request->input('cost_rate'), 2) : null,
            'selling_price' => $request->filled('selling_price') ? (float) $request->input('selling_price') : null,
            'status' => 'in_stock',
            'notes' => $request->input('notes'),
            'created_by' => $request->user() ? $request->user()->id : null,
        ]);

        $this->logAction($request, 'create', 'inventory_tag', $tag->tag_id, 'Registered inventory tag ' . $tag->tag_number);

        return response()->json([
            'status' => 1,
            'message' => 'Tag created successfully.',
            'tag' => $tag,
        ]);
    }

    /**
     * Record Opening Stock.
     */
    public function recordOpeningStock(Request $request)
    {
        $validated = validator($request->all(), [
            'metal_type' => 'required|in:gold,silver',
            'sell_quality_id' => 'required|integer|exists:tbl_sell_qualities,sell_quality_id',
            'gross_weight' => 'required|numeric|min:0.001',
            'stone_weight' => 'nullable|numeric|min:0',
            'purity_karat' => 'nullable',
            'pieces' => 'nullable|integer|min:1',
            'rate_per_gram' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'Validation Failed',
                'errors' => $validated->errors(),
            ], 422);
        }

        $metalType = $request->input('metal_type');
        $sellQualityId = (int) $request->input('sell_quality_id');
        $gross = (float) $request->input('gross_weight');
        $stone = (float) $request->input('stone_weight', 0);
        $purity = $request->input('purity_karat', '22K');
        $pieces = (int) $request->input('pieces', 1);
        $rate = (float) $request->input('rate_per_gram', 0);
        $userId = $request->user() ? $request->user()->id : null;

        $weights = $this->calcService->decomposeWeights($gross, $stone, $purity);
        $amount = round($weights['net_weight'] * $rate, 2);

        $ledger = DB::transaction(function () use (
            $metalType,
            $sellQualityId,
            $weights,
            $pieces,
            $rate,
            $amount,
            $request,
            $userId
        ) {
            $balance = $this->stockService->getBalance($metalType);
            $balance->total_weight_grams = round((float) $balance->total_weight_grams + $weights['net_weight'], 3);
            $balance->total_pieces = (int) $balance->total_pieces + $pieces;
            $balance->save();

            return tbl_stock_ledger::create([
                'metal_type' => $metalType,
                'sell_quality_id' => $sellQualityId,
                'transaction_type' => 'opening',
                'direction' => 'in',
                'gross_weight' => $weights['gross_weight'],
                'stone_weight' => $weights['stone_weight'],
                'net_weight' => $weights['net_weight'],
                'purity_karat' => $weights['purity_karat'],
                'fine_weight' => $weights['fine_weight'],
                'weight_grams' => $weights['net_weight'],
                'quantity_pieces' => $pieces,
                'rate_per_gram' => $rate,
                'amount' => $amount,
                'balance_weight_after' => $balance->total_weight_grams,
                'reference_type' => 'opening_stock',
                'notes' => $request->input('notes', 'Opening stock balance entry'),
                'created_by' => $userId,
            ]);
        });

        $this->logAction($request, 'create', 'opening_stock', $ledger->stock_ledger_id, 'Added opening stock: ' . $weights['net_weight'] . 'g');

        return response()->json([
            'status' => 1,
            'message' => 'Opening stock recorded successfully.',
            'ledger' => $ledger,
        ]);
    }

    /**
     * Record Stock Adjustment (Physical Count Correction).
     */
    public function recordAdjustment(Request $request)
    {
        $validated = validator($request->all(), [
            'metal_type' => 'required|in:gold,silver',
            'sell_quality_id' => 'required|integer|exists:tbl_sell_qualities,sell_quality_id',
            'delta_weight_grams' => 'required|numeric',
            'delta_pieces' => 'nullable|integer',
            'reason' => 'required|string|max:255',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'Validation Failed',
                'errors' => $validated->errors(),
            ], 422);
        }

        $metalType = $request->input('metal_type');
        $sellQualityId = (int) $request->input('sell_quality_id');
        $deltaWeight = round((float) $request->input('delta_weight_grams'), 3);
        $deltaPieces = (int) $request->input('delta_pieces', 0);
        $reason = $request->input('reason');
        $userId = $request->user() ? $request->user()->id : null;

        if ($deltaWeight == 0 && $deltaPieces == 0) {
            return response()->json(['status' => -1, 'message' => 'Adjustment delta cannot be zero.'], 422);
        }

        $ledger = DB::transaction(function () use ($metalType, $sellQualityId, $deltaWeight, $deltaPieces, $reason, $userId) {
            $balance = $this->stockService->getBalance($metalType);
            $newWeight = round((float) $balance->total_weight_grams + $deltaWeight, 3);
            $newPieces = max(0, (int) $balance->total_pieces + $deltaPieces);

            if ($newWeight < 0) {
                throw new RuntimeException('Adjustment would result in negative total metal stock.');
            }

            $balance->total_weight_grams = $newWeight;
            $balance->total_pieces = $newPieces;
            $balance->save();

            return tbl_stock_ledger::create([
                'metal_type' => $metalType,
                'sell_quality_id' => $sellQualityId,
                'transaction_type' => 'adjustment',
                'direction' => $deltaWeight >= 0 ? 'in' : 'out',
                'gross_weight' => abs($deltaWeight),
                'stone_weight' => 0,
                'net_weight' => abs($deltaWeight),
                'purity_karat' => 22.0,
                'fine_weight' => round(abs($deltaWeight) * (22.0 / 24.0), 3),
                'weight_grams' => $deltaWeight,
                'quantity_pieces' => $deltaPieces,
                'balance_weight_after' => $newWeight,
                'reference_type' => 'stock_adjustment',
                'notes' => 'Stock adjustment: ' . $reason,
                'created_by' => $userId,
            ]);
        });

        $this->logAction($request, 'adjust', 'stock_adjustment', $ledger->stock_ledger_id, 'Stock adjustment: ' . $reason);

        return response()->json([
            'status' => 1,
            'message' => 'Stock adjustment posted successfully.',
            'ledger' => $ledger,
        ]);
    }

    /**
     * Old Gold Exchange Module.
     */
    public function recordExchange(Request $request)
    {
        $validated = validator($request->all(), [
            'customer_id' => 'required|integer|exists:tbl_customers,customer_id',
            'old_gold_metal' => 'required|in:gold,silver',
            'old_gold_gross' => 'required|numeric|min:0.001',
            'old_gold_stone' => 'nullable|numeric|min:0',
            'old_gold_purity' => 'nullable',
            'old_gold_rate' => 'required|numeric|min:0.01',
            'new_sell_quality_id' => 'required|integer|exists:tbl_sell_qualities,sell_quality_id',
            'new_weight_grams' => 'required|numeric|min:0.001',
            'new_pieces' => 'nullable|integer|min:1',
            'new_sold_amount' => 'required|numeric|min:0.01',
            'making_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'Validation Failed',
                'errors' => $validated->errors(),
            ], 422);
        }

        $userId = $request->user() ? $request->user()->id : null;
        $customerId = (int) $request->input('customer_id');

        // 1. Decompose Old Gold
        $oldGross = (float) $request->input('old_gold_gross');
        $oldStone = (float) $request->input('old_gold_stone', 0);
        $oldPurity = $request->input('old_gold_purity', '21K');
        $oldWeights = $this->calcService->decomposeWeights($oldGross, $oldStone, $oldPurity);
        $oldRate = (float) $request->input('old_gold_rate');
        $oldValue = round($oldWeights['net_weight'] * $oldRate, 2);

        // 2. New Jewellery
        $newQualityId = (int) $request->input('new_sell_quality_id');
        $newWeight = (float) $request->input('new_weight_grams');
        $newPieces = (int) $request->input('new_pieces', 1);
        $newSoldAmount = (float) $request->input('new_sold_amount');
        $makingCharges = (float) $request->input('making_charges', 0);

        // 3. Exchange Accounting
        $exchangeResult = $this->calcService->calculateExchange(
            $newSoldAmount,
            $oldWeights['net_weight'],
            $oldRate,
            $makingCharges
        );

        $result = DB::transaction(function () use (
            $customerId,
            $request,
            $oldWeights,
            $oldRate,
            $oldValue,
            $newQualityId,
            $newWeight,
            $newPieces,
            $newSoldAmount,
            $makingCharges,
            $exchangeResult,
            $userId
        ) {
            // A. Stock IN for Old Gold
            $oldMetal = $request->input('old_gold_metal');
            $balanceOld = $this->stockService->getBalance($oldMetal);
            $balanceOld->total_weight_grams = round((float) $balanceOld->total_weight_grams + $oldWeights['net_weight'], 3);
            $balanceOld->save();

            $oldStockEntry = tbl_stock_ledger::create([
                'metal_type' => $oldMetal,
                'transaction_type' => 'purchase',
                'direction' => 'in',
                'gross_weight' => $oldWeights['gross_weight'],
                'stone_weight' => $oldWeights['stone_weight'],
                'net_weight' => $oldWeights['net_weight'],
                'purity_karat' => $oldWeights['purity_karat'],
                'fine_weight' => $oldWeights['fine_weight'],
                'weight_grams' => $oldWeights['net_weight'],
                'quantity_pieces' => 1,
                'rate_per_gram' => $oldRate,
                'amount' => $oldValue,
                'balance_weight_after' => $balanceOld->total_weight_grams,
                'reference_type' => 'gold_exchange_in',
                'notes' => 'Old gold received from customer #' . $customerId,
                'created_by' => $userId,
            ]);

            // B. Stock OUT for New Jewellery Sold
            $newMetal = $oldMetal; // Default
            $saleData = $this->stockService->recordSale(
                $newMetal,
                $newQualityId,
                $newWeight,
                $newPieces,
                $newSoldAmount,
                'gold_exchange_out',
                $oldStockEntry->stock_ledger_id,
                $userId
            );

            return [
                'old_gold_ledger_id' => $oldStockEntry->stock_ledger_id,
                'exchange_calculation' => $exchangeResult,
                'sale_cost_profit' => $saleData,
            ];
        });

        $this->logAction($request, 'exchange', 'gold_exchange', $result['old_gold_ledger_id'], 'Gold exchange processed. Net payable: ' . $exchangeResult['net_payable_amount']);

        return response()->json([
            'status' => 1,
            'message' => 'Gold exchange processed successfully.',
            'data' => $result,
        ]);
    }
}
