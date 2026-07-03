<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * T032: DocumentStorage.
 *
 * S3-compatible object storage wrapper. In dev uses MinIO; in prod uses
 * Azure Blob (via the S3 API). Generates a tenant-scoped storage key:
 *   {tenant_id}/boilerplate/{uuid}.{ext}
 */
final class DocumentStorage
{
    private const DISK = 's3';

    public function put(UploadedFile $file, string $tenantId): string
    {
        $key = sprintf(
            '%s/boilerplate/%s.%s',
            $tenantId,
            (string) Str::uuid(),
            $file->getClientOriginalExtension()
        );
        Storage::disk(self::DISK)->putFileAs(
            dirname($key),
            $file,
            basename($key),
        );
        return $key;
    }

    public function get(string $key): string
    {
        return Storage::disk(self::DISK)->get($key);
    }

    public function delete(string $key): void
    {
        Storage::disk(self::DISK)->delete($key);
    }

    public function exists(string $key): bool
    {
        return Storage::disk(self::DISK)->exists($key);
    }
}
