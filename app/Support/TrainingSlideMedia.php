<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;

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

    public static function response(?string $path): StreamedResponse
    {
        abort_unless(self::isSafePath($path) && Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path);
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
};
