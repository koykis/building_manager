<?php

namespace App\Console\Commands;

use App\Models\Apartment;
use App\Models\ExpenseCategory;
use App\Models\Statement;
use App\Services\Reconciliation;
use App\Services\StatementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportPilot extends Command
{
    protected $signature = 'app:import-pilot {json} {sources} {--publish-reviewed}';

    protected $description = 'Import a visually reviewed local transcription with original photos; preserve printed rounding';

    public function handle(StatementService $service, Reconciliation $review): int
    {
        $records = json_decode(file_get_contents($this->argument('json')), true, 512, JSON_THROW_ON_ERROR);
        $results = [];
        foreach ($records as $data) {
            $file = basename($data['source_file']);
            unset($data['source_file']);
            $existing = Statement::where('period', $data['period'])->first();
            if ($existing && $existing->status !== 'draft') {
                $this->line($data['period'].' already published; unchanged');

                continue;
            }
            foreach ($data['lines'] as &$line) {
                $line['category_id'] = ExpenseCategory::where('code', $line['category_code'])->firstOrFail()->id;
                unset($line['category_code']);
            }unset($line);
            foreach ($data['rows'] as &$row) {
                $row['apartment_id'] = Apartment::where('alias', $row['apartment_alias'])->firstOrFail()->id;
                unset($row['apartment_alias']);
            }unset($row);
            $s = $existing ?? $service->save($data);
            $source = rtrim($this->argument('sources'), '/').'/'.$file;
            $bytes = file_get_contents($source);
            $path = 'sources/'.hash('sha256', $bytes).'.jpg';
            Storage::disk('local')->put($path, $bytes);
            $s->documents()->firstOrCreate(['sha256' => hash('sha256', $bytes)], ['filename' => $file, 'path' => $path, 'sha256' => hash('sha256', $bytes), 'mime' => 'image/jpeg']);
            $checks = $review->run($s);
            $exceptions = [];
            foreach ($checks['checks'] as $c) {
                if ($c['matches']) {
                    continue;
                }$this->line($s->period.' '.$c['key'].' difference '.$c['delta']);
                if (bccomp(ltrim($c['delta'], '-'), '0.0300', 4) <= 0) {
                    $exceptions[$c['key']] = 'Visual transcription preserves printed values. Sum of displayed components differs from printed total by '.$c['delta'].' EUR due to source precision/rounding; no source value adjusted.';
                }
            }
            if ($this->option('publish-reviewed')) {
                $s->exceptions = $exceptions;
                $s->save();
                $checks = $review->run($s);
                if ($checks['can_publish']) {
                    $service->publish($s, $s->version, null);
                    $this->info($s->period.' published');
                } else {
                    $this->warn($s->period.' kept draft: unresolved checks');
                }
            }
            $results[$s->period] = $checks;
        }
        file_put_contents(storage_path('app/private/pilot-reconciliation.json'), json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
