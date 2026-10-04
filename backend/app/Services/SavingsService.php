<?php

namespace App\Services;

use App\Models\CapitalProject;
use App\Models\Statement;
use Carbon\CarbonImmutable;

class SavingsService
{
    public function calculate(CapitalProject $p, string $through): array
    {
        $reasons = [];
        $p->loadMissing(['costLines:expense_lines.id,statement_id,amount']);
        $sources = Statement::select(['id', 'period', 'status'])->where(function ($query) use ($p) {
            $query->whereIn('id', $p->costLines->pluck('statement_id'))->orWhereIn('period', $p->baseline_periods ?? []);
        })->get();
        $publishedIds = $sources->where('status', 'published')->pluck('id')->all();
        $publishedPeriods = $sources->where('status', 'published')->pluck('period')->all();
        $cost = Decimal::sum($p->costLines->pluck('amount'));
        if (! $p->completion_date) {
            $reasons[] = 'completion_date_required';
        }
        if (bccomp($cost, '0', 4) <= 0) {
            $reasons[] = 'documented_cost_required';
        }
        if (! $p->target_categories) {
            $reasons[] = 'target_categories_required';
        }
        if (! $p->baseline || ! $p->baseline_periods || ! $p->baseline_notes) {
            $reasons[] = 'documented_baseline_required';
        }
        if ($p->costLines->contains(fn ($line) => ! in_array($line->statement_id, $publishedIds, true))) {
            $reasons[] = 'cost_source_superseded';
        }
        foreach ($p->baseline_periods ?? [] as $period) {
            if (! in_array($period, $publishedPeriods, true)) {
                $reasons[] = 'baseline_period_unavailable';
            }
        }
        if ($p->completion_date) {
            $start = CarbonImmutable::parse($p->completion_date)->startOfMonth()->addMonth();
            $end = CarbonImmutable::createFromFormat('!Y-m', $through);
            if ($end->lt($start)) {
                $reasons[] = 'no_complete_post_month';
            }if ($end->gte(CarbonImmutable::now()->startOfMonth())) {
                $reasons[] = 'period_not_complete';
            }
            foreach ($p->baseline_periods ?? [] as $period) {
                if ($period >= substr($p->completion_date, 0, 7)) {
                    $reasons[] = 'baseline_must_precede_completion';
                }
            }
            foreach (CapitalProject::where('id', '!=', $p->id)->where('completion_date', '<=', $end->endOfMonth()->toDateString())->get(['id', 'completion_date', 'target_categories']) as $other) {
                if ($other->completion_date <= $end->endOfMonth()->toDateString() && array_intersect($p->target_categories, $other->target_categories)) {
                    $reasons[] = 'overlapping_project_categories';
                }
            }
        }
        if ($reasons) {
            return ['available' => false, 'reasons' => array_values(array_unique($reasons)), 'cost' => $cost, 'months' => []];
        }
        abort_if($start->diffInMonths($end) > 240, 422, 'Choose a range of at most 20 years.');
        $data = Statement::where('status', 'published')->whereBetween('period', [$start->format('Y-m'), $through])
            ->select(['id', 'period'])->with(['lines' => fn ($query) => $query->select(['id', 'statement_id', 'category_id', 'amount', 'classification'])
            ->whereIn('category_id', $p->target_categories)->whereIn('classification', ['operating', 'unclassified'])])->get()->keyBy('period');
        $cumulative = '0.0000';
        $months = [];
        $breakEven = null;
        for ($m = $start; $m->lte($end); $m = $m->addMonth()) {
            $period = $m->format('Y-m');
            $statement = $data->get($period);
            $baseline = $p->baseline[$m->format('m')] ?? null;
            if (! $statement || $baseline === null) {
                $reasons[] = $statement ? 'seasonal_baseline_missing:'.$period : 'actual_missing:'.$period;

                continue;
            }$actual = Decimal::sum($statement->lines->whereIn('category_id', $p->target_categories)->where('classification', 'operating')->pluck('amount'));
            if ($statement->lines->whereIn('category_id', $p->target_categories)->where('classification', 'unclassified')->isNotEmpty()) {
                $reasons[] = 'unclassified_actual:'.$period;
            }$saving = bcsub($baseline, $actual, 4);
            $cumulative = bcadd($cumulative, $saving, 4);
            if ($breakEven === null && bccomp($cumulative, $cost, 4) >= 0) {
                $breakEven = $period;
            }$months[] = ['period' => $period, 'baseline' => $baseline, 'actual' => $actual, 'savings' => $saving, 'cumulative' => $cumulative];
        }
        if ($reasons) {
            return ['available' => false, 'reasons' => $reasons, 'cost' => $cost, 'months' => []];
        }

        return ['available' => true, 'reasons' => [], 'cost' => $cost, 'months' => $months, 'cumulative' => $cumulative, 'net' => bcsub($cumulative, $cost, 4), 'first_break_even' => $breakEven, 'currently_covered' => bccomp($cumulative, $cost, 4) >= 0, 'estimated' => true];
    }
}
