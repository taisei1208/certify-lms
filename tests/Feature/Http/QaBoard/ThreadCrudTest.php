<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreadCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_thread_for_published_certification(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => '学習方法について質問です',
                'body' => 'この分野の学習方法を教えてください。',
            ]);

        $thread = QaThread::query()->firstOrFail();

        $response
            ->assertRedirect(
                route('qa-board.show', $thread),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '学習方法について質問です',
            'body' => 'この分野の学習方法を教えてください。',
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ]);
    }

    public function test_student_cannot_create_thread_for_archived_certification(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->archived()
            ->create();

        $this->actingAs($student)
            ->from(route('qa-board.create'))
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => '公開停止資格への質問',
                'body' => 'この質問は保存されない想定です。',
            ])
            ->assertRedirect(route('qa-board.create'))
            ->assertSessionHasErrors('certification_id');

        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_thread_input_validation(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => null,
                'title' => '',
                'body' => '',
            ])
            ->assertSessionHasErrors([
                'certification_id',
                'title',
                'body',
            ]);

        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_author_can_update_title_and_body_but_not_certification(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $originalCertification = Certification::factory()
            ->published()
            ->create();

        $anotherCertification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($originalCertification)
            ->open()
            ->create();

        $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '更新後のタイトル',
                'body' => '更新後の本文です。',
                'certification_id' => $anotherCertification->id,
            ])
            ->assertRedirect(
                route('qa-board.show', $thread),
            )
            ->assertSessionHas('success');

        $thread->refresh();

        $this->assertSame(
            '更新後のタイトル',
            $thread->title,
        );

        $this->assertSame(
            '更新後の本文です。',
            $thread->body,
        );

        $this->assertSame(
            $originalCertification->id,
            $thread->certification_id,
        );
    }

    public function test_other_student_cannot_update_thread(): void
    {
        $author = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()
            ->for($author, 'user')
            ->for($certification)
            ->create();

        $this->actingAs($otherStudent)
            ->patch(route('qa-board.update', $thread), [
                'title' => '不正な更新',
                'body' => '他人による更新です。',
            ])
            ->assertForbidden();

        $thread->refresh();

        $this->assertNotSame(
            '不正な更新',
            $thread->title,
        );
    }

    public function test_author_can_delete_thread_and_its_replies(): void
    {
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

        $reply = QaReply::factory()
            ->for($student, 'user')
            ->for($thread, 'thread')
            ->create();

        $this->actingAs($student)
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect(route('qa-board.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_other_student_cannot_delete_thread(): void
    {
        $author = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()
            ->for($author, 'user')
            ->for($certification)
            ->create();

        $this->actingAs($otherStudent)
            ->delete(route('qa-board.destroy', $thread))
            ->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_author_can_resolve_thread_without_replies(): void
    {
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
            ->open()
            ->create();

        $this->assertDatabaseCount('qa_replies', 0);

        $this->actingAs($student)
            ->post(route('qa-board.resolve', $thread))
            ->assertRedirect(
                route('qa-board.show', $thread),
            )
            ->assertSessionHas('success');

        $thread->refresh();

        $this->assertSame(
            QaThreadStatus::Resolved,
            $thread->status,
        );

        $this->assertNotNull($thread->resolved_at);
    }

    public function test_author_can_unresolve_thread(): void
    {
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
            ->resolved()
            ->create();

        $this->actingAs($student)
            ->post(route('qa-board.unresolve', $thread))
            ->assertRedirect(
                route('qa-board.show', $thread),
            )
            ->assertSessionHas('success');

        $thread->refresh();

        $this->assertSame(
            QaThreadStatus::Open,
            $thread->status,
        );

        $this->assertNull($thread->resolved_at);
    }

    public function test_other_student_cannot_change_resolved_status(): void
    {
        $author = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()
            ->for($author, 'user')
            ->for($certification)
            ->open()
            ->create();

        $this->actingAs($otherStudent)
            ->post(route('qa-board.resolve', $thread))
            ->assertForbidden();

        $this->assertSame(
            QaThreadStatus::Open,
            $thread->fresh()->status,
        );
    }
}
