<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('queue:work database --stop-when-empty --max-time=40 --tries=3 --timeout=30')->everyMinute()->withoutOverlapping(3);
Schedule::command('londra:retry-pending-notifications')->everyFiveMinutes()->withoutOverlapping(3);
Schedule::command('londra:retry-privacy-notifications')->everyFiveMinutes()->withoutOverlapping(3);
Schedule::command('londra:retention')->dailyAt('03:20')->timezone('Europe/London')->withoutOverlapping(30);
Schedule::command('queue:prune-failed --hours=168')->daily()->withoutOverlapping(10);
Schedule::command('londra:refresh-country-data')->weeklyOn(1, '04:20')->timezone('Europe/London')->withoutOverlapping(10);
