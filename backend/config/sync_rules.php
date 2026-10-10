<?php

return [
    // a synced record is turned away when its own date, or the phone's time, is older than this
    'max_age_days' => 7,

    // ...or when the phone's time is further ahead of the server's than this
    'max_clock_ahead_minutes' => 10,
];
