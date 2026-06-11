<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed attachment MIME types
    |--------------------------------------------------------------------------
    |
    | Validation uses the `mimetypes:` rule (content-based, not extension), so
    | these are real MIME types. Grouped for clarity; the controller flattens
    | them into a single list passed to the validator.
    |
    */
    'allowed_mimetypes' => [
        'image' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/zip',
            'application/x-rar-compressed',
            'application/vnd.rar',
        ],
        'video' => [
            'video/mp4',
            'video/quicktime',
            'video/webm',
            'video/x-msvideo',
            'video/x-matroska',
        ],
    ],

    // Max upload size per file, in kilobytes (100 MB — accommodates screen recordings).
    'max_size_kb' => 102400,
];
