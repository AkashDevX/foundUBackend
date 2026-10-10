<?php

namespace Tests\Unit;

use App\Support\TrainingSlideBlocks;
use Tests\TestCase;

class TrainingSlideLayoutTest extends TestCase
{
    public function test_pictures_keep_the_place_they_were_given(): void
    {
        $layout = TrainingSlideBlocks::resolveLayoutTokens(
            ['title', 'new:second', 'body', 'new:first', 'bullets'],
            [4, 9, 10],
            ['first' => 9, 'second' => 10],
            true,
            false,
        );

        $this->assertSame(
            ['title', 'block:10', 'body', 'block:9', 'bullets', 'block:4'],
            $layout,
        );
    }

    public function test_a_removed_picture_is_left_out_of_the_order(): void
    {
        $layout = TrainingSlideBlocks::resolveLayoutTokens(
            ['image', 'title', 'block:3', 'body'],
            [8],
            [],
            true,
            false,
        );

        $this->assertSame(['title', 'body', 'bullets', 'block:8'], $layout);
    }

    public function test_a_link_is_kept_when_the_address_has_no_scheme(): void
    {
        $parsed = TrainingSlideBlocks::fromPayload([
            'new_kind' => ['link'],
            'new_label' => [''],
            'new_body' => ['example.com/safety'],
            'new_token' => ['link1'],
        ]);

        $this->assertSame('link', $parsed['items'][0]['kind']);
        $this->assertSame('https://example.com/safety', $parsed['items'][0]['body']);
    }

    public function test_a_link_typed_beside_a_photo_field_is_kept(): void
    {
        $parsed = TrainingSlideBlocks::fromPayload([
            'new_kind' => ['photo', 'link'],
            'new_label' => ['', 'example.com/guide'],
            'new_body' => ['', ''],
            'new_token' => ['photo1', 'link1'],
        ]);

        $this->assertCount(1, $parsed['items']);
        $this->assertSame('link', $parsed['items'][0]['kind']);
        $this->assertSame('https://example.com/guide', $parsed['items'][0]['body']);
        $this->assertNull($parsed['items'][0]['label']);
    }
}
