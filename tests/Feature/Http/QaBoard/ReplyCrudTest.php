<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_reply_to_resolved_thread(): void
    {
        $threadAuthor = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $replyAuthor = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()
            ->for($threadAuthor, 'user')
            ->for($certification)
            ->resolved()
            ->create();

        $this->actingAs($replyAuthor)
            ->post(
                route('qa-board.replies.store', $thread),
                ['body' => '解決済みですが、補足回答します。'],
            )
            ->assertRedirect(
                route('qa-board.show', $thread),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replyAuthor->id,
            'body' => '解決済みですが、補足回答します。',
        ]);
    }

    public function test_assigned_coach_can_reply(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->state([
                'status' => UserStatus::InProgress->value,
            ])
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $this->actingAs($coach)
            ->post(
                route('qa-board.replies.store', $thread),
                ['body' => '担当コーチからの回答です。'],
            )
            ->assertRedirect(
                route('qa-board.show', $thread),
            );

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
        ]);
    }

    public function test_unassigned_coach_cannot_reply(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $this->actingAs($coach)
            ->post(
                route('qa-board.replies.store', $thread),
                ['body' => '担当外コーチの回答です。'],
            )
            ->assertForbidden();

        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_admin_cannot_reply(): void
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
            ->post(
                route('qa-board.replies.store', $thread),
                ['body' => '管理者の回答です。'],
            )
            ->assertForbidden();

        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_reply_body_is_required(): void
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

        $this->actingAs($student)
            ->post(
                route('qa-board.replies.store', $thread),
                ['body' => ''],
            )
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_reply_author_can_update_reply(): void
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
            ->patch(
                route('qa-board.replies.update', [
                    'thread' => $thread,
                    'reply' => $reply,
                ]),
                ['body' => '更新後の回答本文です。'],
            )
            ->assertRedirect(
                route('qa-board.show', $thread),
            );

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新後の回答本文です。',
        ]);
    }

    public function test_other_user_cannot_update_reply(): void
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

        $reply = QaReply::factory()
            ->for($author, 'user')
            ->for($thread, 'thread')
            ->create();

        $this->actingAs($otherStudent)
            ->patch(
                route('qa-board.replies.update', [
                    'thread' => $thread,
                    'reply' => $reply,
                ]),
                ['body' => '不正な更新'],
            )
            ->assertForbidden();

        $this->assertNotSame(
            '不正な更新',
            $reply->fresh()->body,
        );
    }

    public function test_reply_author_can_delete_reply(): void
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
            ->delete(
                route('qa-board.replies.destroy', [
                    'thread' => $thread,
                    'reply' => $reply,
                ]),
            )
            ->assertRedirect(
                route('qa-board.show', $thread),
            );

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_admin_can_delete_any_reply(): void
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
            ->create();

        $reply = QaReply::factory()
            ->for($student, 'user')
            ->for($thread, 'thread')
            ->create();

        $this->actingAs($admin)
            ->delete(
                route('admin.qa-board.replies.destroy', [
                    'thread' => $thread,
                    'reply' => $reply,
                ]),
            )
            ->assertRedirect(
                route('admin.qa-board.show', $thread),
            );

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_reply_from_different_thread_returns_404(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $threadA = QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $threadB = QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $reply = QaReply::factory()
            ->for($student, 'user')
            ->for($threadB, 'thread')
            ->create();

        $this->actingAs($student)
            ->get(
                route('qa-board.replies.edit', [
                    'thread' => $threadA,
                    'reply' => $reply,
                ]),
            )
            ->assertNotFound();
    }
}
