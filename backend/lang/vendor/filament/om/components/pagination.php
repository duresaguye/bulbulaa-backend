<?php

// Afaan Oromoo for Filament's built-in text. Please review with a native speaker.

return [
    'label' => 'Qajeelfama fuulaa',
    'overview' => '{1} Bu\'aa 1 agarsiisaa jira|[2,*] Bu\'aa :total keessaa :first hanga :last agarsiisaa jira',
    'fields' => [
        'records_per_page' => [
            'label' => 'Fuula tokkoon',
            'options' => [
                'all' => 'Hunda',
            ],
        ],
    ],
    'actions' => [
        'first' => [
            'label' => 'Jalqaba',
        ],
        'go_to_page' => [
            'label' => 'Gara fuula :page deemi',
        ],
        'last' => [
            'label' => 'Dhuma',
        ],
        'next' => [
            'label' => 'Itti aanu',
        ],
        'previous' => [
            'label' => 'Kan duraa',
        ],
    ],
];
