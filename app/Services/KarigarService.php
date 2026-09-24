<?php

namespace App\Services;

use App\Models\tbl_karigar_job;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KarigarService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function issue(array $data, ?int $userId = null): tbl_karigar_job
    {
        return DB::transaction(function () use ($data, $userId) {
            $pieces = max(1, (int) ($data['issued_pieces'] ?? 1));
            $sellQualityId = (int) $data['sell_quality_id'];

            $quality = \App\Models\tbl_sell_quality::with('category')->find($sellQualityId);
            if (!$quality || !$quality->category) {
                throw new RuntimeException('Invalid item type selected.');
            }

            $categoryMetal = $quality->category->metal_type ?? null;
            if ($categoryMetal && $categoryMetal !== $data['metal_type']) {
                throw new RuntimeException(
                    'Metal type does not match the selected item type category (' . $categoryMetal . ').'
                );
            }

            $job = new tbl_karigar_job();
            $job->karigar_id = (int) $data['karigar_id'];
            $job->job_date = $data['job_date'];
            $job->metal_type = $data['metal_type'];
            $job->sell_quality_id = $sellQualityId;
            $job->issued_weight_grams = (float) $data['issued_weight_grams'];
            $job->item_description = $data['item_description'] ?? null;
            $job->notes = $data['notes'] ?? null;
            $job->job_status = 'issued';
            $job->created_by = $userId;
            $job->save();

            $this->stockService->issueQualityToKarigar(
                $job->metal_type,
                $sellQualityId,
                (float) $job->issued_weight_grams,
                $pieces,
                (int) $job->karigar_job_id,
                $userId,
                'Karigar outward #' . $job->karigar_job_id
            );

            return $job->fresh(['karigar', 'quality']);
        });
    }

    public function addIssue(tbl_karigar_job $job, array $data, ?int $userId = null): tbl_karigar_job
    {
        if ($job->job_status !== 'issued') {
            throw new RuntimeException('Extra outward can only be added to issued jobs.');
        }

        return DB::transaction(function () use ($job, $data, $userId) {
            $pieces = max(1, (int) ($data['issued_pieces'] ?? 1));
            $extraWeight = (float) $data['issued_weight_grams'];
            $sellQualityId = (int) ($data['sell_quality_id'] ?? $job->sell_quality_id);

            if ($extraWeight <= 0) {
                throw new RuntimeException('Additional weight must be greater than zero.');
            }

            if ($sellQualityId <= 0) {
                throw new RuntimeException('Select an item type from stock for this outward.');
            }

            $quality = \App\Models\tbl_sell_quality::with('category')->find($sellQualityId);
            if (!$quality || !$quality->category) {
                throw new RuntimeException('Invalid item type selected.');
            }

            $categoryMetal = $quality->category->metal_type ?? null;
            if ($categoryMetal && $categoryMetal !== $job->metal_type) {
                throw new RuntimeException(
                    'Extra outward item type must match this job metal (' . $job->metal_type . ').'
                );
            }

            $qualityName = $quality->quality_name ?: ('#' . $sellQualityId);

            $this->stockService->issueQualityToKarigar(
                $job->metal_type,
                $sellQualityId,
                $extraWeight,
                $pieces,
                (int) $job->karigar_job_id,
                $userId,
                'Karigar extra outward #' . $job->karigar_job_id . ' (' . $qualityName . ')'
            );

            $job->issued_weight_grams = round((float) $job->issued_weight_grams + $extraWeight, 3);

            $autoNote = 'Extra outward: ' . $qualityName . ' '
                . number_format($extraWeight, 3) . 'g × ' . $pieces . ' pc';
            $userNote = !empty($data['notes']) ? trim((string) $data['notes']) : '';
            $combined = $userNote !== '' ? ($autoNote . ' — ' . $userNote) : $autoNote;
            $job->notes = trim(($job->notes ? $job->notes . "\n" : '') . $combined);
            $job->save();

            return $job->fresh(['karigar', 'quality']);
        });
    }

    public function returnJob(tbl_karigar_job $job, array $data, ?int $userId = null): tbl_karigar_job
    {
        if ($job->job_status !== 'issued') {
            throw new RuntimeException('Only issued jobs can be returned.');
        }

        return DB::transaction(function () use ($job, $data, $userId) {
            $returnedWeight = (float) $data['returned_weight_grams'];
            $pieces = max(0, (int) ($data['returned_pieces'] ?? 0));
            $wastage = max(0, (float) ($data['wastage_grams'] ?? 0));
            $sellQualityId = (int) $data['sell_quality_id'];
            $mazduri = round(max((float) ($data['mazduri_cost'] ?? 0), 0), 2);

            if ($returnedWeight <= 0) {
                throw new RuntimeException('Returned weight must be greater than zero.');
            }

            $job->sell_quality_id = $sellQualityId;
            $job->returned_weight_grams = $returnedWeight;
            $job->returned_pieces = $pieces;
            $job->wastage_grams = $wastage;
            $job->mazduri_cost = $mazduri;
            $job->return_date = $data['return_date'] ?? now();
            $job->job_status = 'returned';
            $job->item_description = $data['item_description'] ?? $job->item_description;
            $job->notes = $data['notes'] ?? $job->notes;
            $job->save();

            $grossW = (float) ($data['gross_weight'] ?? $returnedWeight);
            $stoneW = (float) ($data['stone_weight'] ?? 0);
            $purityK = (float) ($data['purity_karat'] ?? 22.0);
            $tagNo = $data['tag_number'] ?? null;

            $this->stockService->returnMetalFromKarigar(
                $job->metal_type,
                $sellQualityId,
                $returnedWeight,
                max(1, $pieces),
                (int) $job->karigar_job_id,
                $userId,
                'Karigar inward #' . $job->karigar_job_id,
                $grossW,
                $stoneW,
                $purityK,
                $tagNo
            );

            return $job->fresh(['karigar', 'quality']);
        });
    }

    public function deleteJob(tbl_karigar_job $job, ?int $userId = null): void
    {
        if ($job->invoice_mst_id) {
            throw new RuntimeException('Cannot delete: this job is linked to an invoice.');
        }

        if (!$job->karigar_job_status) {
            throw new RuntimeException('Job not found.');
        }

        DB::transaction(function () use ($job, $userId) {
            $jobId = (int) $job->karigar_job_id;

            if ($job->job_status === 'returned') {
                $this->stockService->reversePurchase('karigar_return', $jobId, $userId);
            }

            if (in_array($job->job_status, ['issued', 'returned'], true)) {
                $reversed = $this->stockService->reverseKarigarIssue($jobId, $userId);
                if (!$reversed) {
                    $this->stockService->restoreLegacyKarigarIssue($job, $userId);
                }
            }

            $job->karigar_job_status = false;
            $job->job_status = 'cancelled';
            $job->save();
        });
    }

    public function linkJobToInvoice(int $karigarJobId, int $invoiceMstId): void
    {
        $job = tbl_karigar_job::where('karigar_job_id', $karigarJobId)
            ->where('karigar_job_status', true)
            ->where('job_status', 'returned')
            ->first();

        if (!$job) {
            throw new RuntimeException('Karigar job not found or not ready for sale.');
        }

        if ($job->invoice_mst_id && (int) $job->invoice_mst_id !== $invoiceMstId) {
            throw new RuntimeException('This karigar job is already linked to another invoice.');
        }

        $job->invoice_mst_id = $invoiceMstId;
        $job->save();
    }
}
