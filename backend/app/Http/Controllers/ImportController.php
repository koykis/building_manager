<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\Statement;
use App\Services\StatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ImportController
{
    public function index(Request $request)
    {
        $validated = $request->validate(['per_page' => 'sometimes|integer|min:1|max:50', 'page' => 'sometimes|integer|min:1|max:100000']);
        $query = ImportBatch::select(['id', 'name', 'created_at', 'updated_at', 'item_results'])->orderByDesc('id');
        $compact = fn ($batch) => ['id' => $batch->id, 'name' => $batch->name, 'created_at' => $batch->created_at, 'updated_at' => $batch->updated_at,
            'items' => $batch->item_results ?? []];

        return isset($validated['per_page']) ? $query->simplePaginate((int) $validated['per_page'])->through($compact) : $query->get()->map($compact);
    }

    public function store(Request $r, StatementService $service)
    {
        $r->validate(['file' => 'required|file|max:20480']);
        $f = fopen($r->file('file')->getRealPath(), 'r');
        $header = fgetcsv($f, 0, ',', '"', '');
        abort_unless($header === ['period', 'payload'], 422, 'Expected CSV header: period,payload');
        $items = [];
        while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
            abort_if(count($items) >= 500, 422, 'Maximum 500 statements per batch');
            abort_unless(count($row) === 2, 422, 'Each CSV row needs period and JSON payload');
            $items[] = ['period' => $row[0], 'payload' => $row[1], 'status' => 'pending'];
        }fclose($f);
        $batch = ImportBatch::create(['name' => $r->file('file')->getClientOriginalName(), 'items' => $items]);

        return $this->process($batch, $service, $r->user()->id);
    }

    public function resume(Request $r, ImportBatch $batch, StatementService $service)
    {
        return $this->process($batch, $service, $r->user()->id);
    }

    private function process($batch, $service, $actor)
    {
        $items = $batch->items;
        foreach ($items as &$item) {
            if (($item['status'] ?? '') === 'imported') {
                continue;
            }try {
                $existing = Statement::where('period', $item['period'])->first();
                if ($existing) {
                    $item['status'] = 'existing';
                    $item['statement_id'] = $existing->id;
                } else {
                    $data = json_decode($item['payload'], true, 512, JSON_THROW_ON_ERROR);
                    abort_unless(($data['period'] ?? null) === $item['period'], 422, 'Period mismatch');
                    $data['reviewed'] = false;
                    $data['exceptions'] = [];
                    $s = $service->save($data, null, $actor);
                    $item['statement_id'] = $s->id;
                    $item['status'] = 'imported';
                }unset($item['error']);
            } catch (\Throwable $e) {
                $item['status'] = 'error';
                $item['error'] = $e instanceof ValidationException ? implode(' ', Arr::flatten($e->errors())) : 'Invalid statement payload; review the CSV row.';
            }$batch->update(['items' => $items]);
        }unset($item);

        $result = ImportBatch::findOrFail($batch->id, ['id', 'name', 'created_at', 'updated_at', 'item_results']);

        return ['id' => $result->id, 'name' => $result->name, 'items' => $result->item_results, 'created_at' => $result->created_at, 'updated_at' => $result->updated_at];
    }

    public function export(Statement $statement)
    {
        $statement->load(['sections', 'lines', 'rows.cells']);

        return response()->streamDownload(function () use ($statement) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['period', 'payload'], ',', '"', '');
            fputcsv($f, [$statement->period, json_encode($statement, JSON_UNESCAPED_UNICODE)], ',', '"', '');
            fclose($f);
        }, 'statement-'.$statement->period.'-r'.$statement->revision.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
