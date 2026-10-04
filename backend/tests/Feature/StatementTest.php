<?php

namespace Tests\Feature;

use App\Models\SourceDocument;
use App\Models\Statement;
use App\Models\User;
use App\Services\Decimal;
use App\Services\StatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
    }

    private function payload(): array
    {
        return ['period' => '2026-08', 'printed_total' => '471.00', 'reviewed' => true, 'exceptions' => [], 'sections' => [['key' => 'common', 'label' => 'Κοινόχρηστα', 'allocation_column' => 'common', 'printed_total' => '280.00'], ['key' => 'lift', 'label' => 'Ασανσέρ', 'allocation_column' => 'lift', 'printed_total' => '45.00'], ['key' => 'boiler', 'label' => 'Boiler', 'allocation_column' => 'boiler', 'printed_total' => '126.00'], ['key' => 'special', 'label' => 'Ειδικές', 'allocation_column' => 'special', 'printed_total' => '20.00']], 'lines' => array_map(fn ($c, $a, $s) => ['section_key' => $s, 'category_id' => $c, 'description' => 'Synthetic expense', 'amount' => $a, 'classification' => 'operating'], [1, 8, 12, 13], ['280.00', '45.00', '126.00', '20.00'], ['common', 'lift', 'boiler', 'special']), 'rows' => [['apartment_id' => 1, 'printed_total' => '471.00', 'metrics' => ['boiler_m3' => '5'], 'cells' => array_map(fn ($c, $a) => ['column' => $c, 'raw' => $a, 'state' => 'value'], ['common', 'lift', 'boiler', 'special'], ['280.00', '45.00', '126.000', '20.00'])]]];
    }

    private function source($id)
    {
        SourceDocument::create(['statement_id' => $id, 'filename' => 'synthetic.jpg', 'path' => 'sources/synthetic.jpg', 'sha256' => str_repeat('b', 64), 'mime' => 'image/jpeg']);
    }

    public function test_locale_decimal_preserves_source_precision(): void
    {
        $this->assertSame('36.1070', Decimal::parse('36,107', 4, 'el'));
        $this->assertSame('1227.00', Decimal::parse('1.227,00', 2, 'el'));
        $this->expectException(ValidationException::class);
        Decimal::parse('1.234', 2);
    }

    public function test_publish_revision_and_stale_write_protection(): void
    {
        $payload = $this->payload();
        $s = $this->postJson('/api/v1/admin/statements', $payload)->assertSuccessful()->json();
        $id = $s['id'];
        $this->postJson("/api/v1/admin/statements/$id/publish", ['version' => 1])->assertUnprocessable();
        $this->source($id);
        $this->getJson("/api/v1/admin/statements/$id/review")->assertOk()->assertJsonPath('can_publish', true);
        $this->putJson("/api/v1/admin/statements/$id", [...$payload, 'version' => 99])->assertConflict();
        $this->postJson("/api/v1/admin/statements/$id/publish", ['version' => 1])->assertOk();
        $this->putJson("/api/v1/admin/statements/$id", [...$payload, 'version' => 2])->assertConflict();
        $revision = $this->postJson("/api/v1/admin/statements/$id/revise")->assertSuccessful()->json();
        $this->assertSame(2, $revision['revision']);
        $this->assertSame('published', Statement::find($id)->status);
        $this->postJson("/api/v1/admin/statements/$id/revise")->assertConflict();
        $this->assertDatabaseCount('source_documents', 2);
    }

    public function test_source_rounding_requires_specific_exception(): void
    {
        $p = $this->payload();
        $p['rows'][0]['cells'][2]['raw'] = '126.001';
        $s = app(StatementService::class)->save($p);
        $this->source($s->id);
        $this->getJson('/api/v1/admin/statements/'.$s->id.'/review')->assertJsonPath('can_publish', false);
        $p['exceptions'] = ['column:boiler' => 'Printed rounding differs by one mill.', 'row:1' => 'Printed total rounds the boiler component.'];
        $s = app(StatementService::class)->save([...$p, 'version' => 1], $s);
        $this->getJson('/api/v1/admin/statements/'.$s->id.'/review')->assertJsonPath('can_publish', true);
    }

    public function test_unknown_cells_and_duplicate_periods_cannot_be_published(): void
    {
        $p = $this->payload();
        $p['rows'][0]['cells'][0]['state'] = 'unreadable';
        $s = app(StatementService::class)->save($p);
        $this->source($s->id);
        $this->postJson('/api/v1/admin/statements/'.$s->id.'/publish', ['version' => 1])->assertUnprocessable();
        $this->postJson('/api/v1/admin/statements', $p)->assertConflict();
    }

    public function test_resident_cannot_read_or_export_statements(): void
    {
        $s = app(StatementService::class)->save($this->payload());
        $this->actingAs(User::factory()->create(['active' => true, 'role' => 'resident', 'apartment_id' => 1]));
        foreach (['', '/'.$s->id, '/'.$s->id.'/export', '/'.$s->id.'/review'] as $suffix) {
            $this->getJson('/api/v1/admin/statements'.$suffix)->assertForbidden();
        }
    }

    public function test_nonzero_source_value_cannot_be_marked_as_blank_or_zero(): void
    {
        $p = $this->payload();
        foreach (['blank', 'zero'] as $state) {
            $p['rows'][0]['cells'][0]['state'] = $state;
            $this->postJson('/api/v1/admin/statements', $p)->assertUnprocessable();
        }
        $this->assertDatabaseCount('statements', 0);
    }
}
