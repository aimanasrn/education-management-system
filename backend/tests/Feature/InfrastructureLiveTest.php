<?php

namespace Tests\Feature;

use App\Models\AdmissionApplication;
use App\Models\AdmissionDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InfrastructureLiveTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_LIVE_INFRA') !== '1') {
            $this->markTestSkipped('Live Redis/Garage tests are opt-in.');
        }
        $connection = config('database.default');
        if (! str_contains((string) config('database.connections.'.$connection.'.database'), '_qa_')) {
            throw new \RuntimeException('Use an isolated QA database for live infrastructure tests.');
        }
    }

    public function test_redis_garage_privacy_and_the_running_queue_worker(): void
    {
        $this->artisan('ems:check-infrastructure', ['--queue' => true])->assertExitCode(0);
    }

    public function test_real_garage_admission_upload_download_is_private_and_authorized(): void
    {
        config(['filesystems.documents_disk' => 'garage']);
        $owner = User::factory()->create();
        $app = AdmissionApplication::factory()->create(['created_by' => $owner->id]);
        $pdf = "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF";
        $document = null;
        try {
            $id = $this->actingAs($owner, 'sanctum')->postJson("/api/admissions/$app->id/documents", ['file' => UploadedFile::fake()->createWithContent('evidence.pdf', $pdf)])->assertCreated()->json('data.id');
            $document = AdmissionDocument::findOrFail($id);
            $this->assertSame('garage', $document->disk);
            $this->assertSame($pdf, Storage::disk('garage')->get($document->path));
            $url = rtrim(config('filesystems.disks.garage.endpoint'), '/').'/'.config('filesystems.disks.garage.bucket').'/'.$document->path;
            $this->assertSame(403, Http::timeout(5)->get($url)->status());
            $this->get("/api/admissions/$app->id/documents/$id")->assertOk()->assertStreamedContent($pdf);
            $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/admissions/$app->id/documents/$id")->assertNotFound();
        } finally {
            if ($document) {
                Storage::disk('garage')->delete($document->path);
            }
        }
    }
}
