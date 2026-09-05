<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->create([
            'name' => 'テストプラン',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertViewIs('plan.management.index')
            ->assertViewHas('plans')
            ->assertSee('テストプラン');
    }

    public function test_index_can_filter_by_keyword(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->create([
            'name' => '短期集中プラン',
        ]);

        Plan::factory()->create([
            'name' => '長期学習プラン',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'keyword' => '短期',
            ]))
            ->assertOk()
            ->assertSee('短期集中プラン')
            ->assertDontSee('長期学習プラン');
    }

    public function test_index_can_filter_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->draft()->create([
            'name' => '下書きプラン',
        ]);

        Plan::factory()->published()->create([
            'name' => '公開中プラン',
        ]);

        Plan::factory()->archived()->create([
            'name' => 'アーカイブプラン',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'status' => PlanStatus::Published->value,
            ]))
            ->assertOk()
            ->assertSee('公開中プラン')
            ->assertDontSee('下書きプラン')
            ->assertDontSee('アーカイブプラン');
    }

    public function test_index_is_paginated_by_twenty_items(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->count(21)->create();

        $this->actingAs($admin)
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertViewHas(
                'plans',
                fn ($plans) => $plans->count() === 20
                    && $plans->total() === 21,
            );
    }

    public function test_index_contains_number_of_assigned_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        User::factory()
            ->student()
            ->withPlan($plan)
            ->create();

        $this->actingAs($admin)
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertViewHas('plans', function ($plans) use ($plan) {
                $result = $plans->firstWhere('id', $plan->id);

                return $result !== null
                    && $result->users_count === 1;
            });
    }

    public function test_admin_can_view_create_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.plans.create'))
            ->assertOk()
            ->assertViewIs('plan.management.create');
    }

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->validPayload(),
            );

        $plan = Plan::query()
            ->where('name', '3か月プラン')
            ->firstOrFail();

        $response
            ->assertRedirect(
                route('admin.plans.show', $plan),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '3か月プラン',
            'description' => '標準的な受講プランです。',
            'duration_days' => 90,
            'default_meeting_quota' => 4,
            'sort_order' => 10,
            'status' => PlanStatus::Draft->value,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_sent_during_creation_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->validPayload([
                    'status' => PlanStatus::Published->value,
                ]),
            )
            ->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'name' => '3か月プラン',
            'status' => PlanStatus::Draft->value,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.plans.create'))
            ->post(route('admin.plans.store'), [])
            ->assertRedirect(route('admin.plans.create'))
            ->assertSessionHasErrors([
                'name',
                'duration_days',
                'default_meeting_quota',
            ]);

        $this->assertDatabaseCount('plans', 0);
    }

    public function test_store_validates_numeric_ranges(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->validPayload([
                    'duration_days' => 3651,
                    'default_meeting_quota' => 1001,
                    'sort_order' => -1,
                ]),
            )
            ->assertSessionHasErrors([
                'duration_days',
                'default_meeting_quota',
                'sort_order',
            ]);

        $this->assertDatabaseCount('plans', 0);
    }

    public function test_admin_can_view_detail_with_assigned_users(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()->published()->create([
            'name' => '詳細確認プラン',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $student = User::factory()
            ->student()
            ->withPlan($plan)
            ->create([
                'name' => '契約中受講生',
            ]);

        $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan))
            ->assertOk()
            ->assertViewIs('plan.management.show')
            ->assertViewHas('plan')
            ->assertSee('詳細確認プラン')
            ->assertSee($student->name);
    }

    public function test_admin_can_update_plan_without_active_users(): void
    {
        $creator = User::factory()->admin()->create();
        $updater = User::factory()->admin()->create();

        $plan = Plan::factory()->draft()->create([
            'name' => '変更前プラン',
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);

        $this->actingAs($updater)
            ->put(
                route('admin.plans.update', $plan),
                $this->validPayload([
                    'name' => '変更後プラン',
                    'duration_days' => 180,
                    'default_meeting_quota' => 8,
                ]),
            )
            ->assertRedirect(
                route('admin.plans.show', $plan),
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '変更後プラン',
            'duration_days' => 180,
            'default_meeting_quota' => 8,
            'status' => PlanStatus::Draft->value,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $updater->id,
        ]);
    }

    public function test_plan_with_active_user_cannot_be_updated(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()->draft()->create([
            'name' => '変更前プラン',
        ]);

        User::factory()
            ->student()
            ->inProgress()
            ->withPlan($plan)
            ->create();

        $this->actingAs($admin)
            ->putJson(
                route('admin.plans.update', $plan),
                $this->validPayload([
                    'name' => '変更後プラン',
                ]),
            )
            ->assertConflict();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '変更前プラン',
        ]);
    }

    public function test_status_sent_during_update_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->validPayload([
                    'status' => PlanStatus::Published->value,
                ]),
            )
            ->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => PlanStatus::Draft->value,
        ]);
    }

    public function test_admin_can_delete_unreferenced_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_published_plan_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan))
            ->assertConflict();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_archived_plan_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan))
            ->assertConflict();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_plan_assigned_to_user_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        User::factory()
            ->student()
            ->withPlan($plan)
            ->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan))
            ->assertConflict();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_plan_referenced_by_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        UserPlanLog::factory()
            ->for($plan)
            ->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan))
            ->assertConflict();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_admin_can_publish_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan))
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => PlanStatus::Published->value,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_archive_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan))
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => PlanStatus::Archived->value,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_return_archived_plan_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan))
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => PlanStatus::Draft->value,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_published_plan_cannot_be_published_again(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.publish', $plan))
            ->assertConflict();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => PlanStatus::Published->value,
        ]);
    }

    public function test_draft_plan_cannot_be_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.archive', $plan))
            ->assertConflict();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => PlanStatus::Draft->value,
        ]);
    }

    public function test_only_archived_plan_can_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.unarchive', $plan))
            ->assertConflict();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => PlanStatus::Published->value,
        ]);
    }

    public function test_student_cannot_access_management_screen(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }

    public function test_coach_cannot_access_management_screen(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_create_plan_by_direct_request(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(
                route('admin.plans.store'),
                $this->validPayload(),
            )
            ->assertForbidden();

        $this->assertDatabaseCount('plans', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.plans.index'))
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
            'name' => '3か月プラン',
            'description' => '標準的な受講プランです。',
            'duration_days' => 90,
            'default_meeting_quota' => 4,
            'sort_order' => 10,
        ], $overrides);
    }
}
