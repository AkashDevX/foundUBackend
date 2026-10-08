<?php

namespace Tests\Unit;

use App\Support\RegistrationIdDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegistrationIdDocumentTest extends TestCase
{
    public function test_accepts_images_pdf_and_word(): void
    {
        foreach ([
            UploadedFile::fake()->image('front.jpg'),
            UploadedFile::fake()->image('back.png'),
            UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->create('id.doc', 20, 'application/msword'),
            UploadedFile::fake()->create(
                'id.docx',
                20,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        ] as $file) {
            $validator = Validator::make(
                ['id_document_upload' => ['1' => $file]],
                ['id_document_upload.*' => RegistrationIdDocument::fileRules()],
            );
            $this->assertTrue($validator->passes(), $file->getClientOriginalName());
        }
    }

    public function test_rejects_other_formats(): void
    {
        $validator = Validator::make(
            ['id_document_upload' => ['1' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain')]],
            ['id_document_upload.*' => RegistrationIdDocument::fileRules()],
        );

        $this->assertFalse($validator->passes());
    }
}
