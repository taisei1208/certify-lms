<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($student === null) {
            $this->command?->warn(
                'AiChatSeeder: 固定受講生が存在しません。',
            );

            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->first();

        $this->seedGeneralConversation($student, $enrollment);

        $this->seedErrorConversation($student, $enrollment);

        if ($enrollment === null) {
            return;
        }

        $section = Section::query()->published()
            ->whereHas('chapter.part',
                fn ($query) => $query->where(
                    'certification_id', $enrollment->certification_id)
            )->first();

        if ($section !== null) {
            $this->seedSectionConversation($student, $enrollment, $section);
        }
    }

    private function seedGeneralConversation(User $student, ?Enrollment $enrollment): void
    {
        $conversation = AiChatConversation::query()
            ->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'section_id' => null,
                    'title' => '試験勉強の進め方',
                ],
                [
                    'enrollment_id' => $enrollment?->id,
                    'auto_title_enabled' => false,
                    'last_message_at' => now()->subDays(1),
                ],
            );

        $conversation->messages()->delete();

        $this->createMessage(
            $conversation,
            AiChatMessageRole::User,
            AiChatMessageStatus::Completed,
            '試験日までの学習計画を立てるコツを教えてください。',
            now()->subDays(1)->subMinutes(2),
        );

        $this->createMessage(
            $conversation,
            AiChatMessageRole::Assistant,
            AiChatMessageStatus::Completed,
            '試験日から逆算し、学習範囲を週単位に分ける方法がおすすめです。復習日もあらかじめ確保しましょう。',
            now()->subDays(1),
        );
    }

    private function seedErrorConversation(User $student, ?Enrollment $enrollment): void
    {
        $conversation = AiChatConversation::query()
            ->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'section_id' => null,
                    'title' => '苦手分野について',
                ],
                [
                    'enrollment_id' => $enrollment?->id,
                    'auto_title_enabled' => false,
                    'last_message_at' => now()->subHours(2),
                ]
            );

        $conversation->messages()->delete();

        $this->createMessage(
            $conversation,
            AiChatMessageRole::User,
            AiChatMessageStatus::Completed,
            'この分野を覚えるコツはありますか。',
            now()->subHours(2)->subMinute(),
        );

        $this->createMessage(
            $conversation,
            AiChatMessageRole::Assistant,
            AiChatMessageStatus::Error,
            '',
            now()->subHours(2),
        );
    }

    private function seedSectionConversation(User $student, Enrollment $enrollment, Section $section): void
    {
        $conversation = AiChatConversation::query()
            ->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'section_id' => $section->id,
                ],
                [
                    'enrollment_id' => $enrollment->id,
                    'title' => $section->title,
                    'auto_title_enabled' => true,
                    'last_message_at' => now()->subMinutes(30),
                ]
            );

        $conversation->messages()->delete();

        $this->createMessage(
            $conversation,
            AiChatMessageRole::User,
            AiChatMessageStatus::Completed,
            'このSectionの重要なポイントを整理してください。',
            now()->subMinutes(31),
        );

        $this->createMessage(
            $conversation,
            AiChatMessageRole::Assistant,
            AiChatMessageStatus::Completed,
            'まず用語の意味を整理し、例題を使って考え方を確認すると理解しやすくなります。',
            now()->subMinutes(30),
        );
    }

    private function createMessage(
        AiChatConversation $conversation,
        AiChatMessageRole $role,
        AiChatMessageStatus $status,
        string $content,
        mixed $createdAt,
    ): void {
        $conversation->messages()->create([
            'role' => $role,
            'status' => $status,
            'content' => $content,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
