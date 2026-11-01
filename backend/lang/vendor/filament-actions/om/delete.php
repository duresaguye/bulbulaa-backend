<?php

// Afaan Oromoo for Filament's built-in text. Please review with a native speaker.

return [
    'single' => [
        'label' => 'Haqi',
        'modal' => [
            'heading' => ':Label haqi',
            'actions' => [
                'delete' => [
                    'label' => 'Haqi',
                ],
            ],
        ],
        'notifications' => [
            'deleted' => [
                'title' => 'Haqameera',
            ],
        ],
    ],
    'multiple' => [
        'label' => 'Kan filatame haqi',
        'modal' => [
            'heading' => 'Kan filatame :label haqi',
            'actions' => [
                'delete' => [
                    'label' => 'Haqi',
                ],
            ],
        ],
        'notifications' => [
            'deleted' => [
                'title' => 'Haqameera',
            ],
            'deleted_partial' => [
                'title' => ':total keessaa :count haqameera',
                'missing_authorization_failure_message' => ':count haquuf hayyama hin qabdu.',
                'missing_processing_failure_message' => ':count haqamuu hin dandeenye.',
            ],
            'deleted_none' => [
                'title' => 'Haquun hin milkoofne',
                'missing_authorization_failure_message' => ':count haquuf hayyama hin qabdu.',
                'missing_processing_failure_message' => ':count haqamuu hin dandeenye.',
            ],
        ],
    ],
];
