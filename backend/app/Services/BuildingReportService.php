<?php

namespace App\Services;

use App\Models\ApartmentStatementRow;
use App\Models\ExpenseCategory;
use App\Models\ExpenseLine;
use App\Models\Statement;
use Carbon\CarbonImmutable;

class BuildingReportService
{
    public function coverage(): array
    {
        $coverage = Statement::where('status', 'published')->selectRaw('MIN(period) as first, MAX(period) as last, COUNT(*) as published')->toBase()->first();

        return ['first' => $coverage->first, 'last' => $coverage->last, 'published' => (int) $coverage->published];
    }

    /** @return array<string, array> */
    public function monthly(string $from, string $to, bool $includePrior = false, bool $operatingOnly = false, ?int $categoryId = null): array
    {
        $statements = Statement::select(['id', 'period', 'printed_total'])->where('status', 'published')
            ->where(function ($query) use ($from, $to, $includePrior) {
                $query->whereBetween('period', [$from, $to]);
                if ($includePrior) {
                    $query->orWhereBetween('period', [CarbonImmutable::createFromFormat('!Y-m', $from)->subYear()->format('Y-m'), CarbonImmutable::createFromFormat('!Y-m', $to)->subYear()->format('Y-m')]);
                }
            })
            ->withExists(['rows as estimated' => fn ($query) => $query->where('metrics->allocation_method', 'estimated_two_month_split')])
            ->addSelect(['source_period' => ApartmentStatementRow::select('metrics->source_period')->whereColumn('statement_id', 'statements.id')->orderBy('id')->limit(1)])
            ->orderBy('period')->get();
        if ($statements->isEmpty()) {
            return [];
        }
        $amounts = ExpenseLine::whereIn('statement_id', $statements->modelKeys())
            ->when($operatingOnly, fn ($query) => $query->where('classification', 'operating'))
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->select(['statement_id', 'classification', 'category_id'])->selectRaw('SUM(amount) as amount')
            ->groupBy('statement_id', 'classification', 'category_id')->toBase()->get()->groupBy('statement_id');
        $months = [];
        foreach ($statements as $statement) {
            $totals = $this->emptyTotals();
            $categories = [];
            $operatingCategories = [];
            foreach ($amounts->get($statement->id, []) as $line) {
                $amount = bcadd((string) $line->amount, '0', 4);
                $totals[$line->classification] = bcadd($totals[$line->classification], $amount, 4);
                if (in_array($line->classification, ['operating', 'capital'], true)) {
                    $categories[$line->category_id] = bcadd($categories[$line->category_id] ?? '0', $amount, 4);
                }
                if ($line->classification === 'operating') {
                    $operatingCategories[$line->category_id] = $amount;
                }
            }
            $months[$statement->period] = ['period' => $statement->period, 'available' => true, 'totals' => $totals,
                'expense' => bcadd($totals['operating'], $totals['capital'], 4), 'statement_total' => $statement->printed_total,
                'estimated' => (bool) $statement->estimated, 'source_period' => $statement->source_period ?? $statement->period,
                'categories' => $categories, 'operating_categories' => $operatingCategories];
        }

        return $months;
    }

