<?php

namespace Tests\Feature;

use App\InfrastructureEnvironment;
use App\Models\AdmissionApplication;
use App\Models\AdmissionDocument;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uploads_store_the_selected_private_disk_and_downloads_use_recorded_locations(): void
    {
        Storage::fake('garage');
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'garage']);
        $owner = User::factory()->create();
        $app = AdmissionApplication::factory()->create(['created_by' => $owner->id]);
        $this->actingAs($owner, 'sanctum');
        $id = $this->postJson("/api/admissions/$app->id/documents", ['file' => UploadedFile::fake()->create('proof.pdf', 4, 'application/pdf')])->assertCreated()->json('data.id');
        $doc = AdmissionDocument::findOrFail($id);
        $this->assertSame('garage', $doc->disk);
        Storage::disk('garage')->assertExists($doc->path);
        Storage::disk('local')->assertMissing($doc->path);
        config(['filesystems.documents_disk' => 'local']);
        $this->get("/api/admissions/$app->id/documents/$id")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $old = AdmissionDocument::factory()->create(['admission_application_id' => $app->id, 'disk' => 'local']);
        Storage::disk('local')->put($old->path, 'local-evidence');
        config(['filesystems.documents_disk' => 'garage']);
        $this->get("/api/admissions/$app->id/documents/$old->id")->assertOk()->assertStreamedContent('local-evidence');
        $this->getJson("/api/admissions/$app->id")->assertJsonMissingPath('data.documents.0.path')->assertJsonMissingPath('data.documents.0.disk');
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/admissions/$app->id/documents/$id")->assertNotFound();
        $this->assertStringNotContainsString($doc->path, AuditLog::latest('id')->first()->toJson());
    }

    public function test_local_setup_generates_private_secrets_once_and_preserves_existing_configuration(): void
    {
        $root = sys_get_temp_dir().'/ems-infra-test-'.bin2hex(random_bytes(8));
        mkdir($root, 0700);
        try {
            file_put_contents($root.'/compose.yaml', "services: {}\n");
            file_put_contents($root.'/.env.example', "APP_KEY=\nDB_PASSWORD=\nREDIS_PASSWORD=\nJWT_SECRET=\nGARAGE_RPC_SECRET=\nGARAGE_ACCESS_KEY=\nGARAGE_SECRET_KEY=\nGARAGE_ADMIN_TOKEN=\nEXISTING_SETTING=keep-me\n");
            InfrastructureEnvironment::prepare($root);
            $first = file_get_contents($root.'/.env');
            $this->assertStringContainsString('EXISTING_SETTING=keep-me', $first);
            foreach (['APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD', 'JWT_SECRET', 'GARAGE_RPC_SECRET', 'GARAGE_ACCESS_KEY', 'GARAGE_SECRET_KEY', 'GARAGE_ADMIN_TOKEN'] as $key) {
                preg_match('/^'.$key.'=(.+)$/m', $first, $match);
                $this->assertGreaterThanOrEqual(32, strlen($match[1] ?? ''));
            }
            InfrastructureEnvironment::prepare($root);
            $this->assertSame($first, file_get_contents($root.'/.env'));
            if (PHP_OS_FAMILY !== 'Windows') {
                $this->assertSame(0600, fileperms($root.'/.env') & 0777);
            }
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_invalid_document_storage_configuration_is_rejected_before_writing_a_file(): void
    {
        config(['filesystems.documents_disk' => 'public']);
        $owner = User::factory()->create();
        $app = AdmissionApplication::factory()->create(['created_by' => $owner->id]);
        $this->actingAs($owner, 'sanctum')->postJson("/api/admissions/$app->id/documents", ['file' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')])->assertServiceUnavailable();
        $this->assertDatabaseCount('admission_documents', 0);
    }
}
