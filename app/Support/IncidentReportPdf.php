<?php

namespace App\Support;

use App\Models\IncidentReport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Styled PDF exports for the admin incident register and a single report.
 */
final class IncidentReportPdf
{
    /**
     * @param  iterable<int, IncidentReport>  $reports
     */
    public static function renderRegister(iterable $reports, string $companyName, string $filter): string
    {
        $document = self::registerDocument($reports, $companyName, $filter);

        return self::renderView('admin.incidents.pdf-register', $document, 'landscape', $document['heading'], $companyName);
    }

    public static function render(IncidentReport $report, string $companyName): string
    {
        $document = self::document($report, $companyName);

        return self::renderView('admin.incidents.pdf', $document, 'portrait', $document['heading'], $companyName);
    }

    public static function filename(IncidentReport $report): string
    {
        return self::reference($report).'.pdf';
    }

    public static function registerFilename(string $filter): string
    {
        $filter = in_array($filter, ['open', 'new', 'acknowledged', 'resolved', 'all'], true) ? $filter : 'open';

        return 'incident-register-'.$filter.'.pdf';
    }

    public static function reference(IncidentReport $report): string
    {
        $id = (int) $report->id;
        if ($id <= 0) {
            return 'IR-DRAFT';
        }

        return 'IR-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(IncidentReport $report, string $companyName): array
    {
        $tone = self::statusTone((string) $report->status);
        $signature = $report->signatureDrawing();
        $signatureSrc = is_array($signature) ? self::signatureImage($signature) : null;

        return [
            'company' => $companyName !== '' ? $companyName : 'Organization',
            'logo' => self::logoImage(),
            'reference' => self::reference($report),
            'heading' => 'Incident report '.self::reference($report),
            'type' => $report->typeLabel(),
            'site' => trim((string) $report->site_name) !== '' ? (string) $report->site_name : 'Not specified',
            'location' => trim((string) $report->location) !== '' ? (string) $report->location : 'Not specified',
            'reporter' => $report->reporterName(),
            'occurred' => DisplayTimezone::formatDateTime($report->occurred_at),
            'submitted' => DisplayTimezone::formatDateTime($report->created_at),
            'generated' => DisplayTimezone::formatDateTime(DisplayTimezone::now()),
            'status' => (string) $report->status,
            'status_label' => $report->statusLabel(),
            'tone' => $tone,
            'sections' => self::sections($report, $signatureSrc),
            'review' => [
                'note' => trim((string) $report->admin_note),
                'reviewed_by' => trim((string) $report->reviewed_by),
                'reviewed_at' => $report->reviewed_at !== null
                    ? DisplayTimezone::formatDateTime($report->reviewed_at)
                    : '',
            ],
        ];
    }

    /**
     * @param  iterable<int, IncidentReport>  $reports
     * @return array<string, mixed>
     */
    public static function registerDocument(iterable $reports, string $companyName, string $filter): array
    {
        $filter = in_array($filter, ['open', 'new', 'acknowledged', 'resolved', 'all'], true) ? $filter : 'open';
        $labels = [
            'open' => 'Open reports',
            'new' => 'New reports',
            'acknowledged' => 'Acknowledged reports',
            'resolved' => 'Resolved reports',
            'all' => 'All reports',
        ];
        $items = $reports instanceof Collection ? $reports->values() : collect($reports)->values();
        $counts = ['new' => 0, 'acknowledged' => 0, 'resolved' => 0];
        $rows = [];

        foreach ($items as $report) {
            if (! $report instanceof IncidentReport) {
                continue;
            }
            $status = (string) $report->status;
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            $rows[] = [
                'reference' => self::reference($report),
                'submitted' => DisplayTimezone::formatDateTime($report->created_at),
                'occurred' => DisplayTimezone::formatDateTime($report->occurred_at),
                'reporter' => $report->reporterName(),
                'type' => $report->typeLabel(),
                'site' => trim((string) $report->site_name) !== '' ? (string) $report->site_name : '—',
                'status_label' => $report->statusLabel(),
                'tone' => self::statusTone($status),
            ];
        }

        return [
            'company' => $companyName !== '' ? $companyName : 'Organization',
            'logo' => self::logoImage(),
            'heading' => 'Incident register',
            'filter_label' => $labels[$filter],
            'generated' => DisplayTimezone::formatDateTime(DisplayTimezone::now()),
            'total' => count($rows),
            'counts' => $counts,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array{title: string, rows: list<array{label: string, value: string}>, photos: list<array{name: string, src: ?string}>, signature: ?string}>
     */
    private static function sections(IncidentReport $report, ?string $signatureSrc): array
    {
        $sawIncidentPhotos = false;
        $sawPropertyPhotos = false;
        $sawSignature = false;
        $sections = [];

        foreach ($report->presentationSections() as $section) {
            $title = (string) ($section['title'] ?? '');
            $rows = [];
            foreach ($section['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (($row['label'] ?? '') === 'Signature' && $signatureSrc !== null) {
                    continue;
                }
                $label = trim((string) ($row['label'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($label === '' || $value === '') {
                    continue;
                }
                $rows[] = ['label' => $label, 'value' => $value];
            }

            $photos = [];
            $signature = null;
            if ($title === 'ATTACHMENTS / EVIDENCE') {
                $sawIncidentPhotos = true;
                $photos = self::photos($report, 'incident');
            }
            if ($title === 'PROPERTY LOSS OR DAMAGE') {
                $sawPropertyPhotos = true;
                $photos = self::photos($report, 'property');
            }
            if ($title === 'EMPLOYEE DECLARATION' && $signatureSrc !== null) {
                $sawSignature = true;
                $signature = $signatureSrc;
            }

            if ($rows === [] && $photos === [] && $signature === null) {
                continue;
            }

            $sections[] = [
                'title' => $title,
                'rows' => $rows,
                'photos' => $photos,
                'signature' => $signature,
            ];
        }

        if (! $sawIncidentPhotos) {
            $photos = self::photos($report, 'incident');
            if ($photos !== []) {
                $sections[] = [
                    'title' => 'ATTACHMENTS / EVIDENCE',
                    'rows' => [],
                    'photos' => $photos,
                    'signature' => null,
                ];
            }
        }

        if (! $sawPropertyPhotos) {
            $photos = self::photos($report, 'property');
            if ($photos !== []) {
                $sections[] = [
                    'title' => 'PROPERTY LOSS OR DAMAGE',
                    'rows' => [],
                    'photos' => $photos,
                    'signature' => null,
                ];
            }
        }

        if (! $sawSignature && $signatureSrc !== null) {
            $sections[] = [
                'title' => 'EMPLOYEE DECLARATION',
                'rows' => [],
                'photos' => [],
                'signature' => $signatureSrc,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array{name: string, src: ?string}>
     */
    private static function photos(IncidentReport $report, string $group): array
    {
        $photos = [];
        foreach ($report->filesFor($group) as $photo) {
            $file = $photo['file'];
            $name = trim((string) ($file['name'] ?? ''));
            if ($name === '') {
                $name = $group === 'property' ? 'Property photo' : 'Incident photo';
            }
            $path = (string) ($file['path'] ?? '');
            $src = null;
            if ($path !== '' && Storage::disk('incident_attachments')->exists($path)) {
                $src = self::embeddedImage(Storage::disk('incident_attachments')->path($path));
            }
            $photos[] = ['name' => $name, 'src' => $src];
        }

        return $photos;
    }

    /**
     * @param  array{width: int, height: int, strokes: list<list<array{x: float, y: float}>>}  $drawing
     */
    private static function signatureImage(array $drawing): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $width = max(1, (int) $drawing['width']);
        $height = max(1, (int) $drawing['height']);
        $scale = 2;
        $canvasW = min(1600, $width * $scale);
        $canvasH = min(800, $height * $scale);
        $scaleX = $canvasW / $width;
        $scaleY = $canvasH / $height;

        $image = imagecreatetruecolor($canvasW, $canvasH);
        if ($image === false) {
            return null;
        }

        $paper = imagecolorallocate($image, 255, 252, 245);
        $ink = imagecolorallocate($image, 15, 23, 42);
        $rule = imagecolorallocate($image, 203, 186, 156);
        imagefilledrectangle($image, 0, 0, $canvasW, $canvasH, $paper);
        $baseY = (int) round($canvasH * 0.78);
        imageline($image, (int) round($canvasW * 0.08), $baseY, (int) round($canvasW * 0.92), $baseY, $rule);
        imagesetthickness($image, 4);

        foreach ($drawing['strokes'] as $stroke) {
            if (! is_array($stroke)) {
                continue;
            }
            $previous = null;
            foreach ($stroke as $point) {
                if (! is_array($point)) {
                    continue;
                }
                $x = (int) round(((float) ($point['x'] ?? 0)) * $scaleX);
                $y = (int) round(((float) ($point['y'] ?? 0)) * $scaleY);
                if ($previous !== null) {
                    imageline($image, $previous[0], $previous[1], $x, $y, $ink);
                } else {
                    imagefilledellipse($image, $x, $y, 4, 4, $ink);
                }
                $previous = [$x, $y];
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

    private static function logoImage(): ?string
    {
        $path = public_path('images/crulynk-logo.png');
        if (! is_file($path)) {
            return null;
        }

        return self::embeddedImage($path, 420);
    }

    private static function embeddedImage(string $absolutePath, int $maxEdge = 1400): ?string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $type = (int) $info[2];
        $supported = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if (defined('IMAGETYPE_WEBP')) {
            $supported[] = IMAGETYPE_WEBP;
        }
        if (! in_array($type, $supported, true) || $width < 1 || $height < 1) {
            return null;
        }

        $keepPng = in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)
            || (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP);
        $needsResize = $width > $maxEdge || $height > $maxEdge;
        if (! $needsResize && $type !== IMAGETYPE_WEBP && $type !== IMAGETYPE_GIF) {
            $binary = file_get_contents($absolutePath);
            if (! is_string($binary) || $binary === '') {
                return null;
            }

            return 'data:'.image_type_to_mime_type($type).';base64,'.base64_encode($binary);
        }

        $source = self::loadGdImage($absolutePath, $type);
        if ($source === null) {
            return null;
        }

        $scale = min($maxEdge / $width, $maxEdge / $height, 1);
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($targetW, $targetH);
        if ($resized === false) {
            imagedestroy($source);

            return null;
        }
        if ($keepPng) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $clear = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $targetW, $targetH, $clear);
            imagealphablending($resized, true);
        } else {
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefilledrectangle($resized, 0, 0, $targetW, $targetH, $white);
        }
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagedestroy($source);

        ob_start();
        if ($keepPng) {
            imagesavealpha($resized, true);
            imagepng($resized);
            $mime = 'image/png';
        } else {
            imagejpeg($resized, null, 82);
            $mime = 'image/jpeg';
        }
        $binary = ob_get_clean();
        imagedestroy($resized);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    /**
     * @return \GdImage|null
     */
    private static function loadGdImage(string $absolutePath, int $type)
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absolutePath) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absolutePath) : false,
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($absolutePath) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        return $image === false ? null : $image;
    }

    /**
     * @return array{bg: string, fg: string, bar: string}
     */
    private static function statusTone(string $status): array
    {
        return match ($status) {
            IncidentReport::STATUS_NEW => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'bar' => '#dc2626'],
            IncidentReport::STATUS_ACKNOWLEDGED => ['bg' => '#fef3c7', 'fg' => '#92400e', 'bar' => '#d97706'],
            default => ['bg' => '#d1fae5', 'fg' => '#065f46', 'bar' => '#059669'],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderView(string $view, array $data, string $orientation, string $title, string $author): string
    {
        $html = view($view, $data)->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use ($orientation): void {
            $font = $fontMetrics->getFont('DejaVu Sans');
            $text = 'Page '.$pageNumber.' of '.$pageCount;
            $size = 8;
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $pageWidth = $orientation === 'landscape' ? 841.89 : 595.28;
            $pageHeight = $orientation === 'landscape' ? 595.28 : 841.89;
            $canvas->text($pageWidth - 32 - $width, $pageHeight - 22, $text, $font, $size, [0.42, 0.47, 0.55]);
        });

        $dompdf->addInfo('Title', $title);
        $dompdf->addInfo('Author', $author !== '' ? $author : 'CruLynk');
        $dompdf->addInfo('Subject', 'Workplace incident report');

        return $dompdf->output();
    }
}
