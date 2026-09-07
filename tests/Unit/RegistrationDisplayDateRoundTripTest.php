<?php

namespace Tests\Unit;

use App\Support\RegistrationDisplay;
use PHPUnit\Framework\TestCase;

class RegistrationDisplayDateRoundTripTest extends TestCase
{
    public function test_parses_us_slash_format_and_round_trips(): void
    {
        $parsed = RegistrationDisplay::parseStoredDateWithFormat('03/15/2026');
        $this->assertNotNull($parsed);
        $this->assertSame('2026-03-15', $parsed['iso']);
        $this->assertSame('m/d/Y', $parsed['format']);

        $stored = RegistrationDisplay::persistAdminDateField('2026-03-20', 'm/d/Y');
        $this->assertSame('03/20/2026', $stored);
    }

    public function test_parses_iso_and_keeps_iso_on_save(): void
    {
        $parsed = RegistrationDisplay::parseStoredDateWithFormat('2026-03-15');
        $this->assertNotNull($parsed);
        $this->assertSame('Y-m-d', $parsed['format']);

        $stored = RegistrationDisplay::persistAdminDateField('2026-03-20', 'Y-m-d');
        $this->assertSame('2026-03-20', $stored);
    }

    public function test_parses_dash_us_format(): void
    {
        $parsed = RegistrationDisplay::parseStoredDateWithFormat('03-15-2026');
        $this->assertNotNull($parsed);
        $this->assertSame('2026-03-15', $parsed['iso']);
        $this->assertSame('m-d-Y', $parsed['format']);

        $stored = RegistrationDisplay::persistAdminDateField('2026-04-01', 'm-d-Y');
        $this->assertSame('04-01-2026', $stored);
    }

    public function test_parses_date_with_trailing_time(): void
    {
        $parsed = RegistrationDisplay::parseStoredDateWithFormat('03/15/2026 00:00:00');
        $this->assertNotNull($parsed);
        $this->assertSame('2026-03-15', $parsed['iso']);
    }

    public function test_parses_two_digit_year(): void
    {
        $parsed = RegistrationDisplay::parseStoredDateWithFormat('03/15/26');
        $this->assertNotNull($parsed);
        $this->assertSame('2026-03-15', $parsed['iso']);
    }

    public function test_parses_dates_with_spaces_around_separators(): void
    {
        $parsed = RegistrationDisplay::parseStoredDateWithFormat('03 / 15 / 2026');
        $this->assertNotNull($parsed);
        $this->assertSame('2026-03-15', $parsed['iso']);
        $this->assertSame('m/d/Y', $parsed['format']);
    }

    public function test_parses_iso_with_spaces_around_dashes(): void
    {
        $parsed = RegistrationDisplay::parseStoredDateWithFormat('2026 - 03 - 15');
        $this->assertNotNull($parsed);
        $this->assertSame('2026-03-15', $parsed['iso']);
    }

    public function test_parses_space_separated_numeric_date(): void
    {
        $parsed = RegistrationDisplay::parseStoredDateWithFormat('03 15 2026');
        $this->assertNotNull($parsed);
        $this->assertSame('2026-03-15', $parsed['iso']);
    }

    public function test_html_date_input_from_spaced_db_value(): void
    {
        $this->assertSame('2026-03-15', RegistrationDisplay::toHtmlDateInput('03 / 15 / 2026'));
    }

    public function test_normalizes_licence_json_expiry_to_iso(): void
    {
        $rows = RegistrationDisplay::normalizeDocumentJsonExpiryRows([
            ['id' => '1', 'type' => 'Forklift', 'expiry' => '03 / 15 / 2026'],
        ]);
        $this->assertSame('2026-03-15', $rows[0]['expiry']);
        $this->assertSame('2026-03-15', $rows[0]['expiry_date']);
    }

    public function test_rebuilds_summary_from_json_rows(): void
    {
        $summary = RegistrationDisplay::rebuildDocumentRowsSummary([
            ['id' => '1', 'type' => 'Forklift', 'expiry' => '2026-03-15'],
            ['id' => '2', 'type' => 'HR', 'expiry' => '2027-01-20'],
        ]);
        $this->assertSame('Forklift (exp. 2026-03-15) · HR (exp. 2027-01-20)', $summary);
    }

    public function test_document_row_expiry_input_from_json(): void
    {
        $input = RegistrationDisplay::documentRowExpiryInputValue([
            'id' => '1',
            'type' => 'Public Liability',
            'expiry' => '2026-12-01',
        ]);
        $this->assertSame('2026-12-01', $input);
    }

    public function test_array_date_storage_format_field_does_not_overwrite_value(): void
    {
        $this->assertSame(
            'police_check_expiry_storage_format',
            RegistrationDisplay::adminDateStorageFormatFieldName('police_check_expiry')
        );
        $this->assertSame(
            'licence_expiry_row_storage_format[7]',
            RegistrationDisplay::adminDateStorageFormatFieldName('licence_expiry_row[7]')
        );

        parse_str(
            'licence_expiry_row[7]=2026-04-01&licence_expiry_row[7]_storage_format=Y-m-d',
            $colliding
        );
        $this->assertSame('Y-m-d', $colliding['licence_expiry_row'][7]);

        $formatName = RegistrationDisplay::adminDateStorageFormatFieldName('licence_expiry_row[7]');
        parse_str('licence_expiry_row[7]=2026-04-01&'.$formatName.'=Y-m-d', $fixed);
        $this->assertSame('2026-04-01', $fixed['licence_expiry_row'][7]);
        $this->assertSame('Y-m-d', $fixed['licence_expiry_row_storage_format'][7]);
    }
}
