<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * ID files accepted during onboarding and admin profile edits.
 */
class RegistrationIdDocument
{
    public const MAX_KILOBYTES = 15360;

    /** @var list<string> */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

    /**
     * @return array<int, mixed>
     */
    public static function fileRules(): array
    {
        return [
            'file',
            'max:'.self::MAX_KILOBYTES,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                $extension = strtolower($value->getClientOriginalExtension());
                if (! in_array($extension, self::EXTENSIONS, true)) {
                    $fail('Upload a JPG, PNG, PDF, DOC, or DOCX file.');
                }
            },
        ];
    }
}
