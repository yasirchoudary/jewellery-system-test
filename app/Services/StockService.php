<?php

namespace App\Services;

use App\Models\tbl_karigar_job;
use App\Models\tbl_metal_balance;
use App\Models\tbl_sell_quality;
use App\Models\tbl_stock_ledger;
use App\Services\JewelleryCalculationService;
use App\Models\tbl_inventory_tag;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    protected JewelleryCalculationService $calcService;

    public function __construct(?JewelleryCalculationService $calcService = null)
    {
        $this->calcService = $calcService ?: app(JewelleryCalculationService::class);
    }

    public function getBalance(string $metalType): tbl_metal_balance
    {
        return tbl_metal_balance::firstOrCreate(
            ['metal_type' => $metalType],
            ['total_weight_grams' => 0, 'total_pieces' => 0]
        );
    }

    public function getQualityBalance(int $sellQualityId): array
    {
        $quality = tbl_sell_quality::with('category')->find($sellQualityId);
        $qualityName = $quality ? $quality->quality_name : 'Unknown item';
        $metalType = $quality && $quality->category
            ? ($quality->category->metal_type ?? null)
            : null;
        $availablePieceWeights = $this->getAvailablePieceWeights($sellQualityId);

        return [
            'sell_quality_id' => $sellQualityId,
            'quality_name' => $qualityName,
            'metal_type' => $metalType,
            'weight_grams' => round(array_sum($availablePieceWeights), 3),
            'pieces' => count($availablePieceWeights),
            'available_piece_weights' => $availablePieceWeights,
            'available_piece_weights_label' => $this->formatAvailablePieceWeights($availablePieceWeights),
            'grams_per_piece' => $this->getUniformGramsPerPiece($availablePieceWeights),
        ];
    }

    public function getAvailablePieceWeights(int $sellQualityId): array
    {
        $events = tbl_stock_ledger::where('sell_quality_id', $sellQualityId)
            ->where(function ($query) {
                $query->whereIn('transaction_type', ['purchase', 'sale'])
                    ->orWhere(function ($nested) {
                        $nested->where('transaction_type', 'adjustment')
                            ->where('reference_type', 'karigar_issue');
                    });
            })
            ->orderBy('created_at')
            ->orderBy('stock_ledger_id')
            ->get(['transaction_type', 'weight_grams', 'quantity_pieces']);

        $pool = [];

        foreach ($events as $event) {
            $pieces = (int) $event->quantity_pieces;
            $weight = (float) $event->weight_grams;

            if ($pieces <= 0 || $weight <= 0) {
                continue;
            }

            $weightPerPiece = round($weight / $pieces, 3);

            if ($event->transaction_type === 'purchase') {
                for ($i = 0; $i < $pieces; $i++) {
                    $pool[] = $weightPerPiece;
                }
                continue;
            }

            for ($i = 0; $i < $pieces; $i++) {
                $removed = false;

                foreach ($pool as $idx => $pieceWeight) {
                    if (abs($pieceWeight - $weightPerPiece) <= 0.0005) {
                        unset($pool[$idx]);
                        $removed = true;
                        break;
                    }
                }

                if (!$removed && !empty($pool)) {
                    array_shift($pool);
                }
            }
        }

        return array_values($pool);
    }

    public function getGramsPerPiece(int $sellQualityId): ?float
    {
        return $this->getUniformGramsPerPiece($this->getAvailablePieceWeights($sellQualityId));
    }

    public function assertSaleWeightMatchesStockRatio(
        int $sellQualityId,
        float $totalWeightGrams,
        int $pieces,
        string $actionVerb = 'sell'
    ): void {
        if ($pieces <= 0 || $totalWeightGrams <= 0) {
            return;
        }

        $balance = $this->getQualityBalance($sellQualityId);
        $name = $balance['quality_name'];
        $weightPerPiece = round($totalWeightGrams / $pieces, 3);
        $available = $balance['available_piece_weights'];

        if (empty($available)) {
            throw new RuntimeException(
                'No stock for "' . $name . '". Purchase this item type first.'
            );
        }

        $matchingCount = 0;
        foreach ($available as $pieceWeight) {
            if (abs($pieceWeight - $weightPerPiece) <= 0.0005) {
                $matchingCount++;
            }
        }

        if ($matchingCount < $pieces) {
            throw new RuntimeException(
                'Cannot ' . $actionVerb . ' ' . $pieces . ' pc at ' . number_format($weightPerPiece, 3) . 'g each for "' . $name . '". '
                . 'Available piece weights: ' . $balance['available_piece_weights_label'] . '.'
            );
        }
    }

    protected function getUniformGramsPerPiece(array $availablePieceWeights): ?float
    {
        if (empty($availablePieceWeights)) {
            return null;
        }

        $first = round($availablePieceWeights[0], 3);

        foreach ($availablePieceWeights as $pieceWeight) {
            if (abs($pieceWeight - $first) > 0.0005) {
                return null;
            }
        }

        return $first;
    }

    protected function formatAvailablePieceWeights(array $weights): string
    {
        if (empty($weights)) {
            return 'none';
        }

        $counts = [];

        foreach ($weights as $weight) {
            $key = number_format($weight, 3, '.', '');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $parts = [];

        foreach ($counts as $weight => $count) {
            $parts[] = $count > 1 ? $count . ' x ' . $weight . 'g' : $weight . 'g';
        }

        return implode(', ', $parts);
    }

    public function getAllQualityBalances()
    {
        return tbl_sell_quality::with('category')
            ->where('sell_quality_status', true)
            ->orderBy('quality_name')
            ->get(['sell_quality_id', 'quality_name', 'sell_quality_category_id'])
            ->map(function ($quality) {
                $balance = $this->getQualityBalance((int) $quality->sell_quality_id);

                return [
                    'sell_quality_id' => $quality->sell_quality_id,
                    'quality_name' => $quality->quality_name,
                    'metal_type' => $quality->category->metal_type ?? $balance['metal_type'] ?? null,
                    'weight_grams' => $balance['weight_grams'],
                    'pieces' => $balance['pieces'],
                ];
            })
            ->values();
    }

    public function getAverageRate(string $metalType): float
    {
        $purchases = tbl_stock_ledger::where('metal_type', $metalType)
            ->where('transaction_type', 'purchase')
            ->where('weight_grams', '>', 0)
            ->get(['weight_grams', 'amount']);

        if ($purchases->isEmpty()) {
            return 0;
        }

        $totalWeight = $purchases->sum('weight_grams');
        $totalAmount = $purchases->sum('amount');

        return $totalWeight > 0 ? round($totalAmount / $totalWeight, 2) : 0;
    }

    public function addPurchase(
        string $metalType,
        ?int $sellQualityId,
        float $weightGrams,
        int $pieces,
        float $ratePerGram,
        float $amount,
        string $referenceType,
        int $referenceId,
        ?int $userId = null,
        float $grossWeight = 0.0,
        float $stoneWeight = 0.0,
        float $purityKarat = 22.0,
        ?string $tagNumber = null
    ): tbl_stock_ledger {
        if ($weightGrams <= 0) {
            throw new RuntimeException('Purchase weight must be greater than zero.');
        }

        $gross = $grossWeight > 0 ? $grossWeight : $weightGrams;
        $weights = $this->calcService->decomposeWeights($gross, $stoneWeight, $purityKarat);

        return DB::transaction(function () use (
            $metalType,
            $sellQualityId,
            $weightGrams,
            $pieces,
            $ratePerGram,
            $amount,
            $referenceType,
            $referenceId,
            $userId,
            $weights,
            $tagNumber
        ) {
            $balance = $this->getBalance($metalType);
            $balance->total_weight_grams = round((float) $balance->total_weight_grams + $weightGrams, 3);
            $balance->total_pieces = (int) $balance->total_pieces + $pieces;
            $balance->save();

            $ledger = tbl_stock_ledger::create([
                'metal_type' => $metalType,
                'sell_quality_id' => $sellQualityId,
                'transaction_type' => 'purchase',
                'direction' => 'in',
                'gross_weight' => $weights['gross_weight'],
                'stone_weight' => $weights['stone_weight'],
                'net_weight' => $weights['net_weight'],
                'purity_karat' => $weights['purity_karat'],
                'fine_weight' => $weights['fine_weight'],
                'tag_number' => $tagNumber,
                'weight_grams' => $weightGrams,
                'quantity_pieces' => $pieces,
                'rate_per_gram' => $ratePerGram,
                'amount' => $amount,
                'balance_weight_after' => $balance->total_weight_grams,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $userId,
            ]);

            if ($tagNumber && $sellQualityId) {
                tbl_inventory_tag::updateOrCreate(
                    ['tag_number' => $tagNumber],
                    [
                        'barcode' => $tagNumber,
                        'sell_quality_id' => $sellQualityId,
                        'metal_type' => $metalType,
                        'gross_weight' => $weights['gross_weight'],
                        'stone_weight' => $weights['stone_weight'],
                        'net_weight' => $weights['net_weight'],
                        'purity_karat' => $weights['purity_karat'],
                        'fine_weight' => $weights['fine_weight'],
                        'pieces' => $pieces,
                        'cost_rate' => $ratePerGram,
                        'cost_amount' => $amount,
                        'status' => 'in_stock',
                        'source_type' => $referenceType,
                        'source_id' => $referenceId,
                        'created_by' => $userId,
                    ]
                );
            }

            return $ledger;
        });
    }

    public function reversePurchase(
        string $referenceType,
        int $referenceId,
        ?int $userId = null
    ): void {
        $entry = tbl_stock_ledger::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('transaction_type', 'purchase')
            ->first();

        if (!$entry) {
            return;
        }

        DB::transaction(function () use ($entry, $referenceType, $referenceId, $userId) {
            $weightGrams = (float) $entry->weight_grams;
            $pieces = (int) $entry->quantity_pieces;
            $balance = $this->getBalance($entry->metal_type);

            if ((float) $balance->total_weight_grams < $weightGrams - 0.0005) {
                throw new RuntimeException(
                    'Cannot delete purchase: ' . $entry->metal_type . ' stock has already been used.'
                );
            }

            if ($entry->sell_quality_id) {
                $qualityBalance = $this->getQualityBalance((int) $entry->sell_quality_id);

                if ($qualityBalance['weight_grams'] < $weightGrams - 0.0005) {
                    throw new RuntimeException(
                        'Cannot delete purchase: "' . $qualityBalance['quality_name'] . '" stock has already been sold.'
                    );
                }
            }

            $balance->total_weight_grams = round((float) $balance->total_weight_grams - $weightGrams, 3);
            $balance->total_pieces = max(0, (int) $balance->total_pieces - $pieces);
            $balance->save();

            tbl_stock_ledger::create([
                'metal_type' => $entry->metal_type,
                'sell_quality_id' => $entry->sell_quality_id,
                'transaction_type' => 'adjustment',
                'weight_grams' => -$weightGrams,
                'quantity_pieces' => 0,
                'rate_per_gram' => $entry->rate_per_gram,
                'amount' => $entry->amount ? -((float) $entry->amount) : null,
                'balance_weight_after' => $balance->total_weight_grams,
                'reference_type' => $referenceType . '_reversal',
                'reference_id' => $referenceId,
                'created_by' => $userId,
                'notes' => 'Purchase deleted (' . $pieces . ' pcs reversed)',
            ]);

            $entry->delete();
        });
    }

    public function recordSale(
        string $metalType,
        ?int $sellQualityId,
        float $weightGrams,
        int $pieces,
        float $soldAmount,
        string $referenceType,
        int $referenceId,
        ?int $userId = null,
        float $grossWeight = 0.0,
        float $stoneWeight = 0.0,
        float $purityKarat = 22.0,
        ?string $tagNumber = null
    ): array {
        if ($weightGrams <= 0) {
            throw new RuntimeException('Sale weight must be greater than zero.');
        }

        $gross = $grossWeight > 0 ? $grossWeight : $weightGrams;
        $weights = $this->calcService->decomposeWeights($gross, $stoneWeight, $purityKarat);

        return DB::transaction(function () use (
            $metalType,
            $sellQualityId,
            $weightGrams,
            $pieces,
            $soldAmount,
            $referenceType,
            $referenceId,
            $userId,
            $weights,
            $tagNumber
        ) {
            $this->assertQualityStockAvailable($sellQualityId, $weightGrams, $pieces);

            $balance = $this->getBalance($metalType);

            if ((float) $balance->total_weight_grams < $weightGrams) {
                throw new RuntimeException(
                    'Insufficient total ' . $metalType . ' stock. Available: '
                    . number_format((float) $balance->total_weight_grams, 3) . 'g, requested: '
                    . number_format($weightGrams, 3) . 'g.'
                );
            }

            $avgRate = $this->getAverageRate($metalType);
            $costAmount = round($weightGrams * $avgRate, 2);
            $profitAmount = round($soldAmount - $costAmount, 2);

            $balance->total_weight_grams = round((float) $balance->total_weight_grams - $weightGrams, 3);
            $balance->total_pieces = max(0, (int) $balance->total_pieces - $pieces);
            $balance->save();

            tbl_stock_ledger::create([
                'metal_type' => $metalType,
                'sell_quality_id' => $sellQualityId,
                'transaction_type' => 'sale',
                'direction' => 'out',
                'gross_weight' => $weights['gross_weight'],
                'stone_weight' => $weights['stone_weight'],
                'net_weight' => $weights['net_weight'],
                'purity_karat' => $weights['purity_karat'],
                'fine_weight' => $weights['fine_weight'],
                'tag_number' => $tagNumber,
                'weight_grams' => $weightGrams,
                'quantity_pieces' => $pieces,
                'rate_per_gram' => $weightGrams > 0 ? round($soldAmount / $weightGrams, 2) : 0,
                'amount' => $soldAmount,
                'balance_weight_after' => $balance->total_weight_grams,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $userId,
                'notes' => 'Cost: ' . $costAmount . ', Profit: ' . $profitAmount,
            ]);

            if ($tagNumber) {
                tbl_inventory_tag::where('tag_number', $tagNumber)->update([
                    'status' => 'sold',
                    'sold_invoice_id' => $referenceId,
                ]);
            }

            return [
                'cost_amount' => $costAmount,
                'profit_amount' => $profitAmount,
                'average_rate' => $avgRate,
            ];
        });
    }

    public function reverseSale(
        string $referenceType,
        int $referenceId,
        ?int $userId = null
    ): void {
        $entries = tbl_stock_ledger::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('transaction_type', 'sale')
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($entries, $referenceType, $referenceId, $userId) {
            foreach ($entries as $entry) {
                $weightGrams = (float) $entry->weight_grams;
                $pieces = (int) $entry->quantity_pieces;
                $balance = $this->getBalance($entry->metal_type);

                $balance->total_weight_grams = round((float) $balance->total_weight_grams + $weightGrams, 3);
                $balance->total_pieces = (int) $balance->total_pieces + $pieces;
                $balance->save();

                tbl_stock_ledger::create([
                    'metal_type' => $entry->metal_type,
                    'sell_quality_id' => $entry->sell_quality_id,
                    'transaction_type' => 'adjustment',
                    'weight_grams' => $weightGrams,
                    'quantity_pieces' => $pieces,
                    'rate_per_gram' => $entry->rate_per_gram,
                    'amount' => $entry->amount ? -((float) $entry->amount) : null,
                    'balance_weight_after' => $balance->total_weight_grams,
                    'reference_type' => $referenceType . '_reversal',
                    'reference_id' => $referenceId,
                    'created_by' => $userId,
                    'notes' => 'Sale deleted — stock restored (' . $pieces . ' pcs, ' . $weightGrams . 'g)',
                ]);

                $entry->delete();
            }
        });
    }

    public function resolveMetalTypeFromCategory(int $categoryId): string
    {
        $category = DB::table('tbl_sell_quality_categories')
            ->where('sell_quality_category_id', $categoryId)
            ->first();

        return $category && $category->metal_type === 'silver' ? 'silver' : 'gold';
    }

    public function reverseKarigarIssue(int $referenceId, ?int $userId = null): bool
    {
        $entries = tbl_stock_ledger::where('reference_type', 'karigar_issue')
            ->where('reference_id', $referenceId)
            ->where('transaction_type', 'adjustment')
            ->get();

        if ($entries->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($entries, $referenceId, $userId) {
            foreach ($entries as $entry) {
                $weightGrams = (float) $entry->weight_grams;
                $pieces = (int) $entry->quantity_pieces;
                $balance = $this->getBalance($entry->metal_type);

                $balance->total_weight_grams = round((float) $balance->total_weight_grams + $weightGrams, 3);
                $balance->total_pieces = (int) $balance->total_pieces + $pieces;
                $balance->save();

                tbl_stock_ledger::create([
                    'metal_type' => $entry->metal_type,
                    'sell_quality_id' => $entry->sell_quality_id,
                    'transaction_type' => 'adjustment',
                    'weight_grams' => $weightGrams,
                    'quantity_pieces' => $pieces,
                    'rate_per_gram' => $entry->rate_per_gram,
                    'amount' => $entry->amount ? -((float) $entry->amount) : null,
                    'balance_weight_after' => $balance->total_weight_grams,
                    'reference_type' => 'karigar_issue_reversal',
                    'reference_id' => $referenceId,
                    'created_by' => $userId,
                    'notes' => 'Karigar job deleted — stock restored (' . $pieces . ' pcs, ' . $weightGrams . 'g)',
                ]);

                $entry->delete();
            }
        });

        return true;
    }

    public function restoreLegacyKarigarIssue(tbl_karigar_job $job, ?int $userId = null): void
    {
        $weightGrams = (float) $job->issued_weight_grams;
        if ($weightGrams <= 0) {
            return;
        }

        DB::transaction(function () use ($job, $weightGrams, $userId) {
            $balance = $this->getBalance($job->metal_type);
            $balance->total_weight_grams = round((float) $balance->total_weight_grams + $weightGrams, 3);
            $balance->save();

            tbl_stock_ledger::create([
                'metal_type' => $job->metal_type,
                'sell_quality_id' => $job->sell_quality_id,
                'transaction_type' => 'adjustment',
                'weight_grams' => $weightGrams,
                'quantity_pieces' => 0,
                'rate_per_gram' => $this->getAverageRate($job->metal_type),
                'amount' => null,
                'balance_weight_after' => $balance->total_weight_grams,
                'reference_type' => 'karigar_issue_reversal',
                'reference_id' => (int) $job->karigar_job_id,
                'created_by' => $userId,
                'notes' => 'Legacy karigar job deleted — metal stock restored (' . $weightGrams . 'g)',
            ]);
        });
    }

    public function issueQualityToKarigar(
        string $metalType,
        int $sellQualityId,
        float $weightGrams,
        int $pieces,
        int $referenceId,
        ?int $userId = null,
        ?string $notes = null
    ): tbl_stock_ledger {
        if ($weightGrams <= 0 || $pieces <= 0) {
            throw new RuntimeException('Issue weight and pieces must be greater than zero.');
        }

        return DB::transaction(function () use (
            $metalType,
            $sellQualityId,
            $weightGrams,
            $pieces,
            $referenceId,
            $userId,
            $notes
        ) {
            $this->assertSaleWeightMatchesStockRatio($sellQualityId, $weightGrams, $pieces, 'issue to karigar');

            $balance = $this->getBalance($metalType);
            $available = (float) $balance->total_weight_grams;

            if ($available < $weightGrams - 0.0005) {
                throw new RuntimeException(
                    'Insufficient ' . $metalType . ' stock for karigar issue. Available: '
                    . number_format($available, 3) . 'g.'
                );
            }

            $avgRate = $this->getAverageRate($metalType);
            $amount = round($weightGrams * $avgRate, 2);

            $balance->total_weight_grams = round($available - $weightGrams, 3);
            $balance->total_pieces = max(0, (int) $balance->total_pieces - $pieces);
            $balance->save();

            return tbl_stock_ledger::create([
                'metal_type' => $metalType,
                'sell_quality_id' => $sellQualityId,
                'transaction_type' => 'adjustment',
                'direction' => 'out',
                'gross_weight' => $weightGrams,
                'stone_weight' => 0,
                'net_weight' => $weightGrams,
                'purity_karat' => 22.0,
                'fine_weight' => round($weightGrams * (22.0 / 24.0), 3),
                'weight_grams' => $weightGrams,
                'quantity_pieces' => $pieces,
                'rate_per_gram' => $avgRate,
                'amount' => $amount,
                'balance_weight_after' => $balance->total_weight_grams,
                'reference_type' => 'karigar_issue',
                'reference_id' => $referenceId,
                'created_by' => $userId,
                'notes' => $notes ?: 'Metal issued to karigar',
            ]);
        });
    }

    /** @deprecated Use issueQualityToKarigar */
    public function issueMetalToKarigar(
        string $metalType,
        float $weightGrams,
        int $referenceId,
        ?int $userId = null,
        ?string $notes = null
    ): tbl_stock_ledger {
        throw new RuntimeException('Karigar outward requires an item type from stock.');
    }

    public function returnMetalFromKarigar(
        string $metalType,
        int $sellQualityId,
        float $weightGrams,
        int $pieces,
        int $referenceId,
        ?int $userId = null,
        ?string $notes = null,
        float $grossWeight = 0.0,
        float $stoneWeight = 0.0,
        float $purityKarat = 22.0,
        ?string $tagNumber = null
    ): tbl_stock_ledger {
        $avgRate = $this->getAverageRate($metalType);
        $amount = round($weightGrams * $avgRate, 2);

        return $this->addPurchase(
            $metalType,
            $sellQualityId,
            $weightGrams,
            $pieces,
            $avgRate,
            $amount,
            'karigar_return',
            $referenceId,
            $userId,
            $grossWeight,
            $stoneWeight,
            $purityKarat,
            $tagNumber
        );
    }

    protected function assertQualityStockAvailable(?int $sellQualityId, float $weightGrams, int $pieces): void
    {
        if (!$sellQualityId) {
            throw new RuntimeException('Item type (quality) is required before stock can be deducted.');
        }

        $balance = $this->getQualityBalance($sellQualityId);
        $name = $balance['quality_name'];

        if ($balance['weight_grams'] <= 0) {
            throw new RuntimeException(
                'No stock for "' . $name . '". Purchase this item type first — you cannot sell a type you do not have in stock.'
            );
        }

        if ($weightGrams > $balance['weight_grams'] + 0.0005) {
            throw new RuntimeException(
                'Insufficient "' . $name . '" stock. Available: '
                . number_format($balance['weight_grams'], 3) . 'g, requested: '
                . number_format($weightGrams, 3) . 'g.'
            );
        }

        if ($pieces > 0 && $pieces > $balance['pieces']) {
            throw new RuntimeException(
                'Insufficient "' . $name . '" pieces. Available: '
                . $balance['pieces'] . ', requested: ' . $pieces . '.'
            );
        }

        $this->assertSaleWeightMatchesStockRatio($sellQualityId, $weightGrams, $pieces);
    }
}
