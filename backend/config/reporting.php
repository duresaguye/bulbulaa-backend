<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reporting deadlines
    |--------------------------------------------------------------------------
    |
    | Number of days after the end of a reporting period before a missing report
    | is shown as overdue. Periods follow the Ethiopian calendar.
    |
    */

    'weekly_grace_days' => (int) env('REPORTING_WEEKLY_GRACE_DAYS', 2),

    'monthly_grace_days' => (int) env('REPORTING_MONTHLY_GRACE_DAYS', 5),

    'annual_grace_days' => (int) env('REPORTING_ANNUAL_GRACE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | At-risk threshold
    |--------------------------------------------------------------------------
    |
    | A target is "on track" when its actual result has reached what its monthly
    | allocation expected by now, "at risk" when it has reached at least this
    | percentage of that expectation, and "behind plan" below it.
    |
    */

    'at_risk_threshold' => (float) env('REPORTING_AT_RISK_THRESHOLD', 80),

];