    public function building(string $from, string $to): array
    {
        $data = $this->monthly($from, $to, includePrior: true);
        $months = [];
        $totals = $this->emptyTotals();
        for ($month = CarbonImmutable::createFromFormat('!Y-m', $from), $end = CarbonImmutable::createFromFormat('!Y-m', $to); $month->lte($end); $month = $month->addMonth()) {
            $period = $month->format('Y-m');
            if (! isset($data[$period])) {
                $months[] = ['period' => $period, 'available' => false, 'totals' => null, 'categories' => null, 'operating_categories' => null, 'yoy' => null];

                continue;
            }
            $current = $data[$period];
            foreach (array_keys($totals) as $type) {
                $totals[$type] = bcadd($totals[$type], $current['totals'][$type], 4);
            }
            $prior = $data[$month->subYear()->format('Y-m')] ?? null;
            $months[] = [...$current, 'prior_estimated' => $prior['estimated'] ?? false, 'prior_categories' => $prior['categories'] ?? null,
                'yoy' => $prior ? $this->comparison($current['expense'], $prior['expense']) : null];
        }
        $comparison = null;
        if (collect($months)->every(fn ($month) => $month['available'] && $month['yoy'] !== null)) {
            $current = Decimal::sum(array_column($months, 'expense'));
            $prior = Decimal::sum(array_map(fn ($month) => $month['yoy']['prior'], $months));
            $comparison = ['current' => $current, ...$this->comparison($current, $prior)];
        }

        return ['comparison' => $comparison, 'months' => $months, 'totals' => $totals,
            'coverage' => ['published' => count(array_filter($months, fn ($month) => $month['available'])), 'expected' => count($months)],
            'categories' => ExpenseCategory::orderBy('id')->get(['id', 'code', 'name_el', 'name_en'])->toArray()];
    }

    public function recurring(string $from, string $to, ?int $categoryId = null): array
    {
        $data = $this->monthly($from, $to, operatingOnly: true, categoryId: $categoryId);
        $occurrences = [];
        foreach ($data as $month) {
            foreach ($month['operating_categories'] as $id => $amount) {
                if (bccomp($amount, '0', 4) !== 0) {
                    $occurrences[$id] = ($occurrences[$id] ?? 0) + 1;
                }
            }
        }
        $ids = $categoryId === null ? array_keys(array_filter($occurrences, fn ($count) => $count >= 2)) : [$categoryId];
        $categories = ExpenseCategory::whereIn('id', $ids)->orderBy('id')->get(['id', 'code', 'name_el', 'name_en'])->toArray();
        $statistics = [];
        foreach ($categories as $category) {
            $samples = array_fill(1, 12, []);
            foreach ($data as $month) {
                $samples[(int) substr($month['period'], 5, 2)][] = ['amount' => $month['operating_categories'][$category['id']] ?? '0.0000', 'estimated' => $month['estimated']];
            }
            $months = [];
            foreach ($samples as $number => $entries) {
                $months[] = ['month' => $number, ...$this->statistics($entries)];
            }
            $statistics[$category['id']] = ['months' => $months, 'summary' => $this->statistics(array_merge(...array_values($samples)))];
        }

        return ['from' => $from, 'to' => $to, 'categories' => $categories, 'statistics' => $statistics,
            'coverage' => ['published' => count($data), 'expected' => (int) CarbonImmutable::createFromFormat('!Y-m', $from)->diffInMonths(CarbonImmutable::createFromFormat('!Y-m', $to)) + 1,
                'estimated' => count(array_filter($data, fn ($month) => $month['estimated']))]];
    }

    private function statistics(array $entries): array
    {
        if (! $entries) {
            return ['count' => 0, 'estimated_count' => 0, 'average' => null, 'min' => null, 'max' => null];
        }
        $amounts = array_column($entries, 'amount');
        $min = $max = $amounts[0];
        foreach ($amounts as $amount) {
            if (bccomp($amount, $min, 4) < 0) {
                $min = $amount;
            }
            if (bccomp($amount, $max, 4) > 0) {
                $max = $amount;
            }
        }

        return ['count' => count($entries), 'estimated_count' => count(array_filter($entries, fn ($entry) => $entry['estimated'])),
            'average' => bcdiv(Decimal::sum($amounts), (string) count($entries), 4), 'min' => $min, 'max' => $max];
    }

    private function emptyTotals(): array
    {
        return ['operating' => '0.0000', 'capital' => '0.0000', 'reserve' => '0.0000', 'unclassified' => '0.0000'];
    }

    private function comparison(string $current, string $prior): array
    {
        $delta = bcsub($current, $prior, 4);

        return ['prior' => $prior, 'delta' => $delta, 'percent' => bccomp($prior, '0', 4) === 0 ? null : bcdiv(bcmul($delta, '100', 6), $prior, 2)];
    }
}
