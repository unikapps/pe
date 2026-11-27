<?php

use Illuminate\Support\Facades\Schedule;

// Fallback for webhooks that never arrive.
Schedule::command('generations:sync-stale')->everyMinute()->withoutOverlapping();
