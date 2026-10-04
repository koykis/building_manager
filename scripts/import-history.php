<?php

/** Import a reviewed historical bundle atomically; defaults to a rolled-back dry run. */
use App\Models\Apartment;
use App\Models\ExpenseCategory;
use App\Models\Statement;
use App\Services\Reconciliation;
use App\Services\StatementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/import-history.php bundle.json source-directory [--publish]\n");
    exit(1);
}
$publish = in_array('--publish', $argv, true);
$records = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$bundleHash = hash_file('sha256', $argv[1]);
$service = app(StatementService::class);
$reconciliation = app(Reconciliation::class);
$before = Statement::with(['sections', 'lines', 'rows.cells', 'documents'])->orderBy('id')->get();
$beforeHash = hash('sha256', $before->toJson());
$beforeIds = $before->modelKeys();
$aliases = Apartment::pluck('id', 'alias')->all();
$results = [];
DB::beginTransaction();
try {
    foreach ([
        'electrical_repairs' => ['Ηλεκτρολογικές εργασίες', 'Electrical repairs'],
        'liability_insurance' => ['Ασφάλεια αστικής ευθύνης', 'Liability insurance'],
        'solar_maintenance' => ['Συντήρηση ηλιακού', 'Solar water heater maintenance'],
        'car_lift_combined' => ['Αναβατόριο: ρεύμα και επισκευή μαζί', 'Car lift: combined electricity and repair'],
    ] as $code => [$el, $en]) {
        ExpenseCategory::firstOrCreate(['code' => $code], ['name_el' => $el, 'name_en' => $en, 'active' => true]);
    }
    foreach ($records as $data) {
        $existing = Statement::where('period', $data['period'])->first();
        if ($existing) {
            $sameBundle = $existing->rows()->first()?->metrics['history_import_sha256'] ?? null;
            if ($existing->status !== 'published' || $sameBundle !== $bundleHash) {
                throw new RuntimeException('Refusing to overwrite existing period '.$data['period']);
            }
            $results[$data['period']] = ['status' => 'already_imported'];

            continue;
        }
        $file = basename($data['source_file']);
        $source = rtrim($argv[2], '/').'/'.$file;
        $bytes = file_get_contents($source);
        $hash = hash('sha256', $bytes);
        if (! hash_equals($data['source_sha256'], $hash)) {
            throw new RuntimeException('Source hash mismatch: '.$file);
        }
        unset($data['source_file'], $data['source_sha256']);
        foreach ($data['lines'] as &$line) {
            $category = ExpenseCategory::where('code', $line['category_code'])->firstOrFail();
            $line['category_id'] = $category->id;
            if ($line['description'] === $line['category_code']) {
                $line['description'] = $category->name_el;
            }
            unset($line['category_code']);
        }
        unset($line);
        $provenance = [];
        foreach ($data['rows'] as &$row) {
            $alias = $row['apartment_alias'];
            $row['apartment_id'] = $aliases[$alias];
            $row['metrics']['history_import_sha256'] = $bundleHash;
            if (isset($data['exceptions']['row:'.$alias])) {
                $data['exceptions']['row:'.$row['apartment_id']] = $data['exceptions']['row:'.$alias];
                unset($data['exceptions']['row:'.$alias]);
            }
            foreach ($row['cells'] as &$cell) {
                $provenance[$row['apartment_id']][$cell['column']] = $cell['provenance'] ?? 'printed';
                unset($cell['provenance']);
            }
            unset($cell, $row['apartment_alias']);
        }
        unset($row);
        $s = $service->save($data);
        foreach ($s->rows as $row) {
            foreach ($row->cells as $cell) {
                $cell->update(['provenance' => $provenance[$row->apartment_id][$cell->column]]);
            }
        }
        $path = 'sources/'.$hash.'.jpg';
        if ($publish && ! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('Could not archive '.$file);
        }
        $s->documents()->create(['filename' => $file, 'path' => $path, 'sha256' => $hash, 'mime' => 'image/jpeg']);
        $review = $reconciliation->run($s);
        if (! $review['can_publish']) {
            throw new RuntimeException($s->period.' failed reconciliation: '.json_encode($review));
        }
        $service->audit($s, 'statement.history_imported', null, ['bundle_sha256' => $bundleHash, 'source_sha256' => $hash, 'allocation_method' => $data['rows'][0]['metrics']['allocation_method'], 'review' => $review]);
        $service->publish($s, $s->version, null);
        $results[$s->period] = ['status' => $publish ? 'published' : 'validated', 'total' => $s->printed_total, 'source' => $file, 'review' => $review];
    }
    $afterHash = hash('sha256', Statement::whereIn('id', $beforeIds)->with(['sections', 'lines', 'rows.cells', 'documents'])->orderBy('id')->get()->toJson());
    if ($beforeHash !== $afterHash) {
        throw new RuntimeException('Pre-existing statements changed; rolling back.');
    }
    if ($publish) {
        DB::commit();
    } else {
        DB::rollBack();
    }
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
$receipt = ['mode' => $publish ? 'published' : 'dry_run', 'bundle_sha256' => $bundleHash, 'existing_statements_unchanged' => count($beforeIds), 'records' => $results];
$receiptPath = dirname($argv[1]).'/'.($publish ? 'import-receipt.json' : 'dry-run-receipt.json');
file_put_contents($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['mode' => $receipt['mode'], 'periods' => count($results), 'existing_unchanged' => count($beforeIds), 'receipt' => $receiptPath], JSON_PRETTY_PRINT)."\n";
