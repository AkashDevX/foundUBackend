<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class TrainingSlideMedia
{
    public const DISK = 'training_media';

    public static function store(UploadedFile $file, string $folder): string
    {
        $stored = $file->store(trim($folder, '/'), self::DISK);
        if (! is_string($stored) || $stored === '') {
            throw new \RuntimeException('The picture could not be saved.');
        }

        return $stored;
    }

    public static function delete(?string $path): void
    {
        if (! self::isSafePath($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public static function response(?string $path): BinaryFileResponse
    {
        abort_unless(self::isSafePath($path) && Storage::disk(self::DISK)->exists($path), 404);

        $absolute = Storage::disk(self::DISK)->path($path);
        $mime = Storage::disk(self::DISK)->mimeType($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $fromExt = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => null,
        };
        $type = $fromExt
            ?? (is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream');

        // A real file response, not a chunked stream. React Native's image
        // loader drops or corrupts chunked bodies, so slides never appear.
        $response = new BinaryFileResponse($absolute);
        $response->headers->set('Content-Type', $type);
        $response->setContentDisposition('inline', basename($path));

        return $response;
    }

    public static function fresh(BinaryFileResponse $response): BinaryFileResponse
    {
        $response->setAutoEtag(false);
        $response->setAutoLastModified(false);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, must-revalidate');

        return $response;
    }

    public static function version(?string $path): string
    {
        return is_string($path) && $path !== '' ? substr(sha1($path), 0, 12) : '';
    }

    /**
     * @return list<string>
     */
    public static function bulletsFromText(?string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text) ?: [];
        $bullets = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            $line = preg_replace('/^[-•*]\s+/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $bullets[] = mb_substr($line, 0, 300);
            if (count($bullets) >= 12) {
                break;
            }
        }

        return $bullets;
    }

    private static function isSafePath(?string $path): bool
    {
        return is_string($path)
            && $path !== ''
            && ! str_contains($path, '..')
            && ! str_starts_with($path, '/');
    }
}
