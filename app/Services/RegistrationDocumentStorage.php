<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Persists multipart registration files on the employee_registration disk and writes
 * relative paths on the Employee model / JSON rows.
 */
class RegistrationDocumentStorage
{
    private const DISK = 'employee_registration';

    /** @var list<string> */
    private const JSON_FILE_REFERENCE_KEYS = ['storage_path', 'uri', 'localUri'];

    public function attach(Request $request, Employee $employee, string $companySlug): void
    {
        $this->applyRemovals($request, $employee);

        $hasUploads = $request->allFiles() !== [];

        if (! $hasUploads) {
            if ($employee->isDirty()) {
                $employee->save();
            }

            return;
        }

        $prefix = trim($companySlug, '/').'/'.$employee->public_id;

        if ($request->hasFile('profile_photo')) {
            /** @var UploadedFile $file */
            $file = $request->file('profile_photo');
            $this->deleteStoredPath($employee->profile_photo_path);
            $employee->profile_photo_path = $file->store($prefix, self::DISK);
        }

        if ($request->hasFile('police_check')) {
            $file = $request->file('police_check');
            $this->deleteStoredPath($employee->police_check_path);
            $employee->police_check_path = $file->store($prefix, self::DISK);
        }

        if ($request->hasFile('fit_to_work')) {
            $file = $request->file('fit_to_work');
            $this->deleteStoredPath($employee->fit_to_work_path);
            $employee->fit_to_work_path = $file->store($prefix, self::DISK);
        }

        if ($request->hasFile('vehicle_insurance')) {
            $file = $request->file('vehicle_insurance');
            $this->deleteStoredPath($employee->vehicle_insurance_path);
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

    private function applyRemovals(Request $request, Employee $employee): void
    {
        if ($request->boolean('remove_profile_photo') && ! $request->hasFile('profile_photo')) {
            $this->deleteStoredPath($employee->profile_photo_path);
            $employee->profile_photo_path = null;
        }

        if ($request->boolean('remove_police_check') && ! $request->hasFile('police_check')) {
            $this->deleteStoredPath($employee->police_check_path);
            $employee->police_check_path = null;
        }

        if ($request->boolean('remove_fit_to_work') && ! $request->hasFile('fit_to_work')) {
            $this->deleteStoredPath($employee->fit_to_work_path);
            $employee->fit_to_work_path = null;
        }

        if ($request->boolean('remove_vehicle_insurance') && ! $request->hasFile('vehicle_insurance')) {
            $this->deleteStoredPath($employee->vehicle_insurance_path);
            $employee->vehicle_insurance_path = null;
        }

        $this->removeFromKeyedJson(
            $request->input('remove_id_document_upload'),
            $request->file('id_document_upload'),
            $employee,
            'id_documents_json',
            'documentKey',
        );

        $this->removeFromKeyedJson(
            $request->input('remove_licence_upload'),
            $request->file('licence_upload'),
            $employee,
            'licences_json',
            'id',
        );

        $this->removeFromKeyedJson(
            $request->input('remove_insurance_upload'),
            $request->file('insurance_upload'),
            $employee,
            'insurances_json',
            'id',
        );
    }

    /**
     * @param  array<string, mixed>|null  $removals
     * @param  array<string, UploadedFile>|null  $uploads
     */
    private function removeFromKeyedJson(
        ?array $removals,
        ?array $uploads,
        Employee $employee,
        string $attribute,
        string $rowKeyField,
    ): void {
        if ($removals === null || $removals === []) {
            return;
        }

        /** @var array<int|string, mixed>|null $rows */
        $rows = $employee->{$attribute};
        if (! is_array($rows) || $rows === []) {
            return;
        }

        $changed = false;

        foreach ($rows as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $this->rowKey($row, $rowKeyField);
            if ($key === '') {
                continue;
            }
            if ($this->uploadPresentForKey($uploads, $key)) {
                continue;
            }
            if (! $this->removalRequested($removals, $key)) {
                continue;
            }
            $this->deleteStoredPath($row['storage_path'] ?? null);
            $this->clearJsonFileReferences($row);
            $changed = true;
        }
        unset($row);

        if ($changed) {
            $this->assignJsonAttribute($employee, $attribute, $rows);
        }
    }

    /**
     * @param  array<string, mixed>  $removals
     */
    private function removalRequested(array $removals, string $key): bool
    {
        foreach ($this->keyCandidates($key) as $candidate) {
            if (! array_key_exists($candidate, $removals)) {
                continue;
            }
            if (filter_var($removals[$candidate], FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, UploadedFile>|null  $uploads
     */
    private function uploadPresentForKey(?array $uploads, string $key): bool
    {
        if ($uploads === null || $uploads === []) {
            return false;
        }

        foreach ($this->keyCandidates($key) as $candidate) {
            if (isset($uploads[$candidate])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int|string>
     */
    private function keyCandidates(string $key): array
    {
        $candidates = [$key];
        if (ctype_digit($key)) {
            $candidates[] = (int) $key;
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowKey(array $row, string $rowKeyField): string
    {
        $raw = $row[$rowKeyField] ?? null;

        return is_scalar($raw) ? trim((string) $raw) : '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function clearJsonFileReferences(array &$row): void
    {
        foreach (self::JSON_FILE_REFERENCE_KEYS as $key) {
            unset($row[$key]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private function assignJsonAttribute(Employee $employee, string $attribute, array $rows): void
    {
        $employee->forceFill([
            $attribute => json_decode(json_encode($rows, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
        ]);
    }

    private function deleteStoredPath(mixed $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
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

        $changed = false;

        foreach ($rows as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $this->rowKey($row, $rowKeyField);
            if ($key === '') {
                continue;
            }
            $file = $this->uploadForKey($uploads, $key);
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $this->deleteStoredPath($row['storage_path'] ?? null);
            $this->clearJsonFileReferences($row);
            $row['storage_path'] = $file->store($prefix, self::DISK);
            $changed = true;
        }
        unset($row);

        if ($changed) {
            $this->assignJsonAttribute($employee, $attribute, $rows);
        }
    }

    /**
     * @param  array<string, UploadedFile>  $uploads
     */
    private function uploadForKey(array $uploads, string $key): ?UploadedFile
    {
        foreach ($this->keyCandidates($key) as $candidate) {
            if (! isset($uploads[$candidate])) {
                continue;
            }
            $file = $uploads[$candidate];
            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }
}
