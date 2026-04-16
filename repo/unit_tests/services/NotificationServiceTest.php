<?php

declare(strict_types=1);

namespace tests\services;

use app\model\Notification;
use app\model\User;
use app\model\UserPreference;
use app\service\NotificationService;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();
        $this->testUserId = $this->createTestUser();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // getNotifications
    // ------------------------------------------------------------------

    public function testGetNotificationsReturnsPaginatedList(): void
    {
        $this->insertNotification($this->testUserId, 'order_update', 'Unit Test Notif A', 'body a');
        $this->insertNotification($this->testUserId, 'order_update', 'Unit Test Notif B', 'body b');

        $result = $this->service->getNotifications($this->testUserId);

        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertGreaterThanOrEqual(2, $result['total']);
    }

    public function testGetNotificationsUnreadCountIsAccurate(): void
    {
        $this->insertNotification($this->testUserId, 'order_update', 'Unit Test Unread', 'body');
        $read = $this->insertNotification($this->testUserId, 'order_update', 'Unit Test Read', 'body');
        $read->read_at = date('Y-m-d H:i:s');
        $read->save();

        $result = $this->service->getNotifications($this->testUserId);

        $this->assertGreaterThanOrEqual(1, $result['unread_count']);
    }

    public function testGetNotificationsDoesNotReturnOtherUsersNotifications(): void
    {
        $otherId = $this->createTestUser('unit-test-notif-other');
        $this->insertNotification($otherId, 'order_update', 'Unit Test Other User Notif', 'body');

        $result = $this->service->getNotifications($this->testUserId);

        foreach ($result['list'] as $item) {
            $this->assertNotEquals('Unit Test Other User Notif', $item['title']);
        }
    }

    // ------------------------------------------------------------------
    // create
    // ------------------------------------------------------------------

    public function testCreateNotificationSucceedsWhenTypeEnabled(): void
    {
        $notification = $this->service->create(
            $this->testUserId,
            'order_update',
            'Unit Test Create Notif',
            'test body'
        );

        $this->assertEquals($this->testUserId, $notification->user_id);
        $this->assertEquals('order_update', $notification->type);
        $this->assertEquals('Unit Test Create Notif', $notification->title);
    }

    public function testCreateNotificationFailsWhenTypeDisabled(): void
    {
        // Disable order_alerts for this user
        $this->service->updateSettings($this->testUserId, ['order_alerts' => false]);

        $this->expectException(\Exception::class);
        $this->service->create(
            $this->testUserId,
            'order_update',
            'Unit Test Disabled Notif',
            'body'
        );
    }

    // ------------------------------------------------------------------
    // markRead
    // ------------------------------------------------------------------

    public function testMarkReadSetsReadAt(): void
    {
        $notif = $this->insertNotification($this->testUserId, 'order_update', 'Unit Test Mark Read', 'body');

        $this->service->markRead($notif->id, $this->testUserId);

        $fresh = Notification::find($notif->id);
        $this->assertNotNull($fresh->read_at);
    }

    public function testMarkReadThrows404ForWrongUser(): void
    {
        $notif = $this->insertNotification($this->testUserId, 'order_update', 'Unit Test Wrong User', 'body');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->markRead($notif->id, 99999);
    }

    public function testMarkReadThrows404ForNonexistentNotification(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->markRead(999999, $this->testUserId);
    }

    // ------------------------------------------------------------------
    // getSettings / updateSettings
    // ------------------------------------------------------------------

    public function testGetSettingsReturnsDefaultsWhenNoPrefsRecord(): void
    {
        $freshUserId = $this->createTestUser('unit-test-notif-fresh');

        $settings = $this->service->getSettings($freshUserId);

        $this->assertTrue($settings['arrival_reminders']);
        $this->assertTrue($settings['activity_alerts']);
        $this->assertTrue($settings['order_alerts']);
        $this->assertTrue($settings['violation_alerts']);
    }

    public function testUpdateSettingsCreatesPrefsRecord(): void
    {
        $this->service->updateSettings($this->testUserId, [
            'order_alerts' => false,
            'activity_alerts' => true,
        ]);

        $settings = $this->service->getSettings($this->testUserId);

        $this->assertFalse($settings['order_alerts']);
        $this->assertTrue($settings['activity_alerts']);
    }

    public function testUpdateSettingsUpdatesExistingPrefs(): void
    {
        $this->service->updateSettings($this->testUserId, ['violation_alerts' => false]);
        $this->service->updateSettings($this->testUserId, ['violation_alerts' => true]);

        $settings = $this->service->getSettings($this->testUserId);

        $this->assertTrue($settings['violation_alerts']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createTestUser(string $username = 'unit-test-notif-user'): int
    {
        $user = new User();
        $user->username = $username;
        $user->role = 'regular_user';
        $user->status = 'active';
        $user->setPassword('TestPassword123');
        $user->save();
        return $user->id;
    }

    private function insertNotification(int $userId, string $type, string $title, string $body): Notification
    {
        $n = new Notification();
        $n->user_id = $userId;
        $n->type = $type;
        $n->title = $title;
        $n->body = $body;
        $n->entity_type = '';
        $n->entity_id = 0;
        $n->save();
        return $n;
    }

    private function cleanUp(): void
    {
        Notification::where('user_id', $this->testUserId)->delete();
        UserPreference::where('user_id', $this->testUserId)->delete();
        Notification::where('title', 'like', 'Unit Test%')->delete();
        User::where('username', 'like', 'unit-test-notif%')->delete();
    }
}
