<?php

namespace App\Services;

use App\Models\Statement;

class Reconciliation
{
    public function run(Statement $s): array
    {
        $s->load(['sections', 'lines', 'rows.cells', 'documents']);
        $checks = [];
        $problems = [];
        $add = function ($key, $actual, $expected) use (&$checks) {
            $delta = bcsub((string) $actual, (string) $expected, 4);
            $checks[] = ['key' => $key, 'actual' => (string) $actual, 'expected' => (string) $expected, 'delta' => $delta, 'matches' => bccomp($delta, '0', 4) === 0];
        };
        if (! $s->sections->count()) {
            $problems[] = 'sections_required';
        }
        if (! $s->lines->count()) {
            $problems[] = 'lines_required';
        }
        if (! $s->rows->count()) {
            $problems[] = 'rows_required';
        }
        if (! $s->documents->count()) {
            $problems[] = 'source_required';
        }
        if (! $s->reviewed) {
            $problems[] = 'review_required';
        }
        foreach ($s->sections as $sec) {
            $add('section:'.$sec->key, Decimal::sum($s->lines->where('section_key', $sec->key)->pluck('amount')), $sec->printed_total);
        }
        $add('sections:total', Decimal::sum($s->sections->pluck('printed_total')), $s->printed_total);
        foreach ($s->sections->groupBy('allocation_column') as $column => $sections) {
            $values = [];
            foreach ($s->rows as $row) {
                $cell = $row->cells->firstWhere('column', $column);
                if (! $cell) {
                    $problems[] = 'missing_cell:'.$row->apartment_id.':'.$column;
                } else {
                    $values[] = $cell->amount;
                }
            }$add('column:'.$column, Decimal::sum($values), Decimal::sum($sections->pluck('printed_total')));
        }
        foreach ($s->rows as $row) {
            foreach ($row->cells as $cell) {
                if ($cell->state === 'unreadable') {
                    $problems[] = 'unreadable:'.$row->apartment_id.':'.$cell->column;
                }if (! $s->sections->contains('allocation_column', $cell->column) && bccomp($cell->amount ?? '0', '0', 4) !== 0) {
                    $problems[] = 'unmapped_cell:'.$row->apartment_id.':'.$cell->column;
                }
            }$add('row:'.$row->apartment_id, Decimal::sum($row->cells->pluck('amount')), $row->printed_total);
        }
        $add('rows:total', Decimal::sum($s->rows->pluck('printed_total')), $s->printed_total);
        $exceptions = $s->exceptions ?? [];
        foreach ($checks as &$c) {
            $c['reason'] = $exceptions[$c['key']] ?? '';
            $c['accepted'] = $c['matches'] || mb_strlen(trim($c['reason'])) >= 10;
        }unset($c);

        return ['checks' => $checks, 'problems' => array_values(array_unique($problems)), 'can_publish' => ! count($problems) && collect($checks)->every('accepted')];
    }
}
