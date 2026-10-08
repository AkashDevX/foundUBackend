<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * Resume / CV files accepted during onboarding and admin profile edits.
 */
class RegistrationResume
{
    public const FIELD = 'resume';

    public const MAX_KILOBYTES = 15360;

    /** @var list<string> */
    public const EXTENSIONS = ['pdf', 'doc', 'docx'];

    public static function extensionOf(UploadedFile $file): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return in_array($ext, self::EXTENSIONS, true) ? $ext : null;
    }

    /**
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        return [
            'nullable',
            'file',
            'max:'.self::MAX_KILOBYTES,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                if (self::extensionOf($value) === null) {
                    $fail('Upload a PDF, DOC, or DOCX file.');
                }
            },
        ];
    }
}
