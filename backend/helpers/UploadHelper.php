<?php
declare(strict_types=1);

final class UploadHelper
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    public static function storeImage(array $file, string $subFolder = 'logos'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('File upload error occurred.', 400);
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            Response::error('Uploaded file exceeds 2MB size limit.', 422);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file((string)$file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            Response::error('Only JPG, PNG, and WEBP image files are permitted.', 422);
        }

        $ext = self::ALLOWED_MIME[$mime];
        $safeFolder = preg_replace('/[^a-z0-9_-]/i', '', $subFolder) ?: 'misc';
        $targetDir = dirname(__DIR__) . '/uploads/' . $safeFolder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = Security::randomToken(16) . '.' . $ext;
        $destination = $targetDir . '/' . $filename;
        if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
            Response::error('Unable to save uploaded file.', 500);
        }

        return '/uploads/' . $safeFolder . '/' . $filename;
    }
}
