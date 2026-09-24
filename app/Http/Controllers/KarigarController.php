<?php

namespace App\Http\Controllers;

use App\Models\tbl_karigar;
use App\Models\tbl_karigar_job;
use App\Services\KarigarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KarigarController extends Controller
{
    protected KarigarService $karigarService;

    public function __construct(KarigarService $karigarService)
    {
        $this->karigarService = $karigarService;
    }

    public function listKarigars(Request $request)
    {
        return tbl_karigar::select('karigar_id', 'karigar_name', 'contact_no', 'address')
            ->where('karigar_status', true)
            ->orderBy('karigar_name')
            ->get();
    }

    public function getKarigars(Request $request)
    {
        $paginate = (int) request('paginate', 10);
        $search = '%' . trim((string) request('search', '')) . '%';

        return tbl_karigar::select('karigar_id', 'karigar_name', 'contact_no', 'address')
            ->where('karigar_status', true)
            ->where(function ($q) use ($search) {
                $q->where('karigar_name', 'like', $search)
                    ->orWhere('contact_no', 'like', $search)
                    ->orWhere('address', 'like', $search);
            })
            ->orderBy('karigar_name')
            ->paginate($paginate);
    }

    public function storeKarigar(Request $request)
    {
        $validated = validator($request->all(), [
            'karigarName' => 'required|max:100',
            'contactNo' => 'nullable|digits_between:10,11',
            'address' => 'nullable|max:255',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'The given data was invalid.',
                'errors' => $validated->errors(),
            ]);
        }

        $karigar = new tbl_karigar();
        $karigar->karigar_name = $request->input('karigarName');
        $karigar->contact_no = trim((string) $request->input('contactNo', '')) ?: null;
        $karigar->address = trim((string) $request->input('address', '')) ?: null;
        $karigar->save();

        return response()->json(['status' => 1, 'message' => 'Karigar added successfully.']);
    }

    public function updateKarigar(Request $request, $karigarId)
    {
        $validated = validator($request->all(), [
            'karigarName' => 'required|max:100',
            'contactNo' => 'nullable|digits_between:10,11',
            'address' => 'nullable|max:255',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'The given data was invalid.',
                'errors' => $validated->errors(),
            ]);
        }

        $karigar = tbl_karigar::findOrFail($karigarId);
        $karigar->karigar_name = $request->input('karigarName');
        $karigar->contact_no = trim((string) $request->input('contactNo', '')) ?: null;
        $karigar->address = trim((string) $request->input('address', '')) ?: null;
        $karigar->save();

        return response()->json(['status' => 1, 'message' => 'Karigar updated successfully.']);
    }

    public function deleteKarigar(Request $request, $karigarId)
    {
        tbl_karigar::where('karigar_id', $karigarId)->update(['karigar_status' => false]);
        return response()->json(['status' => 1, 'message' => 'Karigar deleted successfully.']);
    }

    public function getJobs(Request $request)
    {
        $paginate = (int) request('paginate', 10);
        $status = request('status', '');

        $query = tbl_karigar_job::with([
            'karigar:karigar_id,karigar_name',
            'quality:sell_quality_id,quality_name',
        ])
            ->where('karigar_job_status', true)
            ->orderByDesc('job_date')
            ->orderByDesc('karigar_job_id');

        if ($status !== '') {
            $query->where('job_status', $status);
        }

        return $query->paginate($paginate);
    }

    public function getPendingJobsForSale(Request $request)
    {
        $sellQualityId = (int) $request->query('sell_quality_id', 0);

        $query = tbl_karigar_job::with(['karigar:karigar_id,karigar_name'])
            ->where('karigar_job_status', true)
            ->where('job_status', 'returned')
            ->whereNull('invoice_mst_id');

        if ($sellQualityId > 0) {
            $query->where('sell_quality_id', $sellQualityId);
        }

        return $query->orderByDesc('return_date')->get()->map(function ($job) {
            return [
                'karigar_job_id' => (int) $job->karigar_job_id,
                'karigar_name' => optional($job->karigar)->karigar_name,
                'returned_weight_grams' => (float) $job->returned_weight_grams,
                'mazduri_cost' => (float) $job->mazduri_cost,
                'item_description' => $job->item_description,
            ];
        });
    }

    public function issueJob(Request $request)
    {
        $validated = validator($request->all(), [
            'karigarId' => 'required|integer|exists:tbl_karigars,karigar_id',
            'jobDate' => 'required|date',
            'metalType' => 'required|in:gold,silver',
            'sellQualityId' => 'required|integer|exists:tbl_sell_qualities,sell_quality_id',
            'issuedWeightGrams' => 'required|numeric|min:0.001',
            'issuedPieces' => 'nullable|integer|min:1',
            'itemDescription' => 'nullable|max:255',
            'notes' => 'nullable|max:500',
        ]);

        if ($validated->fails()) {
            $firstError = $validated->errors()->first();

            return response()->json([
                'status' => -1,
                'message' => $firstError ?: 'The given data was invalid.',
                'errors' => $validated->errors(),
            ], 422);
        }

        try {
            $this->karigarService->issue([
                'karigar_id' => (int) $request->input('karigarId'),
                'job_date' => $request->input('jobDate'),
                'metal_type' => $request->input('metalType'),
                'sell_quality_id' => (int) $request->input('sellQualityId'),
                'issued_weight_grams' => (float) $request->input('issuedWeightGrams'),
                'issued_pieces' => (int) ($request->input('issuedPieces') ?? 1),
                'item_description' => $request->input('itemDescription'),
                'notes' => $request->input('notes'),
            ], optional($request->user())->id);

            return response()->json(['status' => 1, 'message' => 'Metal issued to karigar (outward recorded).']);
        } catch (\Throwable $e) {
            return response()->json(['status' => -1, 'message' => $e->getMessage()], 422);
        }
    }

    public function addIssueToJob(Request $request, $jobId)
    {
        $validated = validator($request->all(), [
            'sellQualityId' => 'required|integer|exists:tbl_sell_qualities,sell_quality_id',
            'issuedWeightGrams' => 'required|numeric|min:0.001',
            'issuedPieces' => 'nullable|integer|min:1',
            'notes' => 'nullable|max:500',
        ]);

        if ($validated->fails()) {
            $firstError = $validated->errors()->first();

            return response()->json([
                'status' => -1,
                'message' => $firstError ?: 'The given data was invalid.',
                'errors' => $validated->errors(),
            ], 422);
        }

        $job = tbl_karigar_job::where('karigar_job_id', $jobId)
            ->where('karigar_job_status', true)
            ->first();

        if (!$job) {
            return response()->json(['status' => -1, 'message' => 'Job not found.'], 404);
        }

        try {
            $updated = $this->karigarService->addIssue($job, [
                'sell_quality_id' => (int) $request->input('sellQualityId'),
                'issued_weight_grams' => (float) $request->input('issuedWeightGrams'),
                'issued_pieces' => (int) ($request->input('issuedPieces') ?? 1),
                'notes' => $request->input('notes'),
            ], optional($request->user())->id);

            return response()->json([
                'status' => 1,
                'message' => 'Extra outward added. Total issued: ' . number_format((float) $updated->issued_weight_grams, 3) . 'g.',
                'job' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => -1, 'message' => $e->getMessage()], 422);
        }
    }

    public function returnJob(Request $request, $jobId)
    {
        $validated = validator($request->all(), [
            'returnDate' => 'required|date',
            'sellQualityId' => 'required|integer|exists:tbl_sell_qualities,sell_quality_id',
            'returnedWeightGrams' => 'required|numeric|min:0.001',
            'returnedPieces' => 'nullable|integer|min:0',
            'wastageGrams' => 'nullable|numeric|min:0',
            'mazduriCost' => 'nullable|numeric|min:0',
            'itemDescription' => 'nullable|max:255',
            'notes' => 'nullable|max:500',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => -1,
                'message' => 'The given data was invalid.',
                'errors' => $validated->errors(),
            ]);
        }

        $job = tbl_karigar_job::where('karigar_job_id', $jobId)
            ->where('karigar_job_status', true)
            ->first();

        if (!$job) {
            return response()->json(['status' => -1, 'message' => 'Job not found.'], 404);
        }

        try {
            $this->karigarService->returnJob($job, [
                'return_date' => $request->input('returnDate'),
                'sell_quality_id' => (int) $request->input('sellQualityId'),
                'returned_weight_grams' => (float) $request->input('returnedWeightGrams'),
                'returned_pieces' => (int) $request->input('returnedPieces', 1),
                'wastage_grams' => (float) $request->input('wastageGrams', 0),
                'mazduri_cost' => (float) $request->input('mazduriCost', 0),
                'item_description' => $request->input('itemDescription'),
                'gross_weight' => (float) $request->input('grossWeight', $request->input('returnedWeightGrams')),
                'stone_weight' => (float) $request->input('stoneWeight', 0),
                'purity_karat' => (float) $request->input('purityKarat', 22.0),
                'tag_number' => $request->input('tagNumber'),
                'notes' => $request->input('notes'),
            ], optional($request->user())->id);

            return response()->json(['status' => 1, 'message' => 'Finished goods received from karigar (inward recorded).']);
        } catch (\Throwable $e) {
            return response()->json(['status' => -1, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteJob(Request $request, $jobId)
    {
        $job = tbl_karigar_job::where('karigar_job_id', $jobId)
            ->where('karigar_job_status', true)
            ->first();

        if (!$job) {
            return response()->json(['status' => -1, 'message' => 'Job not found.'], 404);
        }

        if ($job->invoice_mst_id) {
            return response()->json([
                'status' => -1,
                'message' => 'Cannot delete: this job is linked to an invoice.',
            ], 422);
        }

        try {
            $this->karigarService->deleteJob($job, optional($request->user())->id);

            return response()->json(['status' => 1, 'message' => 'Karigar job deleted and stock restored.']);
        } catch (\Throwable $e) {
            return response()->json(['status' => -1, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancelJob(Request $request, $jobId)
    {
        $job = tbl_karigar_job::where('karigar_job_id', $jobId)
            ->where('karigar_job_status', true)
            ->where('job_status', 'issued')
            ->first();

        if (!$job) {
            return response()->json(['status' => -1, 'message' => 'Only issued jobs can be cancelled.'], 422);
        }

        return response()->json([
            'status' => -1,
            'message' => 'Cancel with stock reversal is not automated yet. Complete the return or contact admin.',
        ], 422);
    }
}
