<?php

namespace Tests\Unit;

use App\Support\RegistrationDisplay;
use App\Support\RegistrationResume;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RegistrationResumeTest extends TestCase
{
    public function test_only_pdf_doc_and_docx_extensions_are_accepted(): void
    {
        $this->assertSame('pdf', RegistrationResume::extensionOf(
            UploadedFile::fake()->create('cv.pdf', 4, 'application/pdf')
        ));
        $this->assertSame('doc', RegistrationResume::extensionOf(
            UploadedFile::fake()->create('cv.doc', 4, 'application/msword')
        ));
        $this->assertSame('docx', RegistrationResume::extensionOf(
            UploadedFile::fake()->create('cv.docx', 4, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ));
        $this->assertNull(RegistrationResume::extensionOf(
            UploadedFile::fake()->create('photo.jpg', 4, 'image/jpeg')
        ));
        $this->assertNull(RegistrationResume::extensionOf(
            UploadedFile::fake()->create('notes.txt', 4, 'text/plain')
        ));
    }

    public function test_word_paths_are_recognised_for_profile_display(): void
    {
        $this->assertTrue(RegistrationDisplay::isLikelyWordPath('acme/emp/cv.docx'));
        $this->assertTrue(RegistrationDisplay::isLikelyWordPath('acme/emp/cv.DOC'));
        $this->assertFalse(RegistrationDisplay::isLikelyWordPath('acme/emp/cv.pdf'));
        $this->assertFalse(RegistrationDisplay::isLikelyWordPath(null));
    }
}
