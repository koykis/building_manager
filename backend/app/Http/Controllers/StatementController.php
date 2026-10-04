<?php

namespace App\Http\Controllers;

use App\Models\Statement;
use App\Services\Reconciliation;
use App\Services\StatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatementController
{
    public function index(Request $r)
    {
        $validated = $r->validate(['history' => 'sometimes|boolean', 'per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1|max:100000', 'status' => 'sometimes|in:draft,published,superseded', 'from' => ['sometimes', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'to' => ['sometimes', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        abort_if(isset($validated['from'], $validated['to']) && $validated['from'] > $validated['to'], 422, 'From must precede to.');
        $query = Statement::select(['id', 'period', 'issue_date', 'revision', 'version', 'status', 'printed_total', 'reviewed', 'created_at', 'updated_at'])
            ->when(! $r->boolean('history'), fn ($q) => $q->where('status', '!=', 'superseded'))
            ->when(isset($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->when(isset($validated['from']), fn ($q) => $q->where('period', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($q) => $q->where('period', '<=', $validated['to']))
            ->orderByDesc('period')->orderByDesc('revision')->orderByDesc('id');

        return isset($validated['per_page']) ? $query->simplePaginate((int) $validated['per_page']) : $query->get();
    }

    public function show(Statement $statement)
    {
        return $statement->load(['sections', 'lines', 'rows.cells', 'documents']);
    }

    public function store(Request $r, StatementService $service)
    {
        return $service->save($r->all(), null, $r->user()->id);
    }

    public function update(Request $r, Statement $statement, StatementService $service)
    {
        return $service->save($r->all(), $statement, $r->user()->id);
    }

    public function review(Statement $statement, Reconciliation $service)
    {
        return $service->run($statement);
    }

    public function publish(Request $r, Statement $statement, StatementService $service)
    {
        $v = $r->validate(['version' => 'required|integer']);

        return $service->publish($statement, $v['version'], $r->user()->id);
    }

    public function revise(Request $r, Statement $statement, StatementService $service)
    {
        return $service->revise($statement, $r->user()->id);
    }

    public function destroy(Request $r, Statement $statement, StatementService $service)
    {
        $v = $r->validate(['version' => 'required|integer']);
        DB::transaction(function () use ($r, $statement, $service, $v) {
            $s = Statement::lockForUpdate()->findOrFail($statement->id);
            abort_unless($s->status === 'draft' && $s->version === $v['version'], 409);
            $service->audit($s, 'statement.deleted', $r->user()->id, $s->load(['sections', 'lines', 'rows.cells', 'documents'])->toArray());
            $s->documents()->delete();
            $s->delete();
        });

        return response()->noContent();
    }
}
