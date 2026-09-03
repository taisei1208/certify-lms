<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->create(['name' => 'テスト面談パック']);

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'))
            ->assertOk()
            ->assertViewIs('meeting-pack.management.index')
            ->assertViewHas('plans')
            ->assertSee('テスト面談パック');
    }

    public function test_index_can_filter_by_keyword(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->create(['name' => '短期集中パック']);

        MeetingPack::factory()->create(['name' => 'じっくり学習パック']);

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', ['keyword' => '短期']))
            ->assertOk()
            ->assertSee('短期集中パック')
            ->assertDontSee('じっくり学習パック');
    }

    public function test_index_can_filter_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->draft()->create(['name' => '下書きパック']);

        MeetingPack::factory()->published()->create(['name' => '公開中パック']);

        MeetingPack::factory()->archived()->create(['name' => 'アーカイブパック']);

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'status' => MeetingPackStatus::Published->value,
            ]))
            ->assertOk()
            ->assertSee('公開中パック')
            ->assertDontSee('下書きパック')
            ->assertDontSee('アーカイブパック');
    }

    public function test_index_is_paginated_by_twenty_items(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->count(21)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        $response
            ->assertOk()
            ->assertViewHas(
                'plans',
                fn ($plans) => $plans->count() === 20
                    && $plans->total() === 21,
            );
    }

    public function test_admin_can_view_create_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.create'))
            ->assertOk()
            ->assertViewIs('meeting-pack.management.create');
    }

    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->validPayload(),
            );

        $plan = MeetingPack::query()
            ->where('name', '3回パック')
            ->firstOrFail();

        $response
            ->assertRedirect(
                route('admin.meeting-packs.show', $plan),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => '3回パック',
            'description' => '追加面談用のパックです。',
            'meeting_count' => 3,
            'price' => 9000,
            'stripe_price_id' => 'price_test_123',
            'sort_order' => 10,
            'status' => MeetingPackStatus::Draft->value,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_sent_during_creation_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->validPayload([
                    'status' => MeetingPackStatus::Published->value,
                ]),
            )
            ->assertRedirect();

        $this->assertDatabaseHas('meeting_packs', [
            'name' => '3回パック',
            'status' => MeetingPackStatus::Draft->value,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.meeting-packs.create'))
            ->post(route('admin.meeting-packs.store'), [])
            ->assertRedirect(route('admin.meeting-packs.create'))
            ->assertSessionHasErrors([
                'name',
                'meeting_count',
                'price',
            ]);

        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_store_validates_numeric_ranges(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->validPayload([
                    'meeting_count' => 101,
                    'price' => 1000001,
                    'sort_order' => -1,
                ]),
            )
            ->assertSessionHasErrors([
                'meeting_count',
                'price',
                'sort_order',
            ]);

        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_admin_can_view_detail(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->create([
            'name' => '詳細確認パック',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.show', $plan))
            ->assertOk()
            ->assertViewIs('meeting-pack.management.show')
            ->assertViewHas('plan')
            ->assertSee('詳細確認パック');
    }

    public function test_admin_can_update_basic_information(): void
    {
        $creator = User::factory()->admin()->create();
        $updater = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->draft()->create([
            'name' => '変更前パック',
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);

        $response = $this->actingAs($updater)
            ->patch(
                route('admin.meeting-packs.update', $plan),
                $this->validPayload([
                    'name' => '変更後パック',
                    'meeting_count' => 5,
                    'price' => 12000,
                ]),
            );

        $response
            ->assertRedirect(
                route('admin.meeting-packs.show', $plan),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => '変更後パック',
            'meeting_count' => 5,
            'price' => 12000,
            'status' => MeetingPackStatus::Draft->value,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $updater->id,
        ]);
    }

    public function test_status_sent_during_update_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->patch(
                route('admin.meeting-packs.update', $plan),
                $this->validPayload([
                    'status' => MeetingPackStatus::Published->value,
                ]),
            )
            ->assertRedirect();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Draft->value,
        ]);
    }

    public function test_admin_can_delete_draft_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->delete(
                route('admin.meeting-packs.destroy', $plan),
            )
            ->assertRedirect(
                route('admin.meeting-packs.index'),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $plan->id,
        ]);
    }

    public function test_admin_can_delete_archived_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)
            ->delete(
                route('admin.meeting-packs.destroy', $plan),
            )
            ->assertRedirect(
                route('admin.meeting-packs.index'),
            );

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $plan->id,
        ]);
    }

    public function test_published_meeting_pack_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->deleteJson(
                route('admin.meeting-packs.destroy', $plan),
            )
            ->assertConflict();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Published->value,
        ]);
    }

    public function test_admin_can_publish_draft_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.publish', $plan),
            )
            ->assertRedirect(
                route('admin.meeting-packs.show', $plan),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Published->value,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_archive_published_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.archive', $plan),
            )
            ->assertRedirect(
                route('admin.meeting-packs.show', $plan),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Archived->value,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_return_archived_meeting_pack_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.unarchive', $plan),
            )
            ->assertRedirect(
                route('admin.meeting-packs.show', $plan),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Draft->value,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_published_meeting_pack_cannot_be_published_again(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(
                route('admin.meeting-packs.publish', $plan),
            )
            ->assertConflict();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Published->value,
        ]);
    }

    public function test_draft_meeting_pack_cannot_be_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->postJson(
                route('admin.meeting-packs.archive', $plan),
            )
            ->assertConflict();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Draft->value,
        ]);
    }

    public function test_only_archived_meeting_pack_can_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(
                route('admin.meeting-packs.unarchive', $plan),
            )
            ->assertConflict();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Published->value,
        ]);
    }

    public function test_student_cannot_access_management_screen(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }

    public function test_coach_cannot_access_management_screen(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_create_meeting_pack_by_direct_request(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(
                route('admin.meeting-packs.store'),
                $this->validPayload(),
            )
            ->assertForbidden();

        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.meeting-packs.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validPayload(
        array $overrides = [],
    ): array {
        return array_merge([
            'name' => '3回パック',
            'description' => '追加面談用のパックです。',
            'meeting_count' => 3,
            'price' => 9000,
            'stripe_price_id' => 'price_test_123',
            'sort_order' => 10,
        ], $overrides);
    }
}
