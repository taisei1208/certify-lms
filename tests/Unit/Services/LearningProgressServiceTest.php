<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\ProgressSummary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private LearningProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LearningProgressService::class);
    }

    public function test_returns_zero_when_no_content_exists(): void
    {
        $enrollment = Enrollment::factory()->create();

        $summary = $this->service->summarizeProgress($enrollment);

        $this->assertSummary(
            $summary,
            sectionsTotal: 0,
            sectionsCompleted: 0,
            sectionRatio: 0.0,
            chaptersTotal: 0,
            chaptersCompleted: 0,
            chapterRatio: 0.0,
            partsTotal: 0,
            partsCompleted: 0,
            partRatio: 0.0,
        );

        $this->assertSame(
            [$enrollment->id => 0.0],
            $this->service->batchCalculateProgress(
                new Collection([$enrollment]),
            ),
        );
    }

    public function test_calculates_partial_completion_for_each_level(): void
    {
        $enrollment = Enrollment::factory()->create();
        $sections = $this->createPublishedTree($enrollment);

        // Part Aの2つのChapterを完了。Part Bは未読。
        $this->markRead($enrollment, $sections[0]);
        $this->markRead($enrollment, $sections[1]);

        $summary = $this->service->summarizeProgress($enrollment);

        $this->assertSummary(
            $summary,
            sectionsTotal: 3,
            sectionsCompleted: 2,
            sectionRatio: 0.6667,
            chaptersTotal: 3,
            chaptersCompleted: 2,
            chapterRatio: 0.6667,
            partsTotal: 2,
            partsCompleted: 1,
            partRatio: 0.5,
        );

        $ratios = $this->service->batchCalculateProgress(
            new Collection([$enrollment]),
        );

        $this->assertSame(0.6667, $ratios[$enrollment->id]);
        $this->assertSame(
            $summary->overallCompletionRatio,
            $ratios[$enrollment->id],
        );
    }

    public function test_chapter_and_part_require_all_sections_to_be_read(): void
    {
        $enrollment = Enrollment::factory()->create();

        $part = Part::factory()
            ->published()
            ->forCertification($enrollment->certification)
            ->create();

        $chapter = Chapter::factory()
            ->published()
            ->forPart($part)
            ->create();

        $sections = Section::factory()
            ->count(2)
            ->published()
            ->forChapter($chapter)
            ->create();

        $this->markRead($enrollment, $sections[0]);

        $summary = $this->service->summarizeProgress($enrollment);

        $this->assertSummary(
            $summary,
            sectionsTotal: 2,
            sectionsCompleted: 1,
            sectionRatio: 0.5,
            chaptersTotal: 1,
            chaptersCompleted: 0,
            chapterRatio: 0.0,
            partsTotal: 1,
            partsCompleted: 0,
            partRatio: 0.0,
        );
    }

    public function test_excludes_content_when_any_parent_level_is_draft(): void
    {
        $enrollment = Enrollment::factory()->create();
        $sections = $this->createPublishedTree($enrollment);

        // Part A全体を非公開にする。
        // その配下のChapter・Sectionも集計対象から外れる。
        $sections[0]->chapter->part->update([
            'status' => ContentStatus::Draft->value,
        ]);

        // Part B配下に非公開Chapterと公開Sectionを追加する。
        $visiblePart = $sections[2]->chapter->part;

        $draftChapter = Chapter::factory()
            ->draft()
            ->forPart($visiblePart)
            ->create();

        $sectionUnderDraftChapter = Section::factory()
            ->published()
            ->forChapter($draftChapter)
            ->create();

        // 公開Chapter配下に非公開Sectionを追加する。
        $draftSection = Section::factory()
            ->draft()
            ->forChapter($sections[2]->chapter)
            ->create();

        // 非公開教材にも読了記録を入れ、
        // 分母・分子の両方から除外されることを検証する。
        foreach ($sections as $section) {
            $this->markRead($enrollment, $section);
        }

        $this->markRead($enrollment, $sectionUnderDraftChapter);
        $this->markRead($enrollment, $draftSection);

        $summary = $this->service->summarizeProgress($enrollment);

        $this->assertSummary(
            $summary,
            sectionsTotal: 1,
            sectionsCompleted: 1,
            sectionRatio: 1.0,
            chaptersTotal: 1,
            chaptersCompleted: 1,
            chapterRatio: 1.0,
            partsTotal: 1,
            partsCompleted: 1,
            partRatio: 1.0,
        );

        $this->assertSame(
            [$enrollment->id => 1.0],
            $this->service->batchCalculateProgress(
                new Collection([$enrollment]),
            ),
        );
    }

    public function test_progress_is_separated_by_enrollment_and_certification(): void
    {
        $first = Enrollment::factory()->create();
        $firstSections = $this->createPublishedTree($first);

        // 同じ資格を受講する別ユーザー。
        $second = Enrollment::factory()->create([
            'certification_id' => $first->certification_id,
        ]);

        // 別資格を受講するユーザー。
        $third = Enrollment::factory()->create();
        $thirdSections = $this->createPublishedTree($third);

        $this->markRead($first, $firstSections[0]);

        foreach ($firstSections as $section) {
            $this->markRead($second, $section);
        }

        $this->markRead($third, $thirdSections[0]);
        $this->markRead($third, $thirdSections[1]);

        $enrollments = new Collection([$first, $second, $third]);

        $ratios = $this->service->batchCalculateProgress($enrollments);

        $this->assertSame([
            $first->id => 0.3333,
            $second->id => 1.0,
            $third->id => 0.6667,
        ], $ratios);

        foreach ($enrollments as $enrollment) {
            $summary = $this->service->summarizeProgress($enrollment);

            // 別資格のSectionが総数へ混ざらない。
            $this->assertSame(3, $summary->sectionsTotal);

            $this->assertSame(
                $ratios[$enrollment->id],
                $summary->overallCompletionRatio,
            );
        }

        $this->assertSame(
            1,
            $this->service->summarizeProgress($first)->sectionsCompleted,
        );
    }

    /**
     * Part A
     *   Chapter A1 → Section 1
     *   Chapter A2 → Section 2
     * Part B
     *   Chapter B1 → Section 3
     *
     * @return array{0: Section, 1: Section, 2: Section}
     */
    private function createPublishedTree(Enrollment $enrollment): array
    {
        $partA = Part::factory()
            ->published()
            ->forCertification($enrollment->certification)
            ->create(['order' => 1]);

        $partB = Part::factory()
            ->published()
            ->forCertification($enrollment->certification)
            ->create(['order' => 2]);

        $chapterA1 = Chapter::factory()
            ->published()
            ->forPart($partA)
            ->create(['order' => 1]);

        $chapterA2 = Chapter::factory()
            ->published()
            ->forPart($partA)
            ->create(['order' => 2]);

        $chapterB1 = Chapter::factory()
            ->published()
            ->forPart($partB)
            ->create(['order' => 1]);

        return [
            Section::factory()
                ->published()
                ->forChapter($chapterA1)
                ->create(['order' => 1]),
            Section::factory()
                ->published()
                ->forChapter($chapterA2)
                ->create(['order' => 1]),
            Section::factory()
                ->published()
                ->forChapter($chapterB1)
                ->create(['order' => 1]),
        ];
    }

    private function markRead(
        Enrollment $enrollment,
        Section $section,
    ): void {
        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($section)
            ->create();
    }

    private function assertSummary(
        ProgressSummary $summary,
        int $sectionsTotal,
        int $sectionsCompleted,
        float $sectionRatio,
        int $chaptersTotal,
        int $chaptersCompleted,
        float $chapterRatio,
        int $partsTotal,
        int $partsCompleted,
        float $partRatio,
    ): void {
        $this->assertSame($sectionsTotal, $summary->sectionsTotal);
        $this->assertSame($sectionsCompleted, $summary->sectionsCompleted);
        $this->assertSame($sectionRatio, $summary->sectionCompletionRatio);

        $this->assertSame($chaptersTotal, $summary->chaptersTotal);
        $this->assertSame($chaptersCompleted, $summary->chaptersCompleted);
        $this->assertSame($chapterRatio, $summary->chapterCompletionRatio);

        $this->assertSame($partsTotal, $summary->partsTotal);
        $this->assertSame($partsCompleted, $summary->partsCompleted);
        $this->assertSame($partRatio, $summary->partCompletionRatio);

        // 資格全体の完了率はSection単位の完了率と同じ。
        $this->assertSame($sectionRatio, $summary->overallCompletionRatio);
    }
}
