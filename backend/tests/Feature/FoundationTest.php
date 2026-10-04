<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\SourceDocument;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_direct_file_and_api_requests_return_401_without_accept_header(): void
    {
        $this->get('/api/v1/admin/statements')->assertUnauthorized();
        $this->get('/api/v1/admin/documents/1')->assertUnauthorized();
    }

    public function test_reference_seed_creates_nine_units_and_no_accounts(): void
    {
        $this->seed();
        $this->assertDatabaseCount('apartments', 9);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame('ΣΤ1', Apartment::where('alias', 'F1')->first()->label);
        $this->assertTrue(Apartment::where('alias', 'B1')->first()->car_lift);
        $this->assertFalse(Apartment::where('alias', 'B2')->first()->car_lift);
    }

    public function test_only_active_admin_can_manage_references(): void
    {
        $this->seed();
        $this->getJson('/api/v1/admin/references')->assertUnauthorized();
        $resident = User::factory()->create(['active' => true, 'role' => 'resident', 'apartment_id' => 1]);
        $this->actingAs($resident)->getJson('/api/v1/admin/references')->assertForbidden();
        $admin = User::factory()->create(['active' => true, 'role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/v1/admin/references')->assertOk()->assertJsonCount(9, 'apartments');
        $this->putJson('/api/v1/admin/apartments/1', ['label' => 'Α1', 'alias' => 'A1', 'floor' => 1, 'area' => null, 'car_lift' => true, 'active' => false])->assertOk();
        $admin->update(['active' => false]);
        $this->getJson('/api/v1/admin/references')->assertForbidden();
    }

    public function test_documents_are_private_even_when_id_is_known(): void
    {
        Storage::fake('local');
        $s = Statement::create(['period' => '2026-08', 'printed_total' => '471.00']);
        Storage::disk('local')->put('sources/private.txt', 'private data');
        $d = SourceDocument::create(['statement_id' => $s->id, 'filename' => 'sheet.txt', 'path' => 'sources/private.txt', 'sha256' => str_repeat('a', 64), 'mime' => 'text/plain']);
        $resident = User::factory()->create(['active' => true, 'role' => 'resident']);
        $this->actingAs($resident)->getJson('/api/v1/admin/documents/'.$d->id)->assertForbidden();
        $admin = User::factory()->create(['active' => true, 'role' => 'admin']);
        $this->actingAs($admin)->get('/api/v1/admin/documents/'.$d->id)->assertOk();
    }

    public function test_inactive_account_cannot_login(): void
    {
        $u = User::factory()->create(['password' => 'long-test-password', 'active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'long-test-password'])->assertUnprocessable();
        $u->update(['active' => true]);
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'long-test-password'])->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
    }
}
