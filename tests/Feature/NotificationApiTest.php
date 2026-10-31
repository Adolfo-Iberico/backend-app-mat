<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';

    private User $student;

    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $studentRole = 'student';

        $this->student = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Student Notifications',
            'email' => 'student.notifications@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->otherStudent = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Other Student',
            'email' => 'other.student@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);
    }

    private function createNotificationForUser(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Test Notification',
            'message' => 'This is a test notification message',
            'type' => 'info',
            'is_read' => false,
        ], $overrides));
    }

    public function test_user_can_list_notifications(): void
    {
        $this->createNotificationForUser($this->student, ['title' => 'First']);
        $this->createNotificationForUser($this->student, ['title' => 'Second']);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/notifications");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_id', 'title', 'message', 'type', 'is_read', 'created_at'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $notification = $this->createNotificationForUser($this->student);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->putJson("{$this->baseUrl}/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_user_can_mark_all_as_read(): void
    {
        $this->createNotificationForUser($this->student, ['title' => 'Unread 1']);
        $this->createNotificationForUser($this->student, ['title' => 'Unread 2']);
        $this->createNotificationForUser($this->student, ['title' => 'Unread 3']);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->putJson("{$this->baseUrl}/notifications/read-all");

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $unreadCount = Notification::where('user_id', $this->student->id)
            ->where('is_read', false)
            ->count();

        $this->assertEquals(0, $unreadCount);

        $totalCount = Notification::where('user_id', $this->student->id)->count();
        $this->assertEquals(3, $totalCount);
    }

    public function test_user_can_delete_notification(): void
    {
        $notification = $this->createNotificationForUser($this->student);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->deleteJson("{$this->baseUrl}/notifications/{$notification->id}");

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_can_delete_read_notifications(): void
    {
        $this->createNotificationForUser($this->student, ['title' => 'Read 1', 'is_read' => true]);
        $this->createNotificationForUser($this->student, ['title' => 'Read 2', 'is_read' => true]);
        $this->createNotificationForUser($this->student, ['title' => 'Unread']);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->deleteJson("{$this->baseUrl}/notifications/read/delete");

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $remaining = Notification::where('user_id', $this->student->id)->get();

        $this->assertCount(1, $remaining);
        $this->assertEquals('Unread', $remaining->first()->title);
    }

    public function test_unauthenticated_cannot_access_notifications(): void
    {
        $response = $this->getJson("{$this->baseUrl}/notifications");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not access notifications');

        $response = $this->putJson("{$this->baseUrl}/notifications/read-all");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not mark all as read');

        $response = $this->deleteJson("{$this->baseUrl}/notifications/read/delete");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not delete notifications');
    }

    public function test_notifications_support_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createNotificationForUser($this->student, ['title' => "Notification {$i}"]);
        }

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/notifications?per_page=2");

        $response->assertOk()
            ->assertJsonStructure([
                'data',
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_only_sees_own_notifications(): void
    {
        $this->createNotificationForUser($this->student, ['title' => 'My Notification']);
        $this->createNotificationForUser($this->otherStudent, ['title' => 'Other Notification']);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/notifications");

        $response->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('My Notification', $titles);
        $this->assertNotContains('Other Notification', $titles);
        $this->assertCount(1, $response->json('data'));
    }
}
