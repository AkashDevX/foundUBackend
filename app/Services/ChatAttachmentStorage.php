<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Private chat attachment storage (one file per message).
 */
class ChatAttachmentStorage
{
    public const DISK = 'chat_attachments';

    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];

    /**
     * @return array{path: string, mime: string, name: string, size: int, message_type: string}
     */
    public function store(UploadedFile $file, string $companySlug, int $conversationId): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['attachment' => 'The uploaded file is invalid.']);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['attachment' => 'Attachments must be 10MB or smaller.']);
        }

        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: '');
        if ($mime === '' || ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'attachment' => 'File type not allowed. Use images, PDF, Word, Excel, or plain text.',
            ]);
        }

        $prefix = trim($companySlug, '/').'/'.$conversationId;
        $path = $file->store($prefix, self::DISK);
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['attachment' => 'Could not store the attachment.']);
        }

        $original = $file->getClientOriginalName() ?: 'attachment';
        $messageType = str_starts_with($mime, 'image/') ? 'image' : 'file';

        return [
            'path' => $path,
            'mime' => $mime,
            'name' => $original,
            'size' => (int) $file->getSize(),
            'message_type' => $messageType,
        ];
    }

    public function delete(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public function absolutePath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }
}
