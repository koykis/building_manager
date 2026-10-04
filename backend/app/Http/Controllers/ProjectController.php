<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\CapitalProject;
use App\Models\ExpenseCategory;
use App\Models\ExpenseLine;
use App\Models\Statement;
use App\Services\Decimal;
use App\Services\SavingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController
{
    public function index()
    {
        return CapitalProject::with('costLines')->get();
    }

    public function candidates(Request $request)
    {
        $validated = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1|max:100000', 'from' => ['sometimes', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'to' => ['sometimes', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        abort_if(isset($validated['from'], $validated['to']) && $validated['from'] > $validated['to'], 422, 'From must precede to.');
        $query = ExpenseLine::select(['expense_lines.id', 'expense_lines.statement_id', 'expense_lines.category_id', 'expense_lines.description', 'expense_lines.amount', 'expense_lines.classification', 'statements.period'])
            ->join('statements', 'statements.id', '=', 'expense_lines.statement_id')->where('statements.status', 'published')
            ->whereIn('classification', ['operating', 'capital'])
            ->where(function ($query) {
                $query->whereIn('category_id', ExpenseCategory::whereIn('code', ['plumbing', 'passenger_lift_repairs', 'car_lift_maintenance', 'heating_maintenance'])->select('id'))
                    ->orWhere('classification', 'capital');
            })
            ->when(isset($validated['from']), fn ($q) => $q->where('statements.period', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($q) => $q->where('statements.period', '<=', $validated['to']))
            ->orderByDesc('statements.period')->orderBy('expense_lines.id');

        return isset($validated['per_page']) ? $query->simplePaginate((int) $validated['per_page']) : $query->get();
    }

    public function save(Request $r, ?CapitalProject $project = null)
    {
        $v = $r->validate(['title' => 'required|string|max:200', 'description' => 'nullable|string|max:10000', 'completion_date' => 'nullable|date_format:Y-m-d', 'target_categories' => 'present|array|max:100|exists:expense_categories,id', 'target_categories.*' => 'integer|distinct', 'cost_line_ids' => 'present|array|max:500|exists:expense_lines,id', 'cost_line_ids.*' => 'integer|distinct', 'baseline' => 'present|array|max:12', 'baseline.*' => 'string', 'baseline_periods' => 'present|array|max:240', 'baseline_periods.*' => ['regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'baseline_notes' => 'nullable|string|max:5000']);
        foreach ($v['baseline'] as $month => &$amount) {
            abort_unless(preg_match('/^(0[1-9]|1[0-2])$/', (string) $month), 422, 'Baseline keys must be 01 through 12.');
            $amount = Decimal::parse($amount, 2);
        }unset($amount);

        return DB::transaction(function () use ($v, $project, $r) {
            $p = $project ? CapitalProject::lockForUpdate()->findOrFail($project->id) : new CapitalProject;
            $before = $p->toArray();
            $ids = $v['cost_line_ids'];
            unset($v['cost_line_ids']);
            $lines = ExpenseLine::whereIn('id', $ids)->lockForUpdate()->get();
            $publishedIds = Statement::whereIn('id', $lines->pluck('statement_id'))->where('status', 'published')->pluck('id')->all();
            $linkedIds = DB::table('project_cost_lines')->whereIn('expense_line_id', $ids)->when($p->id, fn ($q) => $q->where('project_id', '!=', $p->id))->pluck('expense_line_id')->all();
            foreach ($lines as $line) {
                abort_unless(in_array($line->statement_id, $publishedIds, true), 422, 'Cost source must be published.');
                abort_if($line->classification === 'reserve' || $line->classification === 'unclassified', 422, 'Reserve or unclassified entries cannot be project cost.');
                abort_if(in_array($line->id, $linkedIds, true), 422, 'Cost already linked to another project.');
            }$p->fill($v)->save();
            $p->costLines()->sync($ids);
            AuditEvent::create(['user_id' => $r->user()->id, 'action' => 'project.saved', 'entity' => 'project', 'entity_id' => $p->id, 'changes' => ['before' => $before, 'after' => $p->toArray()]]);

            return $p->load('costLines');
        });
    }

    public function savings(Request $r, CapitalProject $project, SavingsService $service)
    {
        $v = $r->validate(['through' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        return $service->calculate($project, $v['through']);
    }
}
