<?php

namespace App\Http\Controllers;

use App\Models\ApartmentStatementRow;
use App\Services\BuildingReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController
{
    public function coverage(BuildingReportService $service): array
    {
        return $service->coverage();
    }

    public function building(Request $request, BuildingReportService $service): array|StreamedResponse
    {
        $validated = $this->range($request);
        $report = $service->building($validated['from'], $validated['to']);
        if (($validated['format'] ?? 'json') === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, ['period', 'available', 'operating', 'capital', 'reserve', 'unclassified', 'statement_total', 'estimated', 'source_period'], ',', '"', '');
                foreach ($report['months'] as $month) {
                    fputcsv($stream, [$month['period'], (int) $month['available'], ...array_map(fn ($type) => $month['totals'][$type] ?? '', ['operating', 'capital', 'reserve', 'unclassified']), $month['statement_total'] ?? '', (int) ($month['estimated'] ?? false), $month['source_period'] ?? ''], ',', '"', '');
                }
                fclose($stream);
            }, 'building-'.$validated['from'].'-'.$validated['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $view = $validated['view'] ?? 'full';
        if ($view === 'summary') {
            $categoryTotals = [];
            foreach ($report['months'] as $month) {
                foreach ($month['categories'] ?? [] as $id => $amount) {
                    $categoryTotals[$id] = bcadd($categoryTotals[$id] ?? '0', $amount, 4);
                }
            }

            return ['totals' => $report['totals'], 'coverage' => $report['coverage'], 'comparison' => $report['comparison'], 'category_totals' => $categoryTotals, 'categories' => $report['categories']];
        }
        if ($view === 'series') {
            $categoryId = $validated['category_id'] ?? null;
            $datasets = $categoryId === null
                ? array_map(fn ($type) => ['key' => $type, 'data' => array_map(fn ($month) => $month['totals'][$type] ?? null, $report['months'])], ['operating', 'capital', 'reserve', 'unclassified'])
                : [
                    ['key' => 'current', 'data' => array_map(fn ($month) => $month['available'] ? ($month['categories'][$categoryId] ?? '0.0000') : null, $report['months'])],
                    ['key' => 'prior', 'data' => array_map(fn ($month) => $month['available'] && $month['prior_categories'] !== null ? ($month['prior_categories'][$categoryId] ?? '0.0000') : null, $report['months'])],
                ];

            return ['labels' => array_column($report['months'], 'period'), 'datasets' => $datasets, 'coverage' => $report['coverage']];
        }

        return $report;
    }

    public function recurring(Request $request, BuildingReportService $service): array|StreamedResponse
    {
        $validated = $this->range($request);
        $report = $service->recurring($validated['from'], $validated['to'], isset($validated['category_id']) ? (int) $validated['category_id'] : null);
        if (($validated['format'] ?? 'json') === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, ['category_id', 'code', 'name_el', 'name_en', 'month', 'average', 'minimum', 'maximum', 'sample_count', 'estimated_count'], ',', '"', '');
                foreach ($report['categories'] as $category) {
                    foreach ($report['statistics'][$category['id']]['months'] as $month) {
                        fputcsv($stream, [$category['id'], $category['code'], $this->csvLabel($category['name_el']), $this->csvLabel($category['name_en']), $month['month'], $month['average'], $month['min'], $month['max'], $month['count'], $month['estimated_count']], ',', '"', '');
                    }
                }
                fclose($stream);
            }, 'recurring-'.$validated['from'].'-'.$validated['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return $report;
    }

    public function apartment(Request $request): array|Paginator
    {
        $validated = $request->validate([
            'apartment_id' => $request->user()->role === 'admin' ? 'required|integer|exists:apartments,id' : 'prohibited',
            'from' => ['sometimes', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'to' => ['sometimes', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'view' => ['sometimes', Rule::in(['full', 'summary'])], 'per_page' => 'sometimes|integer|min:1|max:120', 'page' => 'sometimes|integer|min:1|max:100000',
        ]);
        $id = $request->user()->role === 'admin' ? $validated['apartment_id'] : $request->user()->apartment_id;
        abort_unless($id, 403);
        abort_if(isset($validated['from'], $validated['to']) && $validated['from'] > $validated['to'], 422, 'From must precede to.');
        $includeCells = ($validated['view'] ?? 'full') === 'full';
        $query = ApartmentStatementRow::select(['apartment_statement_rows.id', 'apartment_statement_rows.statement_id', 'apartment_statement_rows.printed_total', 'apartment_statement_rows.metrics', 'statements.period'])
            ->join('statements', 'statements.id', '=', 'apartment_statement_rows.statement_id')
            ->where('apartment_id', $id)->where('statements.status', 'published')
            ->when(isset($validated['from']), fn ($query) => $query->where('statements.period', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($query) => $query->where('statements.period', '<=', $validated['to']))
            ->when($includeCells, fn ($query) => $query->with(['cells:id,row_id,column,amount,state,provenance']))
            ->orderBy('statements.period')->orderBy('apartment_statement_rows.id');
        $transform = fn ($row) => [
            'period' => $row->period, 'printed_total' => $row->printed_total,
            ...($includeCells ? ['cells' => $row->cells->map(fn ($cell) => ['column' => $cell->column, 'amount' => $cell->amount, 'state' => $cell->state, 'provenance' => $cell->provenance])->all()] : []),
            'boiler_m3' => $row->metrics['boiler_m3'] ?? null, 'estimated' => ($row->metrics['allocation_method'] ?? null) === 'estimated_two_month_split',
            'source_period' => $row->metrics['source_period'] ?? $row->period, 'total_computed' => ($row->metrics['row_total_provenance'] ?? null) === 'computed_from_visible_cells',
        ];

        return isset($validated['per_page']) ? $query->simplePaginate((int) $validated['per_page'])->through($transform) : $query->get()->map($transform)->all();
    }

    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'to' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'view' => ['sometimes', Rule::in(['full', 'summary', 'series'])], 'format' => ['sometimes', Rule::in(['json', 'csv'])],
            'category_id' => 'sometimes|integer|exists:expense_categories,id', 'apartment_id' => 'prohibited', 'group_by' => 'prohibited',
        ]);
        $start = CarbonImmutable::createFromFormat('!Y-m', $validated['from']);
        $end = CarbonImmutable::createFromFormat('!Y-m', $validated['to']);
        abort_if($start->gt($end) || $start->diffInMonths($end) > 240, 422, 'Choose a range of at most 20 years.');

        return $validated;
    }

    private function csvLabel(string $label): string
    {
        return preg_match('/^[=+\-@\t\r\n]/u', $label) ? "'".$label : $label;
    }
}
