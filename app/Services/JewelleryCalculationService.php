<?php

namespace App\Services;

use RuntimeException;
use InvalidArgumentException;

/**
 * Central Authoritative Calculation Engine for Jewellery Operations.
 * Enforces decimal-safe precision (DECIMAL 14,3 / round to 3 decimals).
 */
class JewelleryCalculationService
{
    public const SUPPORTED_PURITIES = [
        '24K' => 24.0,
        '22K' => 22.0,
        '21K' => 21.0,
        '20K' => 20.0,
        '18K' => 18.0,
        '14K' => 14.0,
        '999' => 23.976,
        '916' => 21.984,
        '750' => 18.0,
    ];

    /**
     * Calculate Net Metal Weight = Gross Weight - Stone Weight.
     *
     * @param float $grossWeight
     * @param float $stoneWeight
     * @return float
     * @throws InvalidArgumentException
     */
    public function calculateNetWeight(float $grossWeight, float $stoneWeight = 0.0): float
    {
        $gross = round($grossWeight, 3);
        $stone = round($stoneWeight, 3);

        if ($gross < 0) {
            throw new InvalidArgumentException('Gross weight cannot be negative.');
        }

        if ($stone < 0) {
            throw new InvalidArgumentException('Stone weight cannot be negative.');
        }

        if ($stone > $gross) {
            throw new InvalidArgumentException(
                "Stone weight ({$stone}g) cannot exceed gross weight ({$gross}g)."
            );
        }

        return round($gross - $stone, 3);
    }

    /**
     * Resolve numeric Karat value from string or float representation.
     *
     * @param float|string|null $purity
     * @return float
     */
    public function normalizeKarat($purity): float
    {
        if ($purity === null || $purity === '') {
            return 22.0; // Default jewellery standard
        }

        if (is_numeric($purity)) {
            $val = (float) $purity;
            if ($val > 0 && $val <= 24.0) {
                return round($val, 2);
            }
            if ($val >= 100 && $val <= 1000) {
                // Millesimal fineness e.g. 916 -> 21.984
                return round(($val / 1000) * 24.0, 2);
            }
        }

        $clean = strtoupper(trim((string) $purity));
        if (isset(self::SUPPORTED_PURITIES[$clean])) {
            return self::SUPPORTED_PURITIES[$clean];
        }

        // Try extracting numeric portion e.g. "22KT" -> 22.0
        if (preg_match('/^(\d+(?:\.\d+)?)/', $clean, $matches)) {
            $num = (float) $matches[1];
            if ($num > 0 && $num <= 24.0) {
                return round($num, 2);
            }
        }

        return 22.0;
    }

    /**
     * Calculate Fine Gold Weight = Net Weight * (Karat / 24).
     *
     * @param float $netWeight
     * @param float|string|null $purity
     * @return float
     */
    public function calculateFineWeight(float $netWeight, $purity = 22.0): float
    {
        $net = round($netWeight, 3);
        if ($net <= 0) {
            return 0.0;
        }

        $karat = $this->normalizeKarat($purity);
        return round(($net * $karat) / 24.0, 3);
    }

    /**
     * Decompose weight and purity in a single unified array.
     *
     * @param float $grossWeight
     * @param float $stoneWeight
     * @param float|string|null $purity
     * @return array
     */
    public function decomposeWeights(float $grossWeight, float $stoneWeight = 0.0, $purity = 22.0): array
    {
        $gross = round($grossWeight, 3);
        $stone = round($stoneWeight, 3);
        $net = $this->calculateNetWeight($gross, $stone);
        $karat = $this->normalizeKarat($purity);
        $fine = $this->calculateFineWeight($net, $karat);

        return [
            'gross_weight' => $gross,
            'stone_weight' => $stone,
            'net_weight' => $net,
            'purity_karat' => $karat,
            'fine_weight' => $fine,
        ];
    }

    /**
     * Reconcile Karigar Job: Issued = Returned + Remaining + Wastage.
     *
     * @param float $issuedWeight
     * @param float $returnedWeight
     * @param float $remainingWeight
     * @param float $wastageWeight
     * @return array
     */
    public function reconcileKarigarJob(
        float $issuedWeight,
        float $returnedWeight,
        float $remainingWeight = 0.0,
        float $wastageWeight = 0.0
    ): array {
        $issued = round($issuedWeight, 3);
        $returned = round($returnedWeight, 3);
        $remaining = round($remainingWeight, 3);
        $wastage = round($wastageWeight, 3);

        $accounted = round($returned + $remaining + $wastage, 3);
        $variance = round($issued - $accounted, 3);
        $outstanding = round($issued - ($returned + $wastage), 3);

        return [
            'issued_weight' => $issued,
            'returned_weight' => $returned,
            'remaining_weight' => $remaining,
            'wastage_weight' => $wastage,
            'accounted_weight' => $accounted,
            'outstanding_weight' => max(0.0, $outstanding),
            'variance' => $variance,
            'is_reconciled' => abs($variance) <= 0.005,
        ];
    }

    /**
     * Compute Old Gold Exchange Payable/Refundable.
     *
     * @param float $newJewelleryAmount
     * @param float $oldGoldWeightGrams
     * @param float $oldGoldRatePerGram
     * @param float $makingCharges
     * @param float $otherCharges
     * @return array
     */
    public function calculateExchange(
        float $newJewelleryAmount,
        float $oldGoldWeightGrams,
        float $oldGoldRatePerGram,
        float $makingCharges = 0.0,
        float $otherCharges = 0.0
    ): array {
        $oldGoldValue = round($oldGoldWeightGrams * $oldGoldRatePerGram, 2);
        $totalPayable = round(($newJewelleryAmount + $makingCharges + $otherCharges) - $oldGoldValue, 2);

        return [
            'new_jewellery_amount' => round($newJewelleryAmount, 2),
            'old_gold_weight' => round($oldGoldWeightGrams, 3),
            'old_gold_rate' => round($oldGoldRatePerGram, 2),
            'old_gold_value' => $oldGoldValue,
            'making_charges' => round($makingCharges, 2),
            'other_charges' => round($otherCharges, 2),
            'net_payable_amount' => $totalPayable,
            'is_customer_refund' => $totalPayable < 0,
        ];
    }
}
