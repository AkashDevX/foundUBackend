<?php

namespace App\Support;

use App\Services\FcmPushService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells assigned employees that a module with a due date is waiting.
 */
final class TrainingAssignmentNotice
{
    /**
     * @return array{title: string, body: string}
     */
    public static function message(string $moduleTitle, string $dueDate): array
    {
        $title = trim($moduleTitle);
        if ($title === '') {
            $title = 'Training';
        }

        $due = Carbon::parse($dueDate)->timezone(DisplayTimezone::name())->format('j F Y');

        return [
            'title' => 'Training assigned',
            'body' => $title.' has been assigned. Please complete it by '.$due.'.',
        ];
    }

    /**
     * @param  list<int>  $employeeIds
     */
    public static function notify(string $connection, string $moduleTitle, array $employeeIds, ?string $dueDate): void
    {
        $dueDate = trim((string) $dueDate);
        $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
        if ($connection === '' || $dueDate === '' || $employeeIds === []) {
            return;
        }

        $copy = self::message($moduleTitle, $dueDate);
        $previous = DB::getDefaultConnection();

        try {
            DB::setDefaultConnection($connection);
            app(FcmPushService::class)->sendToEmployees($employeeIds, [
                'title' => $copy['title'],
                'body' => $copy['body'],
                'data' => [
                    'type' => 'training_assigned',
                    'kind' => 'training_assigned',
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Training assignment notify failed: '.$e->getMessage());
        } finally {
            DB::setDefaultConnection($previous);
        }
    }
}
