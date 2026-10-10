<?php

return [
    // a waiting report whose photo never arrived is removed after this many days
    'waiting_max_age_days' => 30,

    // bytes the phone sends per request; the server refuses anything larger
    'chunk_size' => 262144,

    // same ceilings as the online form (StoreDiseaseReportRequest): 10 MB photo, 5 MB voice note
    'photo_max_bytes' => 10 * 1024 * 1024,
    'audio_max_bytes' => 5 * 1024 * 1024,
    'audio_max_seconds' => 35,

    // an upload left idle this long is dropped
    'session_hours' => 24,
    'max_open_sessions' => 10,
    'requests_per_minute' => 300,
];
