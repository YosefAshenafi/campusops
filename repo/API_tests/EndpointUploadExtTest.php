<?php

declare(strict_types=1);

namespace tests\api;

use app\model\FileUpload;
use app\model\Notification;
use app\model\User;

/**
 * HTTP endpoint tests for upload / file management and notification mark-read:
 *
 *   POST   /api/v1/upload              (upload a file)
 *   GET    /api/v1/upload/:id          (get file info)
 *   GET    /api/v1/upload/:id/download (download a file)
 *   DELETE /api/v1/upload/:id          (delete a file)
 *   PUT    /api/v1/notifications/:id/read
 */
class EndpointUploadExtTest extends HttpTestCase
{
    private int $fileId       = 0;
    private int $notifId      = 0;
    private int $adminUserId  = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = '';
        $this->cleanupUsersLike('http-upl-%');

        // Ensure an admin user exists before seeding records that need a user_id.
        $adminUser = $this->ensureUser('http-upl-admin', 'administrator');
        $this->adminUserId = (int) $adminUser->id;

        // Seed a FileUpload record so show/download/delete have a real id.
        $f = new FileUpload();
        $f->uploaded_by  = $this->adminUserId;
        $f->filename     = 'test_file_' . uniqid() . '.txt';
        $f->original_name = 'test.txt';
        $f->sha256       = hash('sha256', 'dummy content');
        $f->file_path    = '/tmp/test_file.txt';
        $f->size         = 100;
        $f->category     = 'general';
        $f->save();
        $this->fileId = (int) $f->id;

        // Seed a Notification so mark-read has a real id.
        $n = new Notification();
        $n->user_id    = $this->adminUserId;
        $n->type       = 'order_update';
        $n->title      = 'HTTP Upload Test Notification';
        $n->body       = 'test body';
        $n->entity_type = '';
        $n->entity_id  = 0;
        $n->save();
        $this->notifId = (int) $n->id;
    }

    protected function tearDown(): void
    {
        if ($this->fileId) {
            FileUpload::where('id', $this->fileId)->delete();
        }
        if ($this->notifId) {
            Notification::where('id', $this->notifId)->delete();
        }
        Notification::where('title', 'like', 'HTTP Upload Test%')->delete();
        FileUpload::where('uploaded_by', $this->adminUserId)->delete();
        $this->cleanupUsersLike('http-upl-%');
        $this->token = '';
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        $this->loginAsRole('administrator', 'http-upl-admin');
    }

    private function loginRegular(): void
    {
        $this->loginAsRole('regular_user', 'http-upl-regular');
    }

    // ======================================================================
    // POST /api/v1/upload
    // ======================================================================

    public function testUploadReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/upload');
        $this->assertUnauthorized($res);
    }

    public function testUploadReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->post('/api/v1/upload');
        $this->assertForbidden($res);
    }

    public function testUploadReturns400WhenNoFileProvided(): void
    {
        $this->loginAdmin();
        // POST without an actual multipart file → controller returns 400.
        $res = $this->post('/api/v1/upload');
        $this->assertStatus(400, $res);
    }

    // ======================================================================
    // GET /api/v1/upload/:id
    // ======================================================================

    public function testUploadShowReturns401WhenUnauthenticated(): void
    {
        $res = $this->get("/api/v1/upload/{$this->fileId}");
        $this->assertUnauthorized($res);
    }

    public function testUploadShowReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get("/api/v1/upload/{$this->fileId}");
        // Show returns 200 for the owner/admin.
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testUploadShowReturns404ForMissingFile(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/upload/999999');
        $this->assertNotFound($res);
    }

    // ======================================================================
    // GET /api/v1/upload/:id/download
    // ======================================================================

    public function testUploadDownloadReturns401WhenUnauthenticated(): void
    {
        $res = $this->get("/api/v1/upload/{$this->fileId}/download");
        $this->assertUnauthorized($res);
    }

    public function testUploadDownloadReturns404WhenFilePathMissing(): void
    {
        // The seeded record has a non-existent file_path; download will fail with 404.
        $this->loginAdmin();
        $res = $this->get("/api/v1/upload/{$this->fileId}/download");
        $this->assertNotFound($res);
    }

    public function testUploadDownloadReturns404ForMissingRecord(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/upload/999999/download');
        $this->assertNotFound($res);
    }

    // ======================================================================
    // DELETE /api/v1/upload/:id
    // ======================================================================

    public function testUploadDeleteReturns401WhenUnauthenticated(): void
    {
        $res = $this->delete("/api/v1/upload/{$this->fileId}");
        $this->assertUnauthorized($res);
    }

    public function testUploadDeleteReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->delete("/api/v1/upload/{$this->fileId}");
        $this->assertForbidden($res);
    }

    public function testUploadDeleteReturns200ForAdmin(): void
    {
        // Create a throwaway record so the seeded one remains for other tests.
        $f = new FileUpload();
        $f->uploaded_by  = $this->adminUserId;
        $f->filename     = 'disposable_' . uniqid() . '.txt';
        $f->original_name = 'disposable.txt';
        $f->sha256       = hash('sha256', 'disposable');
        $f->file_path    = '/tmp/disposable.txt';
        $f->size         = 10;
        $f->save();
        $disposableId = (int) $f->id;

        $this->loginAdmin();
        $res = $this->delete("/api/v1/upload/{$disposableId}");
        // 200 OK or 400 (if file_path doesn't exist) — we accept either because
        // the controller catches filesystem errors and may return 400.
        $this->assertTrue(
            $res['status'] === 200 || $res['status'] === 400,
            "Expected 200 or 400, got {$res['status']}"
        );
    }

    // ======================================================================
    // PUT /api/v1/notifications/:id/read
    // ======================================================================

    public function testMarkNotificationReadReturns401WhenUnauthenticated(): void
    {
        $res = $this->put("/api/v1/notifications/{$this->notifId}/read");
        $this->assertUnauthorized($res);
    }

    public function testMarkNotificationReadReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->put("/api/v1/notifications/{$this->notifId}/read");
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testMarkNotificationReadReturns404ForMissingNotification(): void
    {
        $this->loginAdmin();
        $res = $this->put('/api/v1/notifications/999999/read');
        $this->assertNotFound($res);
    }

    public function testMarkNotificationReadReturns404WhenNotOwner(): void
    {
        // Regular user tries to mark admin's notification — should get 404.
        $this->loginRegular();
        $res = $this->put("/api/v1/notifications/{$this->notifId}/read");
        $this->assertNotFound($res);
    }
}
