<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_audit_trail(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        AuditEvent::query()->create([
            'actor_user_id' => $user->id,
            'module_code' => 'access',
            'event_type' => 'roles.create',
            'entity_type' => 'role',
            'entity_id' => '1',
            'description' => 'Perfil criado.',
            'source_ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => ['name' => 'Auditor'],
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $user->id,
            'module_code' => 'produtividade',
            'event_type' => 'flagrantes.import',
            'entity_type' => 'import_batch',
            'entity_id' => '2',
            'description' => 'Importacao concluida.',
            'source_ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => ['rows' => 5],
        ]);

        $response = $this->actingAs($user)->get('/auditoria');

        $response->assertOk();
        $response->assertSee('Auditoria operacional');
        $response->assertSee('roles.create');
        $response->assertSee('flagrantes.import');
    }

    public function test_audit_trail_filters_by_module_code(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        AuditEvent::query()->create([
            'actor_user_id' => $user->id,
            'module_code' => 'access',
            'event_type' => 'users.create',
            'entity_type' => 'user',
            'entity_id' => '1',
            'description' => 'Usuario criado.',
            'source_ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => null,
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $user->id,
            'module_code' => 'analise',
            'event_type' => 'batch.export',
            'entity_type' => 'import_batch',
            'entity_id' => '2',
            'description' => 'Exportacao de lote.',
            'source_ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => null,
        ]);

        $response = $this->actingAs($user)->get('/auditoria?module_code=access');

        $response->assertOk();
        $response->assertSee('users.create');
        $response->assertDontSee('batch.export');
    }

    public function test_audit_trail_filters_by_username_and_exports_csv(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $otherUser = User::factory()->create([
            'username' => 'auditor.demo',
            'name' => 'Auditor Demo',
            'email' => 'auditor.demo@grom.local',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $user->id,
            'module_code' => 'access',
            'event_type' => 'users.update',
            'entity_type' => 'user',
            'entity_id' => '1',
            'description' => 'Usuario atualizado.',
            'source_ip' => '10.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => null,
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $otherUser->id,
            'module_code' => 'analise',
            'event_type' => 'batch.export',
            'entity_type' => 'import_batch',
            'entity_id' => '2',
            'description' => 'Exportacao de lote.',
            'source_ip' => '10.0.0.2',
            'user_agent' => 'PHPUnit',
            'metadata' => ['rows' => 12],
        ]);

        $response = $this->actingAs($user)->get('/auditoria?actor_username=auditor.demo');

        $response->assertOk();
        $response->assertSee('auditor.demo');
        $response->assertDontSee('users.update');

        $exportResponse = $this->actingAs($user)->get('/auditoria/exportar?actor_username=auditor.demo');

        $exportResponse->assertOk();
        $exportResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $exportResponse->assertSee('Auditoria do Grom.Seg');
        $exportResponse->assertSee('auditor.demo');
        $exportResponse->assertSee('batch.export');
    }

    public function test_user_without_permission_is_forbidden_from_audit_trail(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/auditoria');

        $response->assertForbidden();
    }
}

