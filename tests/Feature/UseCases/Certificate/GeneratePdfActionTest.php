<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certificate;

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Certificate\GeneratePdfAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeneratePdfActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_certificate_pdf_on_private_disk(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create([
            'name' => '山岸 治',
        ]);

        $certification = Certification::factory()->published()->create([
            'name' => 'TOEIC L&R 800点コース',
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create([
            'status' => EnrollmentStatus::Passed->value,
            'passed_at' => now()->subDay(),
        ]);

        $certificate = Certificate::factory()->forEnrollment($enrollment)
            ->create([
                'pdf_path' => 'certificates/'.Str::ulid().'.pdf',
                'issued_at' => now(),
            ]);

        app(GeneratePdfAction::class)($certificate);

        Storage::disk('private')->assertExists(
            $certificate->pdf_path,
        );

        $contents = Storage::disk('private')->get(
            $certificate->pdf_path,
        );

        $this->assertStringStartsWith(
            '%PDF',
            $contents,
        );
    }
}
