<?php

use Illuminate\Support\Facades\Schedule;

// Repairs Stripe seat counts left stale by a failed update (see the command).
// Production runs the scheduler as its own container (task-management-docker).
Schedule::command('billing:reconcile-seats')->hourly()->withoutOverlapping();
