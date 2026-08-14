<?php

use Illuminate\Support\Facades\Schedule;

// Rotation leaves a retired row behind every time. Nobody reports a meeting from a
// month ago, so past that point they are only taking up space.
Schedule::command('ble:prune-identities')->daily();
