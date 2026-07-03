<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Full-time weekly ordinary hours threshold (overtime applies above this)
    |--------------------------------------------------------------------------
    */
    'full_time_weekly_hours' => (float) env('PAYROLL_FULL_TIME_WEEKLY_HOURS', 38),

    /*
    |--------------------------------------------------------------------------
    | Leave accrual — hours of leave earned per hours worked (Cleaning Award typical)
    |--------------------------------------------------------------------------
    */
    'sick_leave_hours_per_worked' => (float) env('PAYROLL_SICK_LEAVE_HOURS_PER_WORKED', 35),
    'annual_leave_hours_per_worked' => (float) env('PAYROLL_ANNUAL_LEAVE_HOURS_PER_WORKED', 35),
    'annual_leave_loading_percent' => (float) env('PAYROLL_ANNUAL_LEAVE_LOADING_PERCENT', 17.5),

    /*
    |--------------------------------------------------------------------------
    | Only include clock time from HR-approved weekly timesheets in pay runs
    |--------------------------------------------------------------------------
    */
    'require_approved_timesheets' => (bool) env('PAYROLL_REQUIRE_APPROVED_TIMESHEETS', true),

    /*
    |--------------------------------------------------------------------------
    | Award rate schedule effective date (PDF: 1 July 2025)
    |--------------------------------------------------------------------------
    */
    'default_rates_effective_from' => env('PAYROLL_DEFAULT_RATES_EFFECTIVE_FROM', '2025-07-01'),

    /*
    |--------------------------------------------------------------------------
    | Ordinary weekday window (6am–6pm local display timezone)
    |--------------------------------------------------------------------------
    */
    'weekday_ordinary_start_hour' => 6,
    'weekday_ordinary_end_hour' => 18,

];
