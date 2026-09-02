<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_all_published_certification_threads_without_enrollment(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $author = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()
            ->for($author, 'user')
            ->for($certification)
            ->create(['title' => '未受講資格の公開質問']);

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertSee('未受講資格の公開質問');

        $this->actingAs($student)
            ->get(route('qa-board.show', $thread))
            ->assertOk();
    }

    public function test_student_cannot_view_archived_certification_thread(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->archived()
            ->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create(['title' => '公開停止資格の質問']);

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertDontSee('公開停止資格の質問');

        $this->actingAs($student)
            ->get(route('qa-board.show', $thread))
            ->assertForbidden();
    }

    public function test_coach_only_sees_assigned_certification_threads(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $assignedCertification = Certification::factory()
            ->published()
            ->create();

        $unassignedCertification = Certification::factory()
            ->published()
            ->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $assignedCertification->id,
            'user_id' => $coach->id,
        ]);

        $assignedThread = QaThread::factory()
            ->for($student, 'user')
            ->for($assignedCertification)
            ->create(['title' => '担当資格の質問']);

        $unassignedThread = QaThread::factory()
            ->for($student, 'user')
            ->for($unassignedCertification)
            ->create(['title' => '担当外資格の質問']);

        $this->actingAs($coach)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertSee('担当資格の質問')
            ->assertDontSee('担当外資格の質問');

        $this->actingAs($coach)
            ->get(route('qa-board.show', $assignedThread))
            ->assertOk();

        $this->actingAs($coach)
            ->get(route('qa-board.show', $unassignedThread))
            ->assertForbidden();
    }

    public function test_admin_can_view_archived_certification_thread(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->archived()
            ->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create(['title' => '管理者だけが確認できる質問']);

        $this->actingAs($admin)
            ->get(route('admin.qa-board.index'))
            ->assertOk()
            ->assertSee('管理者だけが確認できる質問');

        $this->actingAs($admin)
            ->get(route('admin.qa-board.show', $thread))
            ->assertOk();
    }

    public function test_admin_can_delete_any_thread(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $this->actingAs($admin)
            ->delete(
                route('admin.qa-board.destroy', $thread),
            )
            ->assertRedirect(
                route('admin.qa-board.index'),
            );

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_graduated_student_cannot_access_qa_board(): void
    {
        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertForbidden();
    }

    public function test_index_filters_by_resolved_status(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->open()
            ->create(['title' => '未解決の質問']);

        QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->resolved()
            ->create(['title' => '解決済みの質問']);

        $this->actingAs($student)
            ->get(route('qa-board.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertSee('解決済みの質問')
            ->assertDontSee('未解決の質問');
    }

    public function test_index_filters_by_unresolved_status(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->open()
            ->create(['title' => '未解決の質問']);

        QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->resolved()
            ->create(['title' => '解決済みの質問']);

        $this->actingAs($student)
            ->get(route('qa-board.index', [
                'status' => 'unresolved',
            ]))
            ->assertOk()
            ->assertSee('未解決の質問')
            ->assertDontSee('解決済みの質問');
    }

    public function test_index_filters_by_certification(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certificationA = Certification::factory()
            ->published()
            ->create();

        $certificationB = Certification::factory()
            ->published()
            ->create();

        QaThread::factory()
            ->for($student, 'user')
            ->for($certificationA)
            ->create(['title' => '資格Aの質問']);

        QaThread::factory()
            ->for($student, 'user')
            ->for($certificationB)
            ->create(['title' => '資格Bの質問']);

        $this->actingAs($student)
            ->get(route('qa-board.index', [
                'certification_id' => $certificationA->id,
            ]))
            ->assertOk()
            ->assertSee('資格Aの質問')
            ->assertDontSee('資格Bの質問');
    }

    public function test_index_searches_title_and_body(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create([
                'title' => '二分探索について',
                'body' => 'アルゴリズムの質問です。',
            ]);

        QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create([
                'title' => 'ネットワークについて',
                'body' => 'サブネットマスクを理解できません。',
            ]);

        $this->actingAs($student)
            ->get(route('qa-board.index', ['keyword' => '二分探索']))
            ->assertOk()
            ->assertSee('二分探索について')
            ->assertDontSee('ネットワークについて');

        $this->actingAs($student)
            ->get(route('qa-board.index', ['keyword' => 'サブネットマスク']))
            ->assertOk()
            ->assertSee('ネットワークについて')
            ->assertDontSee('二分探索について');
    }

    public function test_index_is_paginated(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        QaThread::factory()
            ->count(21)
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertViewHas(
                'threads',
                fn ($threads): bool => $threads->total() === 21
                    && $threads->perPage() === 20
                    && $threads->lastPage() === 2,
            );
    }
}
