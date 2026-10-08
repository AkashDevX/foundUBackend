<?php

namespace App\Support;

use App\Models\IncidentReport;
use Carbon\Carbon;

/**
 * Turns the in-app incident form (same questions as the workplace JotForm)
 * into columns plus the sections shown to an administrator.
 */
final class IncidentReportForm
{
    private const ENFORCE_REQUIRED = true;

    /**
     * @param  array<string, mixed>  $answers
     * @param  list<string>|null  $allowedSites  Active work-location names. Null skips the list check.
     * @return array{error: string}|array{record: array<string, mixed>, details: array<string, mixed>}
     */
    public static function prepare(array $answers, ?array $allowedSites = null): array
    {
        $text = static function (string $key) use ($answers): string {
            $value = $answers[$key] ?? '';

            return trim(is_scalar($value) ? (string) $value : '');
        };

        $required = [
            'site' => 'Site',
            'location' => 'Location of the incident',
            'occurred_on' => 'Date of incident',
            'occurred_time' => 'Time of incident',
            'reported_on' => 'Date incident was reported',
            'reported_time' => 'Time incident was reported',
            'reported_to_first' => 'Reported to first name',
            'reported_to_last' => 'Reported to last name',
            'witnesses' => 'Witnesses',
            'person_type' => 'Type of person or party',
            'person_first' => 'First name of the person involved',
            'person_last' => 'Last name of the person involved',
            'addr_line1' => 'Street address',
            'employment_type' => 'Employment type',
            'phone_area' => 'Phone area code',
            'phone_number' => 'Phone number',
            'gender' => 'Gender',
            'person_age' => 'Approximate age',
            'injured' => 'Was the person injured',
            'first_aid' => 'Was first aid provided',
            'medical_treatment' => 'Was medical treatment required',
            'incident_type' => 'What type of incident occurred',
            'property_equipment_damage' => 'Was there any property or equipment damage',
            'injury_aggravated' => 'Was an existing injury or illness aggravated',
            'before_incident' => 'What were you doing immediately before the incident',
            'description' => 'Description of the incident',
            'personally_experienced' => 'What did you personally see, hear, or experience',
            'ongoing_concern' => 'Did the incident form part of a previous or ongoing concern',
            'impact_description' => 'Description of injury, illness, or impact',
            'continued_working' => 'Did you continue working after the incident',
            'safety_risk' => 'Was there an immediate safety risk',
            'trained' => 'Were you trained to perform the task involved',
            'procedure_followed' => 'Was the required procedure being followed',
            'cctv' => 'Are there CCTV cameras covering the area',
            'weather' => 'Weather conditions',
            'property_lost' => 'Was any personal or company property lost or damaged',
            'declaration_first' => 'Declaration first name',
            'declaration_last' => 'Declaration last name',
            'declaration_on' => 'Declaration date',
            'signature' => 'Signature',
        ];

        if (self::ENFORCE_REQUIRED) {
            foreach ($required as $key => $label) {
                if ($text($key) === '') {
                    return ['error' => $label.' is required.'];
                }
            }

            if (is_array($allowedSites)) {
                $allowed = [];
                foreach ($allowedSites as $site) {
                    $name = trim((string) $site);
                    if ($name !== '') {
                        $allowed[] = $name;
                    }
                }
                if ($allowed === []) {
                    return ['error' => 'No work sites are available.'];
                }
                if (! in_array($text('site'), $allowed, true)) {
                    return ['error' => 'Choose a site from the list.'];
                }
            }
        }

        $typeSlug = $text('incident_type');
        if (! isset(IncidentReport::TYPES[$typeSlug])) {
            if (self::ENFORCE_REQUIRED) {
                return ['error' => 'Choose the type of incident.'];
            }
            $typeSlug = 'other';
        }
        if (self::ENFORCE_REQUIRED && $typeSlug === 'other' && $text('incident_type_other') === '') {
            return ['error' => 'Please specify the type of incident.'];
        }

        $outcomes = $answers['outcomes'] ?? [];
        $outcomeLabels = [];
        if (is_array($outcomes)) {
            foreach ($outcomes as $outcome) {
                $label = trim((string) $outcome);
                if ($label !== '') {
                    $outcomeLabels[] = $label;
                }
            }
        }
        if (self::ENFORCE_REQUIRED && $outcomeLabels === []) {
            return ['error' => 'What was the outcome of the incident is required.'];
        }

        $age = $text('person_age');
        if (self::ENFORCE_REQUIRED && (! ctype_digit($age) || (int) $age > 120)) {
            return ['error' => 'Enter an approximate age from 0 to 120.'];
        }

        $fromStrokes = self::signatureDrawing([
            'width' => $answers['signature_width'] ?? 320,
            'height' => $answers['signature_height'] ?? 160,
            'strokes' => $answers['signature_strokes'] ?? [],
        ]);
        $drawing = $fromStrokes['signed']
            ? $fromStrokes
            : self::signatureDrawing($answers['signature'] ?? null);
        if (self::ENFORCE_REQUIRED && ! $drawing['signed']) {
            return ['error' => 'Sign the declaration before submitting.'];
        }

        $occurredAt = self::dateTime($text('occurred_on'), $text('occurred_time'));
        $reportedAt = self::dateTime($text('reported_on'), $text('reported_time'));
        if (self::ENFORCE_REQUIRED && ($occurredAt === null || $reportedAt === null)) {
            return ['error' => 'Enter a valid date and time.'];
        }

        if (self::ENFORCE_REQUIRED) {
            $latest = DisplayTimezone::now()->addMinutes(5);
            if ($occurredAt->greaterThan($latest) || $reportedAt->greaterThan($latest)) {
                return ['error' => 'Incident and report times cannot be in the future.'];
            }
        }

        $personName = trim($text('person_first').' '.$text('person_last'));
        $reportedTo = trim($text('reported_to_first').' '.$text('reported_to_last'));
        $address = self::address($text('addr_line1'), $text('addr_line2'), $text('addr_city'), $text('addr_state'), $text('addr_postcode'));
        $adultAddress = self::address($text('adult_addr_line1'), $text('adult_addr_line2'), $text('adult_addr_city'), $text('adult_addr_state'), $text('adult_addr_postcode'));
        $phone = trim($text('phone_area').' '.$text('phone_number'));

        $rows = [
            'INCIDENT DETAILS' => [
                'Site' => $text('site'),
                'Location of the incident' => $text('location'),
                'Date of incident' => $text('occurred_on'),
                'Time of incident' => $text('occurred_time'),
                'Date incident was reported' => $text('reported_on'),
                'Time incident was reported' => $text('reported_time'),
                'Reported to' => $reportedTo,
                'Witnesses' => $text('witnesses'),
            ],
            'PERSON INVOLVED' => [
                'Type of person or party' => $text('person_type'),
                'Name' => $personName,
                'Address' => $address,
                'Employment type' => $text('employment_type'),
                'Phone number' => $phone,
                'Gender' => $text('gender') === 'other' ? 'Other' : $text('gender'),
                'Approximate age' => $age,
                'Was the person injured?' => $text('injured'),
                'Was first aid provided?' => $text('first_aid'),
                'Was medical treatment required?' => $text('medical_treatment'),
            ],
            'IF THE INVOLVED PERSON IS UNDER 18' => [
                'Accompanied by an adult' => $text('accompanied'),
                'Name of accompanying adult' => trim($text('adult_first').' '.$text('adult_last')),
                'Relationship to the minor' => $text('adult_relationship'),
                'Address of accompanying adult' => $adultAddress,
                'Contact number' => $text('adult_phone'),
            ],
            'TYPE OF INCIDENT' => [
                'What type of incident occurred?' => IncidentReport::TYPES[$typeSlug],
                'If others (please specify)' => $text('incident_type_other'),
                'Property or equipment damage' => $text('property_equipment_damage'),
                'Existing injury or illness aggravated' => $text('injury_aggravated'),
            ],
            'WHAT HAPPENED?' => [
                'Immediately before the incident' => $text('before_incident'),
                'Description of the incident' => $text('description'),
                'What you personally saw, heard, or experienced' => $text('personally_experienced'),
                'Anything anyone said or did' => $text('anyone_said'),
                'Part of a previous or ongoing concern' => $text('ongoing_concern'),
                'Details of that concern' => $text('ongoing_concern_details'),
                'Escalator, travelator or lift number' => $text('lift_number'),
            ],
            'EFFECT OF THE INCIDENT' => [
                'Outcome' => implode(', ', $outcomeLabels),
                'If other, please specify' => $text('outcome_other'),
                'Description of injury, illness, or impact' => $text('impact_description'),
                'Continued working after the incident' => $text('continued_working'),
                'If you stopped working' => $text('stopped_details'),
            ],
            'WORKPLACE BEHAVIOUR / SUPPORT' => [
                'Behaviour is ongoing' => $text('behaviour_ongoing'),
                'Reported previously' => $text('previously_reported'),
                'Previous report details' => $text('previous_report_details'),
                'Previously reported (follow-up)' => $text('previously_reported_followup'),
                'Additional information' => $text('behaviour_notes'),
            ],
            'IMMEDIATE SAFETY INFORMATION' => [
                'Immediate safety risk' => $text('safety_risk'),
                'Description of the safety risk' => $text('safety_risk_details'),
                'Action taken immediately after' => $text('immediate_action'),
            ],
            'TRAINING / PROCEDURE' => [
                'Trained to perform the task' => $text('trained'),
                'Required procedure being followed' => $text('procedure_followed'),
                'Training or procedure comments' => $text('training_comments'),
            ],
            'SHIFT INFORMATION' => [
                'Where in the shift' => $text('shift_when'),
                'Proportion of shift worked before the incident' => $text('shift_proportion'),
            ],
            'ATTACHMENTS / EVIDENCE' => [
                'CCTV cameras covering the area' => $text('cctv'),
                'CCTV details' => $text('cctv_details'),
                'Summary of CCTV footage' => $text('cctv_summary'),
                'Footage reviewed by' => trim($text('cctv_reviewer_first').' '.$text('cctv_reviewer_last')),
                'Reviewer position' => $text('cctv_reviewer_position'),
                'Date footage reviewed' => $text('cctv_reviewed_on'),
            ],
            'MEDICAL INFORMATION (VOLUNTARY)' => [
                'Pre-existing condition' => $text('preexisting'),
                'Condition details' => $text('preexisting_details'),
                'Medication' => $text('medication'),
                'Medication details' => $text('medication_details'),
            ],
            'WEATHER CONDITIONS' => [
                'Weather' => $text('weather'),
            ],
            'PROPERTY LOSS OR DAMAGE' => [
                'Property lost or damaged' => $text('property_lost'),
                'Owner' => $text('property_owner') === 'other'
                    ? trim('Other: '.$text('property_owner_other'))
                    : $text('property_owner'),
                'Description of damaged property' => $text('property_description'),
                'Nature of the damage' => $text('damage_nature'),
                'Estimated value' => $text('damage_value'),
                'Additional comments' => $text('property_comments'),
            ],
            'EMPLOYEE DECLARATION' => [
                'Full name' => trim($text('declaration_first').' '.$text('declaration_last')),
                'Signature' => $drawing['signed'] && $drawing['strokes'] === [] ? 'Signed in the app' : '',
                'Date' => $text('declaration_on'),
            ],
        ];

        $sections = [];
        foreach ($rows as $title => $pairs) {
            $visible = [];
            foreach ($pairs as $label => $value) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
                $visible[] = ['label' => $label, 'value' => $value];
            }
            if ($visible !== []) {
                $sections[] = ['title' => $title, 'rows' => $visible];
            }
        }

