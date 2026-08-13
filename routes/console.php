<?php

use Illuminate\Support\Facades\Schedule;

// Detections nobody matched are invisible to users, so this only keeps the table
// from growing with rows that can never pair.
Schedule::command('meetings:prune')->hourly();
