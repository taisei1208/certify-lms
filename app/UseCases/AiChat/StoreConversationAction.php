<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class StoreConversationAction
{
    /**
     * @param array{
     *     source: string,
     *     section_id?: string|null,
     *     message?: string|null
     * } $validated
     */
    public function __invoke(User $student, array $validated): AiChatConversation
    {
        $sectionId = $validated['section_id'] ?? null;

        if ($sectionId !== null) {
            return $this->forSection($student, $sectionId);
        }

        $enrollment = $this->resolveEnrollment($student);

        return AiChatConversation::query()->create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment?->id,
            'section_id' => null,
            'title' => '新しい相談',
            'auto_title_enabled' => config('ai-chat.auto_title_enabled'),
            'last_message_at' => now(),
        ]);
    }

    /**
     * 同じSectionの会話があれば再利用する。
     */
    private function forSection(User $student, string $sectionId): AiChatConversation
    {
        $section = Section::query()->published()->with('chapter.part')->findOrFail($sectionId);

        $certificationId = $section->chapter->part->certification_id;

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('certification_id', $certificationId)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->first();

        if ($enrollment === null) {
            throw new AuthorizationException(
                'この教材のAI相談は利用できません。',
            );
        }

        return AiChatConversation::query()
            ->firstOrCreate(
                [
                    'user_id' => $student->id,
                    'section_id' => $section->id,
                ],
                [
                    'enrollment_id' => $enrollment->id,
                    'title' => $section->title,
                    'auto_title_enabled' => config('ai-chat.auto_title_enabled'),
                    'last_message_at' => now(),
                ],
            );
    }

    private function resolveEnrollment(User $student): ?Enrollment
    {
        $default = $student->defaultEnrollment;

        if (
            $default !== null && in_array($default->status, [
                EnrollmentStatus::Learning,
                EnrollmentStatus::Passed,
            ], true)
        ) {
            return $default;
        }

        return Enrollment::query()
            ->where('user_id', $student->id)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->latest('updated_at')
            ->first();
    }
}