        return [
            'record' => [
                'incident_type' => $typeSlug,
                'site_name' => $text('site') !== '' ? $text('site') : 'Not specified',
                'location' => $text('location') !== '' ? $text('location') : 'Not specified',
                'occurred_at' => $occurredAt?->utc(),
                'summary' => $text('description') !== '' ? $text('description') : 'Not specified',
                'witnesses' => $text('witnesses'),
                'reported_to' => $reportedTo,
            ],
            'details' => [
                'incident_type_other' => $text('incident_type_other'),
                'person_name' => $personName,
                'signature_strokes' => $drawing['strokes'],
                'signature_width' => $drawing['width'],
                'signature_height' => $drawing['height'],
                'sections' => $sections,
            ],
        ];
    }

    /**
     * @return array{signed: bool, width: int, height: int, strokes: list<list<array{x: float, y: float}>>}
     */
    private static function signatureDrawing(mixed $raw): array
    {
        $empty = ['signed' => false, 'width' => 320, 'height' => 160, 'strokes' => []];
        if ($raw === 'drawn') {
            return ['signed' => true, 'width' => 320, 'height' => 160, 'strokes' => []];
        }

        $decoded = null;
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        }
        if (! is_array($decoded)) {
            return $empty;
        }

        $width = (int) ($decoded['width'] ?? 320);
        $height = (int) ($decoded['height'] ?? 160);
        $width = max(1, min($width, 2000));
        $height = max(1, min($height, 2000));
        $source = $decoded['strokes'] ?? [];
        if (! is_array($source)) {
            return $empty;
        }

        $strokes = [];
        foreach (array_slice($source, 0, 80) as $stroke) {
            if (! is_array($stroke)) {
                continue;
            }
            $points = [];
            foreach (array_slice($stroke, 0, 500) as $point) {
                if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) {
                    continue;
                }
                $points[] = [
                    'x' => round(max(0, min((float) $point['x'], $width)), 1),
                    'y' => round(max(0, min((float) $point['y'], $height)), 1),
                ];
            }
            if ($points !== []) {
                $strokes[] = $points;
            }
        }

        return [
            'signed' => $strokes !== [],
            'width' => $width,
            'height' => $height,
            'strokes' => $strokes,
        ];
    }

    private static function dateTime(string $date, string $time): ?Carbon
    {
        if ($date === '' || $time === '') {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, DisplayTimezone::name());
        } catch (\Throwable) {
            return null;
        }

        return $parsed === false ? null : $parsed;
    }

    private static function address(string $line1, string $line2, string $city, string $state, string $postcode): string
    {
        return implode(', ', array_values(array_filter(
            [$line1, $line2, $city, $state, $postcode],
            static fn (string $part): bool => $part !== '',
        )));
    }
}
