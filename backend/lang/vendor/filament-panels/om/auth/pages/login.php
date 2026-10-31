<?php

// Afaan Oromoo for Filament's built-in text. Please review with a native speaker.

return [
    'title' => 'Seeni',
    'heading' => 'Seeni',
    'actions' => [
        'register' => [
            'before' => 'yookaan',
            'label' => 'herrega galmaa\'i',
        ],
        'request_password_reset' => [
            'label' => 'Jecha darbii irraanfattee?',
        ],
    ],
    'form' => [
        'email' => [
            'label' => 'Teessoo imeelii',
        ],
        'password' => [
            'label' => 'Jecha darbii',
        ],
        'remember' => [
            'label' => 'Na yaadadhu',
        ],
        'actions' => [
            'authenticate' => [
                'label' => 'Seeni',
            ],
        ],
    ],
    'multi_factor' => [
        'heading' => 'Eenyummaa kee mirkaneessi',
        'subheading' => 'Seenuu itti fufuuf eenyummaa kee mirkaneessuu qabda.',
        'form' => [
            'provider' => [
                'label' => 'Akkamiin mirkaneessuu barbaadda?',
            ],
            'actions' => [
                'authenticate' => [
                    'label' => 'Seenuu mirkaneessi',
                ],
            ],
        ],
    ],
    'messages' => [
        'failed' => 'Odeeffannoon kun galmee keenya waliin wal hin simu.',
    ],
    'notifications' => [
        'throttled' => [
            'title' => 'Yaaliin seenuu baay\'ateera',
            'body' => 'Maaloo sekondii :seconds booda irra deebi\'ii yaali.',
        ],
    ],
];
