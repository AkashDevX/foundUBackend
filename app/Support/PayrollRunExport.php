<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * CSV and PDF downloads for one pay period.
 */
final class PayrollRunExport
{
    /**
     * @param  list<array<string, mixed>>  $previewRows
     * @return array{
     *     company: string,
     *     period_label: string,
     *     period_start: string,
     *     period_end: string,
     *     status: string,
     *     generated: string,
     *     included_count: int,
     *     excluded_count: int,
     *     total_hours: float,
     *     total_amount: float,
     *     employees: list<array<string, mixed>>,
     *     excluded: list<array<string, mixed>>,
     * }
     */
    public static function document(
        string $companyName,
        string $fortnightStart,
        string $fortnightEnd,
        ?string $runStatus,
        array $previewRows,
    ): array {
        $employees = [];
        $excluded = [];
        $totalHours = 0.0;
        $totalAmount = 0.0;

        foreach ($previewRows as $row) {
            /** @var Employee $employee */
            $employee = $row['employee'];
            $name = trim((string) ($employee->full_legal_name ?: $employee->email ?: 'Employee'));
            $code = trim((string) ($employee->employee_code ?: ''));
            $payable = ($row['skipped_reason'] ?? null) === null
                && (((float) ($row['total_hours'] ?? 0)) > 0 || ((float) ($row['total_amount'] ?? 0)) > 0);

            if (! $payable) {
                $excluded[] = [
                    'name' => $name,
                    'code' => $code !== '' ? $code : '—',
                    'email' => (string) ($employee->email ?: ''),
                    'reason' => (string) ($row['skipped_reason'] ?? 'No hours to pay'),
                    'scheduled_hours' => round((float) ($row['scheduled_hours'] ?? 0), 2),
                ];

                continue;
            }

            $lines = [];
            foreach (AdminPayroll::payableLines($row['lines'] ?? []) as $line) {
                $hours = round((float) ($line['hours'] ?? 0), 2);
                $rate = round((float) ($line['rate'] ?? 0), 2);
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $lines[] = [
                    'label' => AdminPayroll::payLineLabel($line),
                    'hours' => $hours,
                    'rate' => $rate,
                    'amount' => $amount,
                    'meta' => ($hours > 0 && $rate > 0)
                        ? number_format($hours, 2).' hrs × '.AdminPayroll::formatMoney($rate).'/hr'
                        : ($hours > 0 ? number_format($hours, 2).' hrs' : ''),
                    'amount_label' => AdminPayroll::formatMoney($amount),
                ];
            }

            $hours = round((float) ($row['total_hours'] ?? 0), 2);
            $amount = round((float) ($row['total_amount'] ?? 0), 2);
            $totalHours += $hours;
            $totalAmount += $amount;
            $employees[] = [
                'name' => $name,
                'code' => $code !== '' ? $code : '—',
                'email' => (string) ($employee->email ?: ''),
                'worked_hours' => $hours,
                'scheduled_hours' => round((float) ($row['scheduled_hours'] ?? 0), 2),
                'variance' => (string) ($row['roster_variance'] ?? '—'),
                'gross' => $amount,
                'gross_label' => AdminPayroll::formatMoney($amount),
                'lines' => $lines,
            ];
        }

        $status = match ($runStatus) {
            'finalized' => 'Finalized',
            'draft' => 'Draft',
            default => 'Not started',
        };

        return [
            'company' => $companyName !== '' ? $companyName : 'Organization',
            'period_label' => DisplayTimezone::format(Carbon::parse($fortnightStart), 'j M Y')
                .' – '
                .DisplayTimezone::format(Carbon::parse($fortnightEnd), 'j M Y'),
            'period_start' => $fortnightStart,
            'period_end' => $fortnightEnd,
            'status' => $status,
            'generated' => DisplayTimezone::format(DisplayTimezone::now(), 'j M Y, g:i A'),
            'included_count' => count($employees),
            'excluded_count' => count($excluded),
            'total_hours' => round($totalHours, 2),
            'total_amount' => round($totalAmount, 2),
            'employees' => $employees,
            'excluded' => $excluded,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $previewRows
     */
    public static function csv(
        string $companyName,
        string $fortnightStart,
        string $fortnightEnd,
        ?string $runStatus,
        array $previewRows,
    ): string {
        $document = self::document($companyName, $fortnightStart, $fortnightEnd, $runStatus, $previewRows);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Pay run', $document['company']]);
        fputcsv($handle, ['Period', $document['period_label']]);
        fputcsv($handle, ['Status', $document['status']]);
        fputcsv($handle, ['Generated', $document['generated']]);
        fputcsv($handle, []);
        fputcsv($handle, [
            'Employee',
            'Employee code',
            'Email',
            'Status',
            'Worked hours',
            'Scheduled hours',
            'Compared to schedule',
            'Gross pay',
            'Pay line',
            'Line hours',
            'Hourly rate',
            'Line amount',
        ]);

        foreach ($document['employees'] as $employee) {
            $lines = $employee['lines'] !== [] ? $employee['lines'] : [[
                'label' => '',
                'hours' => '',
                'rate' => '',
                'amount' => '',
            ]];
            foreach ($lines as $index => $line) {
                fputcsv($handle, [
                    $index === 0 ? $employee['name'] : '',
                    $index === 0 ? $employee['code'] : '',
                    $index === 0 ? $employee['email'] : '',
                    $index === 0 ? 'Ready' : '',
                    $index === 0 ? number_format((float) $employee['worked_hours'], 2, '.', '') : '',
                    $index === 0 ? number_format((float) $employee['scheduled_hours'], 2, '.', '') : '',
                    $index === 0 ? $employee['variance'] : '',
                    $index === 0 ? number_format((float) $employee['gross'], 2, '.', '') : '',
                    $line['label'],
                    $line['hours'] === '' ? '' : number_format((float) $line['hours'], 2, '.', ''),
                    $line['rate'] === '' ? '' : number_format((float) $line['rate'], 2, '.', ''),
                    $line['amount'] === '' ? '' : number_format((float) $line['amount'], 2, '.', ''),
                ]);
            }
        }

        foreach ($document['excluded'] as $employee) {
            fputcsv($handle, [
                $employee['name'],
                $employee['code'],
                $employee['email'],
                'Not included',
                '',
                number_format((float) $employee['scheduled_hours'], 2, '.', ''),
                '',
                '',
                $employee['reason'],
                '',
                '',
                '',
            ]);
        }

        fputcsv($handle, []);
        fputcsv($handle, [
            'Pay period total',
            '',
            '',
            $document['included_count'].' employee'.($document['included_count'] === 1 ? '' : 's'),
            number_format($document['total_hours'], 2, '.', ''),
            '',
            '',
            number_format($document['total_amount'], 2, '.', ''),
            '',
            '',
            '',
            '',
        ]);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param  list<array<string, mixed>>  $previewRows
     */
    public static function render(
        string $companyName,
        string $fortnightStart,
        string $fortnightEnd,
        ?string $runStatus,
        array $previewRows,
    ): string {
        $document = self::document($companyName, $fortnightStart, $fortnightEnd, $runStatus, $previewRows);
        $html = view('admin.payroll.pdf', array_merge($document, [
            'logo' => self::logoDataUri(),
        ]))->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (int $pageNumber, int $pageCount, $canvas, $fontMetrics): void {
            $font = $fontMetrics->getFont('DejaVu Sans');
            $text = 'Page '.$pageNumber.' of '.$pageCount;
            $size = 8;
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $canvas->text($canvas->get_width() - 32 - $width, $canvas->get_height() - 24, $text, $font, $size, [0.42, 0.47, 0.55]);
        });

        $dompdf->addInfo('Title', 'Pay run '.$document['period_label']);
        $dompdf->addInfo('Author', $document['company']);
        $dompdf->addInfo('Subject', 'Pay run');

        return $dompdf->output();
    }

    public static function filename(string $fortnightStart, string $fortnightEnd, string $extension): string
    {
        $extension = $extension === 'pdf' ? 'pdf' : 'csv';

        return 'pay-run-'.$fortnightStart.'-to-'.$fortnightEnd.'.'.$extension;
    }

    /**
     * CruLynk mark with the black plate removed so it sits on the white letterhead.
     */
    private static function logoDataUri(): ?string
    {
        $path = public_path('images/crulynk-logo.png');
        if (! is_file($path) || ! function_exists('imagecreatefrompng')) {
            return null;
        }

        $source = @imagecreatefrompng($path);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $targetW = 360;
        $targetH = max(1, (int) round($height * ($targetW / max(1, $width))));
        $image = imagecreatetruecolor($targetW, $targetH);
        if ($image === false) {
            imagedestroy($source);

            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, $targetW, $targetH, $clear);
        imagealphablending($image, true);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagedestroy($source);

        imagealphablending($image, false);
        imagesavealpha($image, true);
        for ($y = 0; $y < $targetH; $y++) {
            for ($x = 0; $x < $targetW; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $chroma = max($r, $g, $b) - min($r, $g, $b);
                $lum = (int) round((0.3 * $r) + (0.59 * $g) + (0.11 * $b));
                if ($chroma >= 24 || $lum >= 52) {
                    continue;
                }

                $alpha = $lum < 22 ? 127 : (int) min(127, 78 + ((52 - $lum) * 2));
                $color = imagecolorallocatealpha($image, $r, $g, $b, $alpha);
                imagesetpixel($image, $x, $y, $color);
            }
        }

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }
}
