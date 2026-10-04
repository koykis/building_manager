<?php

namespace Tests\Feature;

use App\Models\Statement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReportsImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
    }

    private function statement($period, $amount)
    {
        $s = Statement::create(['period' => $period, 'status' => 'published', 'published_period' => $period, 'printed_total' => bcadd($amount, '50', 2)]);
        $s->lines()->create(['section_key' => 'common', 'category_id' => 1, 'description' => 'Synthetic', 'amount' => $amount, 'classification' => 'operating']);
        $s->lines()->create(['section_key' => 'owners', 'category_id' => 15, 'description' => 'Reserve', 'amount' => '50', 'classification' => 'reserve']);

        return $s;
    }

    public function test_reserves_gaps_and_zero_denominator(): void
    {
        $this->statement('2025-08', '0');
        $this->statement('2026-08', '100');
        $j = $this->getJson('/api/v1/reports/building?from=2026-08&to=2026-09')->assertOk()->json();
        $this->assertSame('100.0000', $j['totals']['operating']);
        $this->assertSame('50.0000', $j['totals']['reserve']);
        $this->assertSame('100.0000', $j['months'][0]['yoy']['delta']);
        $this->assertNull($j['months'][0]['yoy']['percent']);
        $this->assertFalse($j['months'][1]['available']);
        $this->assertNull($j['months'][1]['totals']);
        $this->assertSame(1, $j['coverage']['published']);
    }

    public function test_apartment_report_cannot_be_switched_by_resident(): void
    {
        $s = $this->statement('2026-08', '100');
        foreach ([1, 2] as $id) {
            $s->rows()->create(['apartment_id' => $id, 'printed_total' => $id === 1 ? '10.00' : '90.00', 'metrics' => []]);
        }$this->actingAs(User::factory()->create(['active' => true, 'role' => 'resident', 'apartment_id' => 1]));
        $this->getJson('/api/v1/reports/my-apartment')->assertOk()->assertJsonCount(1)->assertJsonPath('0.printed_total', '10.00');
        $this->getJson('/api/v1/reports/my-apartment?apartment_id=2')->assertUnprocessable();
        $this->getJson('/api/v1/reports/building?from=2026-08&to=2026-08&apartment_id=2')->assertUnprocessable();
    }

    public function test_recurring_costs_sum_operating_lines_without_capital_reserve_or_drafts(): void
    {
        $statement = $this->statement('2026-08', '100');
        foreach (['operating' => '25.12', 'capital' => '900', 'unclassified' => '30'] as $classification => $amount) {
            $statement->lines()->create(['section_key' => 'common', 'category_id' => 1, 'description' => 'Additional cost', 'amount' => $amount, 'classification' => $classification]);
        }
        $statement->lines()->create(['section_key' => 'common', 'category_id' => 2, 'description' => 'Explicit zero', 'amount' => '0', 'classification' => 'operating']);
        $draft = Statement::create(['period' => '2026-09', 'status' => 'draft', 'printed_total' => '500']);
        $draft->lines()->create(['section_key' => 'common', 'category_id' => 1, 'description' => 'Unpublished cost', 'amount' => '500', 'classification' => 'operating']);

        $this->getJson('/api/v1/reports/building?from=2026-08&to=2026-09')
            ->assertOk()
            ->assertJsonPath('months.0.operating_categories', ['1' => '125.1200', '2' => '0.0000'])
            ->assertJsonPath('months.0.categories.1', '1025.1200')
            ->assertJsonPath('months.1.available', false)
            ->assertJsonPath('months.1.operating_categories', null);
    }

    public function test_reports_identify_split_months_and_calculated_totals(): void
    {
        $split = $this->statement('2023-08', '100');
        $row = $split->rows()->create(['apartment_id' => 1, 'printed_total' => '20.00', 'metrics' => ['allocation_method' => 'estimated_two_month_split', 'source_period' => '2023-07/2023-08', 'boiler_m3' => '1.5']]);
        $row->cells()->create(['column' => 'common', 'raw' => '20.00', 'amount' => '20.00', 'state' => 'value', 'provenance' => 'estimated_two_month_split']);
        $actual = $this->statement('2024-08', '120');
        $actual->rows()->create(['apartment_id' => 1, 'printed_total' => '22.00', 'metrics' => ['row_total_provenance' => 'computed_from_visible_cells']]);

        $this->getJson('/api/v1/reports/building?from=2023-08&to=2024-08')
            ->assertOk()
            ->assertJsonPath('months.0.estimated', true)
            ->assertJsonPath('months.0.source_period', '2023-07/2023-08')
            ->assertJsonPath('months.0.prior_estimated', false)
            ->assertJsonPath('months.12.estimated', false)
            ->assertJsonPath('months.12.prior_estimated', true)
            ->assertJsonPath('months.12.yoy.delta', '20.0000');
        $this->getJson('/api/v1/reports/my-apartment?apartment_id=1')
            ->assertOk()
            ->assertJsonPath('0.estimated', true)
            ->assertJsonPath('0.source_period', '2023-07/2023-08')
            ->assertJsonPath('0.boiler_m3', '1.5')
            ->assertJsonPath('0.cells.0.provenance', 'estimated_two_month_split')
            ->assertJsonPath('0.total_computed', false)
            ->assertJsonPath('1.estimated', false)
            ->assertJsonPath('1.total_computed', true);
    }

    public function test_batch_resume_is_idempotent_and_invalid_rows_are_retained(): void
    {
        $payload = ['period' => '2026-01', 'printed_total' => '0.00', 'reviewed' => true, 'exceptions' => [], 'sections' => [], 'lines' => [], 'rows' => []];
        $f = fopen('php://temp', 'w+');
        fputcsv($f, ['period', 'payload'], ',', '"', '');
        fputcsv($f, ['2026-01', json_encode($payload)], ',', '"', '');
        fputcsv($f, ['2026-02', 'broken json'], ',', '"', '');
        rewind($f);
        $file = UploadedFile::fake()->createWithContent('batch.csv', stream_get_contents($f));
        $r = $this->post('/api/v1/admin/imports', ['file' => $file], ['Accept' => 'application/json'])->assertSuccessful()->json();
        $this->assertSame('imported', $r['items'][0]['status']);
        $this->assertSame('error', $r['items'][1]['status']);
        $this->postJson('/api/v1/admin/imports/'.$r['id'].'/resume')->assertOk();
        $this->assertDatabaseCount('statements', 1);
        $this->assertFalse(Statement::first()->reviewed);
        $this->assertSame('draft', Statement::first()->status);
    }
}
