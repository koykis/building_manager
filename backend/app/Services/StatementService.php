<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Statement;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StatementService
{
    const COLUMNS = ['common', 'lift', 'heating', 'individual', 'boiler', 'special', 'owners', 'equal', 'standalone', 'issuance', 'closed'];

    public function validate(array $data): array
    {
        $v = Validator::make($data, [
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'issue_date' => 'nullable|date_format:Y-m-d', 'printed_total' => 'required', 'version' => 'sometimes|integer|min:1', 'reviewed' => 'required|boolean', 'notes' => 'nullable|string|max:10000', 'exceptions' => 'present|array', 'exceptions.*' => 'string|max:2000',
            'sections' => 'present|array|max:20', 'sections.*.key' => ['required', 'distinct', Rule::in(self::COLUMNS)], 'sections.*.label' => 'required|string|max:150', 'sections.*.allocation_column' => ['required', Rule::in(self::COLUMNS)], 'sections.*.printed_total' => 'required',
            'lines' => 'present|array|max:500', 'lines.*.section_key' => ['required', Rule::in(collect($data['sections'] ?? [])->filter(fn ($section) => is_array($section))->pluck('key')->all())], 'lines.*.category_id' => 'required|exists:expense_categories,id', 'lines.*.description' => 'required|string|max:255', 'lines.*.amount' => 'required', 'lines.*.classification' => 'required|in:operating,capital,reserve,unclassified',
            'rows' => 'present|array|max:100', 'rows.*.apartment_id' => 'required|distinct|exists:apartments,id', 'rows.*.printed_total' => 'required', 'rows.*.metrics' => 'present|array', 'rows.*.metrics.*' => 'nullable|string|max:100', 'rows.*.cells' => 'present|array|max:11', 'rows.*.cells.*.column' => ['required', Rule::in(self::COLUMNS)], 'rows.*.cells.*.raw' => 'nullable|string|max:100', 'rows.*.cells.*.state' => 'required|in:value,zero,blank,unreadable',
        ])->validate();
        foreach ($v['sections'] as &$sec) {
            $sec = Arr::only($sec, ['key', 'label', 'allocation_column', 'printed_total']);
        }unset($sec);
        foreach ($v['lines'] as &$line) {
            $line = Arr::only($line, ['section_key', 'category_id', 'description', 'amount', 'classification']);
        }unset($line);
        foreach ($v['rows'] as &$row) {
            $row = Arr::only($row, ['apartment_id', 'printed_total', 'metrics', 'cells']);
            foreach ($row['cells'] as &$cell) {
                $cell = Arr::only($cell, ['column', 'raw', 'state']);
            }unset($cell);
        }unset($row);
        $v['printed_total'] = Decimal::parse($v['printed_total'], 2);
        foreach ($v['sections'] as &$sec) {
            $sec['printed_total'] = Decimal::parse($sec['printed_total'], 2);
        }unset($sec);
        foreach ($v['lines'] as &$line) {
            $line['amount'] = Decimal::parse($line['amount'], 2);
        }unset($line);
        foreach ($v['rows'] as &$row) {
            $row['printed_total'] = Decimal::parse($row['printed_total'], 2);
            foreach (['boiler_m3', 'individual_usage', 'common_permille', 'lift_permille', 'heating_permille'] as $metric) {
                $raw = $row['metrics'][$metric] ?? null;
                if ($raw !== null && $raw !== '') {
                    $value = Decimal::parse($raw, 4, str_contains($raw, ',') ? 'el' : 'canonical');
                    abort_if(bccomp($value, '0', 4) < 0, 422, 'Usage and weights cannot be negative.');
                }
            }
            $seen = [];
            foreach ($row['cells'] as &$cell) {
                abort_if(in_array($cell['column'], $seen), 422, 'Duplicate allocation column');
                $seen[] = $cell['column'];
                if ($cell['state'] === 'blank') {
                    abort_if(trim($cell['raw'] ?? '') !== '', 422, 'A blank cell cannot contain a value.');
                }
                if ($cell['state'] === 'zero') {
                    abort_unless(bccomp(Decimal::parse($cell['raw'] ?? '', 4, str_contains($cell['raw'] ?? '', ',') ? 'el' : 'canonical'), '0', 4) === 0, 422, 'An explicit zero must contain zero.');
                }
                $cell['amount'] = match ($cell['state']) {
                    'blank','unreadable' => null,'zero' => '0.0000',default => Decimal::parse($cell['raw'] ?? '', 4, str_contains($cell['raw'] ?? '', ',') ? 'el' : 'canonical')
                };
                $cell['provenance'] = 'printed';
            }unset($cell);
        }unset($row);

        return $v;
    }

    public function save(array $data, ?Statement $statement = null, ?int $actor = null): Statement
    {
        $v = $this->validate($data);

        return DB::transaction(function () use ($v, $statement, $actor) {
            if ($statement) {
                $s = Statement::lockForUpdate()->findOrFail($statement->id);
                abort_unless($s->status === 'draft', 409, 'Published records are immutable. Create a revision.');
                abort_unless(($v['version'] ?? 0) === $s->version, 409, 'Version conflict');
                abort_unless($v['period'] === $s->period, 422, 'Period cannot change.');
                $before = $s->load(['sections', 'lines', 'rows.cells'])->toArray();
                $s->version++;
            } else {
                abort_if(Statement::where('period', $v['period'])->exists(), 409, 'Period exists. Open or revise it.');
                $s = new Statement;
                $before = null;
            }
            $s->fill(collect($v)->except(['sections', 'lines', 'rows', 'version'])->all());
            $s->save();
            $s->sections()->delete();
            $s->lines()->delete();
            $s->rows()->delete();
            foreach ($v['sections'] as $sec) {
                $s->sections()->create($sec);
            }
            foreach ($v['lines'] as $line) {
                $s->lines()->create($line);
            }
            foreach ($v['rows'] as $row) {
                $cells = $row['cells'];
                unset($row['cells']);
                $new = $s->rows()->create($row);
                foreach ($cells as $cell) {
                    $new->cells()->create($cell);
                }
            }
            $s->refresh()->load(['sections', 'lines', 'rows.cells', 'documents']);
            $this->audit($s, 'statement.saved', $actor, ['before' => $before, 'after' => $s->toArray()]);

            return $s;
        });
    }

    public function publish(Statement $statement, int $version, ?int $actor): Statement
    {
        return DB::transaction(function () use ($statement, $version, $actor) {
            $s = Statement::lockForUpdate()->findOrFail($statement->id);
            abort_unless($s->status === 'draft' && $s->version === $version, 409);
            $review = app(Reconciliation::class)->run($s);
            abort_unless($review['can_publish'], 422, 'Review and reconciliation must be complete.');
            Statement::where('period', $s->period)->where('status', 'published')->update(['status' => 'superseded', 'published_period' => null]);
            $s->update(['status' => 'published', 'published_period' => $s->period, 'version' => $s->version + 1]);
            $this->audit($s, 'statement.published', $actor, $review);

            return $s->fresh();
        });
    }

    public function revise(Statement $statement, ?int $actor): Statement
    {
        return DB::transaction(function () use ($statement, $actor) {
            $s = Statement::lockForUpdate()->findOrFail($statement->id);
            abort_unless($s->status === 'published', 409);
            abort_if(Statement::where('period', $s->period)->where('status', 'draft')->exists(), 409, 'Draft revision already exists.');
            $s->load(['sections', 'lines', 'rows.cells', 'documents']);
            $n = $s->replicate();
            $n->revision = Statement::where('period', $s->period)->max('revision') + 1;
            $n->version = 1;
            $n->status = 'draft';
            $n->published_period = null;
            $n->reviewed = false;
            $n->exceptions = [];
            $n->save();
            foreach (['sections', 'lines', 'documents'] as $rel) {
                foreach ($s->$rel as $item) {
                    $copy = $item->replicate();
                    $copy->statement_id = $n->id;
                    $copy->save();
                }
            }foreach ($s->rows as $row) {
                $copy = $row->replicate();
                $copy->statement_id = $n->id;
                $copy->save();
                foreach ($row->cells as $cell) {
                    $c = $cell->replicate();
                    $c->row_id = $copy->id;
                    $c->save();
                }
            }$this->audit($n, 'statement.revised', $actor, ['previous' => $s->id]);

            return $n;
        });
    }

    public function audit($s, $action, $actor, $changes): void
    {
        AuditEvent::create(['user_id' => $actor, 'action' => $action, 'entity' => 'statement', 'entity_id' => $s->id, 'changes' => $changes]);
    }
}
