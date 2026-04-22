<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Persists multipart registration files on the employee_registration disk and writes
 * relative paths on the Employee model / JSON rows.
 */
class RegistrationDocumentStorage
{
    private const DISK = 'employee_registration';

    public function attach(Request $request, Employee $employee, string $companySlug): void
    {
        if ($request->allFiles() === []) {
            return;
        }

        $prefix = trim($companySlug, '/').'/'.$employee->public_id;

        if ($request->hasFile('profile_photo')) {
            /** @var UploadedFile $file */
            $file = $request->file('profile_photo');
            $employee->profile_photo_path = $file->store($prefix, self::DISK);
        }

        if ($request->hasFile('police_check')) {
            $file = $request->file('police_check');
            $employee->police_check_path = $file->store($prefix, self::DISK);
        }

        if ($request->hasFile('fit_to_work')) {
            $file = $request->file('fit_to_work');
            $employee->fit_to_work_path = $file->store($prefix, self::DISK);
        }

        if ($request->hasFile('vehicle_insurance')) {
            $file = $request->file('vehicle_insurance');
            $employee->vehicle_insurance_path = $file->store($prefix, self::DISK);
        }

        $this->mergeKeyedFileArrayIntoJson(
            $request->file('id_document_upload'),
            $employee,
            'id_documents_json',
            'documentKey',
            $prefix,
        );

        $this->mergeKeyedFileArrayIntoJson(
            $request->file('licence_upload'),
            $employee,
            'licences_json',
            'id',
            $prefix,
        );

        $this->mergeKeyedFileArrayIntoJson(
            $request->file('insurance_upload'),
            $employee,
            'insurances_json',
            'id',
            $prefix,
        );

        $employee->save();
    }

    /**
     * @param  array<string, UploadedFile>|null  $uploads
     */
    private function mergeKeyedFileArrayIntoJson(
        ?array $uploads,
        Employee $employee,
        string $attribute,
        string $rowKeyField,
        string $prefix,
    ): void {
        if ($uploads === null || $uploads === []) {
            return;
        }

        /** @var array<int|string, mixed>|null $rows */
        $rows = $employee->{$attribute};
        if (! is_array($rows) || $rows === []) {
            return;
        }

        foreach ($rows as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $row[$rowKeyField] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (! isset($uploads[$key])) {
                continue;
            }
            $file = $uploads[$key];
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $row['storage_path'] = $file->store($prefix, self::DISK);
        }
        unset($row);

        $employee->{$attribute} = $rows;
    }
}
