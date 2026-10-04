<?php

namespace App\Console\Commands;

use App\Models\Statement;
use App\Services\StatementService;
use Illuminate\Console\Command;

class CorrectSourceLabels extends Command
{
    protected $signature = 'app:correct-source-labels';

    protected $description = 'Revise pilot normalized headings to their original Greek source headings';

    public function handle(StatementService $service): int
    {
        $labels = ['common' => 'ΚΟΙΝΟΧΡΗΣΤΑ', 'lift' => 'ΑΣΑΝΣΕΡ', 'heating' => 'ΘΕΡΜΑΝΣΗ', 'individual' => 'ΑΥΤΟΝΟΜΙΑ', 'boiler' => 'BOILER', 'special' => 'ΕΙΔΙΚΕΣ ΔΑΠΑΝΕΣ', 'owners' => 'ΙΔΙΟΚΤΗΤΩΝ'];
        foreach (Statement::where('status', 'published')->with('sections')->get() as $s) {
            if (! $s->sections->contains(fn ($sec) => $sec->label === $sec->key)) {
                continue;
            }$revision = $service->revise($s, null);
            $data = $revision->load(['sections', 'lines', 'rows.cells'])->toArray();
            foreach ($data['sections'] as &$section) {
                $section['label'] = $labels[$section['key']] ?? $section['label'];
            }unset($section);
            $data['reviewed'] = true;
            $data['exceptions'] = $s->exceptions ?? [];
            $data['notes'] = ($data['notes'] ?? '').' Source headings corrected; monetary values unchanged.';
            $revision = $service->save($data, $revision, null);
            $service->publish($revision, $revision->version, null);
            $this->line($s->period.' headings corrected via revision '.$revision->revision);
        }

        return 0;
    }
}
