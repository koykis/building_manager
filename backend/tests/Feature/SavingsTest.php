<?php

namespace Tests\Feature;

use App\Models\CapitalProject;
use App\Models\Statement;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SavingsTest extends TestCase
{
    use RefreshDatabase;

    private function month($period, $amount)
    {
        $s = Statement::create(['period' => $period, 'status' => 'published', 'published_period' => $period, 'printed_total' => $amount]);
        $s->lines()->create(['section_key' => 'common', 'category_id' => 1, 'description' => 'Synthetic', 'amount' => $amount, 'classification' => 'operating']);

        return $s;
    }

    private function project()
    {
        $this->seed();
        $b = $this->month('2025-01', '100');
        $cost = $b->lines()->create(['section_key' => 'common', 'category_id' => 7, 'description' => 'Synthetic improvement', 'amount' => '20', 'classification' => 'capital']);
        $p = CapitalProject::create(['title' => 'Synthetic project', 'completion_date' => '2025-12-15', 'target_categories' => [1], 'baseline' => ['01' => '100.00', '02' => '100.00', '03' => '100.00'], 'baseline_periods' => ['2025-01'], 'baseline_notes' => 'Synthetic fixed baseline for test']);
        $p->costLines()->attach($cost);

        return $p;
    }

    public function test_negative_savings_reduce_cumulative_and_can_reverse_payback(): void
    {
        $p = $this->project();
        $this->month('2026-01', '50');
        $this->month('2026-02', '150');
        $result = app(SavingsService::class)->calculate($p, '2026-02');
        $this->assertTrue($result['available']);
        $this->assertSame('-50.0000', $result['months'][1]['savings']);
        $this->assertSame('0.0000', $result['cumulative']);
        $this->assertSame('2026-01', $result['first_break_even']);
        $this->assertFalse($result['currently_covered']);
    }

    public function test_missing_month_invalidates_total_instead_of_creating_savings(): void
    {
        $p = $this->project();
        $this->month('2026-01', '50');
        $this->month('2026-03', '50');
        $r = app(SavingsService::class)->calculate($p, '2026-03');
        $this->assertFalse($r['available']);
        $this->assertContains('actual_missing:2026-02', $r['reasons']);
        $this->assertArrayNotHasKey('cumulative', $r);
    }

    public function test_missing_dates_and_overlapping_categories_block_attribution(): void
    {
        $p = $this->project();
        $q = $p->replicate();
        $q->title = 'Other';
        $q->save();
        $r = app(SavingsService::class)->calculate($p, '2026-02');
        $this->assertContains('overlapping_project_categories', $r['reasons']);
        $p->completion_date = null;
        $p->save();
        $this->assertContains('completion_date_required', app(SavingsService::class)->calculate($p, '2026-02')['reasons']);
    }

    public function test_cost_and_baseline_validation_query_count_stays_constant_as_evidence_grows(): void
    {
        $project = $this->project();
        $periods = ['2025-01'];
        for ($month = 2; $month <= 11; $month++) {
            $period = sprintf('2025-%02d', $month);
            $this->month($period, '100');
            $periods[] = $period;
        }
        $source = Statement::where('period', '2025-01')->first();
        for ($line = 0; $line < 20; $line++) {
            $cost = $source->lines()->create(['section_key' => 'owners', 'category_id' => 7, 'description' => 'Additional documented work', 'amount' => '1', 'classification' => 'capital']);
            $project->costLines()->attach($cost);
        }
        $project->update(['baseline_periods' => $periods]);
        $this->month('2026-01', '50');
        DB::enableQueryLog();

        $result = app(SavingsService::class)->calculate($project, '2026-01');

        $this->assertTrue($result['available']);
        $this->assertSame('40.0000', $result['cost']);
        $this->assertLessThanOrEqual(5, count(DB::getQueryLog()));
    }
}
