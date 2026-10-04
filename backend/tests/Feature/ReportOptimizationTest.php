<?php

namespace Tests\Feature;

use App\Models\ApartmentStatementRow;
use App\Models\ExpenseCategory;
use App\Models\ExpenseLine;
use App\Models\ImportBatch;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($admin);

        return $admin;
    }

    private function statement(string $period, string $amount = '100', array $metrics = []): Statement
    {
        $statement = Statement::create(['period' => $period, 'status' => 'published', 'published_period' => $period, 'printed_total' => $amount]);
        $statement->lines()->create(['section_key' => 'common', 'category_id' => 1, 'description' => 'Synthetic cost', 'amount' => $amount, 'classification' => 'operating']);
        $row = $statement->rows()->create(['apartment_id' => 1, 'printed_total' => $amount, 'metrics' => $metrics]);
        $row->cells()->create(['column' => 'common', 'raw' => $amount, 'amount' => $amount, 'state' => 'value']);

        return $statement;
    }

    public function test_building_aggregates_without_hydrating_expense_or_apartment_models_and_reads_only_comparison_windows(): void
    {
        $this->admin();
        $this->statement('2025-01');
        $this->statement('2025-06', '999');
        $statement = $this->statement('2026-01');
        $statement->lines()->create(['section_key' => 'common', 'category_id' => 1, 'description' => 'Second charge', 'amount' => '25.12', 'classification' => 'operating']);
        $statement->rows()->create(['apartment_id' => 2, 'printed_total' => '0', 'metrics' => ['allocation_method' => 'estimated_two_month_split']]);
        $children = $parents = 0;
        ExpenseLine::retrieved(function () use (&$children) {
            $children++;
        });
        ApartmentStatementRow::retrieved(function () use (&$children) {
            $children++;
        });
        Statement::retrieved(function () use (&$parents) {
            $parents++;
        });
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/reports/building?from=2026-01&to=2026-01');

        $response->assertOk()->assertJsonPath('totals.operating', '125.1200')->assertJsonPath('months.0.estimated', true)->assertJsonPath('months.0.yoy.delta', '25.1200');
        $this->assertSame(0, $children);
        $this->assertSame(2, $parents);
        $this->assertCount(3, DB::getQueryLog());
    }

    public function test_coverage_uses_one_query_including_empty_history(): void
    {
        $this->admin();
        DB::enableQueryLog();

        $this->getJson('/api/v1/reports/coverage')->assertOk()->assertExactJson(['first' => null, 'last' => null, 'published' => 0]);

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_summary_series_and_csv_preserve_totals_and_missing_months(): void
    {
        $this->admin();
        $this->statement('2025-01', '0');
        $statement = $this->statement('2026-01', '125.12');
        $statement->lines()->create(['section_key' => 'owners', 'category_id' => 1, 'description' => 'Capital work', 'amount' => '20', 'classification' => 'capital']);
        $range = 'from=2026-01&to=2026-02';

        $this->getJson('/api/v1/reports/building?'.$range.'&view=summary')->assertOk()
            ->assertJsonPath('totals.operating', '125.1200')->assertJsonPath('category_totals.1', '145.1200')->assertJsonMissingPath('months');
        $this->getJson('/api/v1/reports/building?'.$range.'&view=series&category_id=1')->assertOk()
            ->assertJsonPath('labels', ['2026-01', '2026-02'])->assertJsonPath('datasets.0.data', ['145.1200', null])->assertJsonPath('datasets.1.data', ['0.0000', null]);
        $this->getJson('/api/v1/reports/building?'.$range.'&view=series')->assertOk()->assertJsonPath('datasets.0.data', ['125.1200', null]);
        $response = $this->get('/api/v1/reports/building?'.$range.'&format=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('Cache-Control', 'no-store, private');
        $rows = array_map(fn ($row) => str_getcsv($row, ',', '"', ''), explode("\n", trim($response->streamedContent())));
        $this->assertSame(['2026-01', '1', '125.1200', '20.0000', '0.0000', '0.0000', '125.12', '0', '2026-01'], $rows[1]);
        $this->assertSame(['2026-02', '0', '', '', '', '', '', '0', ''], $rows[2]);
    }

    public function test_recurring_statistics_use_monthly_operating_totals_zero_charges_and_estimate_counts(): void
    {
        $this->admin();
        $this->statement('2024-01', '100');
        $statement = $this->statement('2025-01', '200', ['allocation_method' => 'estimated_two_month_split']);
        $statement->lines()->create(['section_key' => 'owners', 'category_id' => 1, 'description' => 'Excluded capital', 'amount' => '900', 'classification' => 'capital']);
        $this->statement('2026-01', '0');
        $statement->lines()->create(['section_key' => 'common', 'category_id' => 2, 'description' => 'One-off', 'amount' => '30', 'classification' => 'operating']);

        $this->getJson('/api/v1/reports/recurring?from=2024-01&to=2026-02')->assertOk()
            ->assertJsonCount(1, 'categories')->assertJsonPath('categories.0.id', 1)
            ->assertJsonPath('statistics.1.months.0', ['month' => 1, 'count' => 3, 'estimated_count' => 1, 'average' => '100.0000', 'min' => '0.0000', 'max' => '200.0000'])
            ->assertJsonPath('statistics.1.months.1.average', null)->assertJsonPath('coverage', ['published' => 3, 'expected' => 26, 'estimated' => 1]);
        $this->getJson('/api/v1/reports/recurring?from=2024-01&to=2026-02&category_id=2')->assertOk()->assertJsonPath('statistics.2.months.0.average', '10.0000');
        $csv = $this->get('/api/v1/reports/recurring?from=2024-01&to=2026-02&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('sample_count,estimated_count', $csv);
        $this->assertStringContainsString('1,100.0000,0.0000,200.0000,3,1', $csv);
    }

    public function test_apartment_summary_filters_in_sql_omits_cells_and_keeps_resident_isolation(): void
    {
        $this->admin();
        $this->statement('2025-01');
        $this->statement('2026-01', '120');
        $resident = User::factory()->create(['role' => 'resident', 'active' => true, 'apartment_id' => 1]);
        $this->actingAs($resident);
        DB::enableQueryLog();

        $this->getJson('/api/v1/reports/my-apartment?from=2026-01&to=2026-01&view=summary&per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.period', '2026-01')->assertJsonPath('data.0.printed_total', '120.00')->assertJsonMissingPath('data.0.cells');

        $this->assertCount(1, DB::getQueryLog());
        $this->getJson('/api/v1/reports/my-apartment?apartment_id=2')->assertUnprocessable();
        $this->getJson('/api/v1/admin/project-candidates')->assertForbidden();
    }

    public function test_lists_support_bounded_pages_and_do_not_send_import_payloads(): void
    {
        $this->admin();
        $this->statement('2025-01');
        $this->statement('2026-01');
        $batch = ImportBatch::create(['name' => 'Example', 'items' => [['period' => '2026-01', 'status' => 'existing', 'payload' => str_repeat('private import data', 1000)]]]);

        $this->getJson('/api/v1/admin/statements?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.period', '2026-01')->assertJsonMissingPath('data.0.exceptions');
        $this->getJson('/api/v1/admin/statements?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.period', '2025-01');
        $this->getJson('/api/v1/admin/statements?per_page=101')->assertUnprocessable();
        DB::enableQueryLog();
        $this->getJson('/api/v1/admin/imports?per_page=1')->assertOk()->assertJsonPath('data.0.items.0.status', 'existing')->assertJsonMissingPath('data.0.items.0.payload');
        $this->assertCount(1, DB::getQueryLog());
        $this->assertStringNotContainsString('"items"', DB::getQueryLog()[0]['query']);
        $this->assertSame(str_repeat('private import data', 1000), $batch->fresh()->items[0]['payload']);
        $batch->update(['items' => [['period' => '2026-01', 'status' => 'error', 'payload' => 'retained', 'error' => 'Invalid source']]]);
        $this->getJson('/api/v1/admin/imports')->assertOk()->assertJsonPath('0.items.0.error', 'Invalid source');
    }

    public function test_candidate_query_excludes_unpublished_and_unusable_costs_in_one_query(): void
    {
        $this->admin();
        $statement = $this->statement('2026-01');
        foreach (['operating', 'reserve', 'unclassified'] as $classification) {
            $statement->lines()->create(['section_key' => 'common', 'category_id' => 7, 'description' => 'Project candidate', 'amount' => '10', 'classification' => $classification]);
        }
        $draft = Statement::create(['period' => '2026-02', 'status' => 'draft', 'printed_total' => '500']);
        $draft->lines()->create(['section_key' => 'owners', 'category_id' => 7, 'description' => 'Draft work', 'amount' => '500', 'classification' => 'capital']);
        DB::enableQueryLog();

        $this->getJson('/api/v1/admin/project-candidates')->assertOk()->assertJsonCount(1)->assertJsonPath('0.period', '2026-01')->assertJsonPath('0.classification', 'operating');

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_import_status_migration_backfills_existing_batches_without_changing_source_payloads(): void
    {
        $migration = require database_path('migrations/2026_10_04_112843_add_item_results_to_import_batches.php');
        $migration->down();
        $items = [['period' => '2026-01', 'status' => 'error', 'error' => 'Invalid source', 'payload' => 'Original content retained']];
        $id = DB::table('import_batches')->insertGetId(['name' => 'Legacy batch', 'items' => json_encode($items)]);

        $migration->up();

        $batch = ImportBatch::findOrFail($id);
        $this->assertSame($items, $batch->items);
        $this->assertSame([['period' => '2026-01', 'status' => 'error', 'error' => 'Invalid source']], $batch->item_results);
    }

    public function test_csv_category_names_remain_literal_spreadsheet_labels(): void
    {
        $this->admin();
        $this->statement('2026-01');
        ExpenseCategory::findOrFail(1)->update(['name_en' => '=1+1']);

        $response = $this->get('/api/v1/reports/recurring?from=2026-01&to=2026-01&category_id=1&format=csv')->assertOk();

        $this->assertStringContainsString("'=1+1", $response->streamedContent());
    }

    public function test_report_options_and_ranges_are_validated_and_recurring_requires_active_authentication(): void
    {
        $admin = $this->admin();
        foreach (['view=arbitrary', 'format=html', 'category_id=9999', 'apartment_id=1', 'group_by=apartment'] as $option) {
            $this->getJson('/api/v1/reports/building?from=2026-01&to=2026-01&'.$option)->assertUnprocessable();
        }
        foreach (['from=2026-13&to=2026-14', 'from=2026-02&to=2026-01', 'from=2000-01&to=2026-01'] as $range) {
            $this->getJson('/api/v1/reports/recurring?'.$range)->assertUnprocessable();
        }
        $admin->update(['active' => false]);
        $this->getJson('/api/v1/reports/recurring?from=2026-01&to=2026-01')->assertForbidden();
        auth()->forgetGuards();
        $this->getJson('/api/v1/reports/recurring?from=2026-01&to=2026-01')->assertUnauthorized();
    }
}
