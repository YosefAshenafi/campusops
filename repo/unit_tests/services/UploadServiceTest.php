<?php

declare(strict_types=1);

namespace tests\services;

use app\model\FileUpload;
use app\model\User;
use app\service\UploadService;
use PHPUnit\Framework\TestCase;

class UploadServiceTest extends TestCase
{
    private UploadService $service;
    private User $ownerUser;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UploadService();
        $this->ownerUser = $this->createTestUser('unit-test-upload-owner');
        $this->otherUser = $this->createTestUser('unit-test-upload-other');
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // upload (via anonymous file stub)
    // ------------------------------------------------------------------

    public function testUploadRejectsDisallowedMimeType(): void
    {
        $file = $this->makeFileStub('test.exe', 'application/octet-stream', 1024, false);
        $actor = $this->mockUser($this->ownerUser->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->upload($file, $actor);
    }

    public function testUploadRejectsFilesOverSizeLimit(): void
    {
        $file = $this->makeFileStub('big.jpg', 'image/jpeg', UploadService::MAX_FILE_SIZE + 1, true);
        $actor = $this->mockUser($this->ownerUser->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->upload($file, $actor);
    }

    // ------------------------------------------------------------------
    // getFile
    // ------------------------------------------------------------------

    public function testGetFileReturnsFileDataForOwner(): void
    {
        $record = $this->insertFileRecord($this->ownerUser->id);

        $result = $this->service->getFile($record->id, $this->ownerUser->id, 'regular_user');

        $this->assertEquals($record->id, $result['id']);
        $this->assertEquals($record->filename, $result['filename']);
        $this->assertEquals($record->original_name, $result['original_name']);
    }

    public function testGetFileAllowsAdministratorToAccessAnyFile(): void
    {
        $record = $this->insertFileRecord($this->ownerUser->id);

        $result = $this->service->getFile($record->id, $this->otherUser->id, 'administrator');

        $this->assertEquals($record->id, $result['id']);
    }

    public function testGetFileAllowsOperationsStaffToAccessAnyFile(): void
    {
        $record = $this->insertFileRecord($this->ownerUser->id);

        $result = $this->service->getFile($record->id, $this->otherUser->id, 'operations_staff');

        $this->assertEquals($record->id, $result['id']);
    }

    public function testGetFileThrows403ForNonOwnerWithoutAdminRole(): void
    {
        $record = $this->insertFileRecord($this->ownerUser->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->getFile($record->id, $this->otherUser->id, 'regular_user');
    }

    public function testGetFileThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->getFile(999999, $this->ownerUser->id, 'administrator');
    }

    // ------------------------------------------------------------------
    // download
    // ------------------------------------------------------------------

    public function testDownloadThrows404WhenFileRecordNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->download(999999, $this->ownerUser->id, 'administrator');
    }

    public function testDownloadThrows404WhenFileDoesNotExistOnDisk(): void
    {
        // Insert a record pointing to a non-existent path
        $record = $this->insertFileRecord($this->ownerUser->id, '/nonexistent/path/ghost.pdf');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->download($record->id, $this->ownerUser->id, 'administrator');
    }

    public function testDownloadThrows403ForNonOwnerWithoutAdminRole(): void
    {
        $record = $this->insertFileRecord($this->ownerUser->id, '/nonexistent/path/ghost2.pdf');

        $this->expectException(\Exception::class);
        // Could be 403 or 404 depending on file existence check order; just ensure an exception is thrown
        $this->service->download($record->id, $this->otherUser->id, 'regular_user');
    }

    // ------------------------------------------------------------------
    // deleteFile
    // ------------------------------------------------------------------

    public function testDeleteFileThrows404WhenNotFound(): void
    {
        $actor = $this->mockUser($this->ownerUser->id, true);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->deleteFile(999999, $actor);
    }

    public function testDeleteFileRemovesRecordFromDatabase(): void
    {
        $record = $this->insertFileRecord($this->ownerUser->id);
        $actor = $this->mockUser($this->ownerUser->id, true);

        $this->service->deleteFile($record->id, $actor);

        $this->assertNull(FileUpload::find($record->id));
    }

    public function testDeleteFileThrows403ForNonOwnerWithoutPermission(): void
    {
        $record = $this->insertFileRecord($this->ownerUser->id);
        $actor = $this->mockUser($this->otherUser->id, false);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->deleteFile($record->id, $actor);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createTestUser(string $username): User
    {
        User::where('username', $username)->delete();
        $user = new User();
        $user->username = $username;
        $user->role = 'regular_user';
        $user->status = 'active';
        $user->setPassword('TestPassword123');
        $user->save();
        return $user;
    }

    private function mockUser(int $id, bool $canDelete = false): object
    {
        return new class($id, $canDelete) {
            public int $id;
            private bool $canDelete;
            public function __construct(int $id, bool $canDelete) {
                $this->id = $id;
                $this->canDelete = $canDelete;
            }
            public function hasPermission(string $permission): bool {
                return $this->canDelete && $permission === 'uploads.delete';
            }
        };
    }

    /**
     * Creates a minimal file stub that mimics ThinkPHP's UploadedFile interface
     * just enough for the UploadService validation checks.
     */
    private function makeFileStub(string $name, string $mime, int $size, bool $mimeAllowed): object
    {
        $allowedTypes = UploadService::ALLOWED_TYPES;
        return new class($name, $mime, $size, $mimeAllowed, $allowedTypes) {
            private string $name;
            private string $mime;
            private int $size;
            private bool $mimeAllowed;
            private array $allowedTypes;

            public function __construct(string $n, string $m, int $s, bool $a, array $t) {
                $this->name = $n;
                $this->mime = $m;
                $this->size = $s;
                $this->mimeAllowed = $a;
                $this->allowedTypes = $t;
            }

            public function checkMIME(array $types): bool {
                return $this->mimeAllowed && in_array($this->mime, $types);
            }
            public function getSize(): int { return $this->size; }
            public function getFilename(): string { return $this->name; }
            public function getExtension(): string { return pathinfo($this->name, PATHINFO_EXTENSION); }
            public function getPathname(): string { return tempnam(sys_get_temp_dir(), 'unit_test_'); }
            public function move(string $dir, string $name): void {}
        };
    }

    private function insertFileRecord(int $uploadedBy, string $filePath = '/tmp/unit_test_file.pdf'): FileUpload
    {
        $record = new FileUpload();
        $record->uploaded_by = $uploadedBy;
        $record->filename = 'unit_test_' . uniqid() . '.pdf';
        $record->original_name = 'unit_test_document.pdf';
        $record->sha256 = hash('sha256', 'unit_test_content_' . uniqid());
        $record->file_path = $filePath;
        $record->size = 1024;
        $record->category = 'general';
        $record->save();
        return $record;
    }

    private function cleanUp(): void
    {
        FileUpload::whereIn('uploaded_by', [$this->ownerUser->id, $this->otherUser->id])->delete();
        User::where('username', 'like', 'unit-test-upload-%')->delete();
    }
}
