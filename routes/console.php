<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    DB::table('operation_heartbeats')->updateOrInsert(
        ['name' => 'scheduler'],
        ['last_success_at' => now()->utc(), 'last_error_code' => null, 'updated_at' => now()->utc()]
    );
})->name('scheduler-heartbeat')->everyMinute()->withoutOverlapping(3);

Schedule::command(
    'queue:work database --queue=high,default,low --stop-when-empty --max-time=45 --max-jobs=25 --memory=96 --sleep=1 --tries=1'
)->name('bounded-queue-worker')->everyMinute()->withoutOverlapping(3);
