<?php

namespace app\service;

use app\model\FileUpload;

class UploadService
{
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    protected string $uploadPath;

    public function __construct()
    {
        $this->uploadPath = runtime_path() . '/uploads';
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * Upload a file.
     */
    public function upload($file, $currentUser): array
    {
        if (!$file->checkMIME(self::ALLOWED_TYPES)) {
            throw new \Exception('File type not allowed. Allowed: JPG, PNG, PDF', 400);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \Exception('File size exceeds 10 MB limit', 400);
        }

        $originalName = $file->getFilename();
        $extension = $file->getExtension();
        $sha256 = hash_file('sha256', $file->getPathname());
        $storedName = $sha256 . '.' . $extension;
        $targetPath = $this->uploadPath . '/' . $storedName;

        $file->move($this->uploadPath, $storedName);

        $fileRecord = new FileUpload();
        $fileRecord->uploaded_by = $currentUser->id;
        $fileRecord->filename = $storedName;
        $fileRecord->original_name = $originalName;
        $fileRecord->sha256 = $sha256;
        $fileRecord->file_path = $targetPath;
        $fileRecord->size = $file->getSize();
        $fileRecord->category = 'general';
        $fileRecord->save();

        return [
            'id' => $fileRecord->id,
            'filename' => $fileRecord->filename,
            'original_name' => $fileRecord->original_name,
            'sha256' => $fileRecord->sha256,
            'size' => $fileRecord->size,
            'url' => '/api/v1/upload/' . $fileRecord->id . '/download',
            'created_at' => $fileRecord->created_at,
        ];
    }

    /**
     * Get file info.
     */
    public function getFile(int $id, int $userId = 0, string $role = ''): array
    {
        $file = FileUpload::find($id);
        if (!$file) {
            throw new \Exception('File not found', 404);
        }

        if ($role !== 'administrator' && $role !== 'operations_staff' && $file->uploaded_by !== $userId) {
            throw new \Exception('Access denied', 403);
        }

        return [
            'id' => $file->id,
            'filename' => $file->filename,
            'original_name' => $file->original_name,
            'sha256' => $file->sha256,
            'size' => $file->size,
            'url' => '/api/v1/upload/' . $file->id . '/download',
            'created_at' => $file->created_at,
        ];
    }

    /**
     * Download file.
     */
    public function download(int $id, int $userId = 0, string $role = ''): array
    {
        $file = FileUpload::find($id);
        if (!$file || !file_exists($file->file_path)) {
            throw new \Exception('File not found', 404);
        }

        if ($role !== 'administrator' && $role !== 'operations_staff' && $file->uploaded_by !== $userId) {
            throw new \Exception('Access denied', 403);
        }

        return [
            'filename' => $file->filename,
            'original_name' => $file->original_name,
            'file_path' => $file->file_path,
            'content_type' => $file->content_type ?? 'application/octet-stream',
        ];
    }

    /**
     * Delete file.
     */
    public function deleteFile(int $id, $currentUser): void
    {
        $file = FileUpload::find($id);
        if (!$file) {
            throw new \Exception('File not found', 404);
        }

        if ($file->uploaded_by != $currentUser->id && !$currentUser->hasPermission('uploads.delete')) {
            throw new \Exception('Insufficient permissions', 403);
        }

        if (file_exists($file->file_path)) {
            unlink($file->file_path);
        }

        $file->delete();
    }
}