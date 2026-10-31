<?php

// Afaan Oromoo for Filament's built-in text. Please review with a native speaker.

return [
    'checkbox_list' => [
        'required_description' => 'Yoo xiqqaate filannoo tokko filadhu.',
        'actions' => [
            'deselect_all' => [
                'label' => 'Hunda dhiisi',
            ],
            'select_all' => [
                'label' => 'Hunda filadhu',
            ],
        ],
    ],
    'date_time_picker' => [
        'month_select' => [
            'label' => 'Ji\'a',
        ],
        'year_input' => [
            'label' => 'Bara',
        ],
        'hour_input' => [
            'label' => 'Sa\'aatii',
        ],
        'minute_input' => [
            'label' => 'Daqiiqaa',
        ],
        'second_input' => [
            'label' => 'Sekondii',
        ],
    ],
    'file_upload' => [
        'placeholder' => 'Faayiliin hin olkaa\'amne',
        'actions' => [
            'download' => [
                'label' => 'Buusi',
            ],
            'open' => [
                'label' => 'Qooda haaraatti bani',
            ],
        ],
        'editor' => [
            'label' => 'Gulaalaa suuraa',
            'actions' => [
                'cancel' => [
                    'label' => 'Dhiisi',
                ],
                'drag_crop' => [
                    'label' => 'Haala harkisuu "murii"',
                ],
                'drag_move' => [
                    'label' => 'Haala harkisuu "socho\'i"',
                ],
                'flip_horizontal' => [
                    'label' => 'Suuraa gara gadiitti garagalchi',
                ],
                'flip_vertical' => [
                    'label' => 'Suuraa gara olitti garagalchi',
                ],
                'move_down' => [
                    'label' => 'Suuraa gadi buusi',
                ],
                'move_left' => [
                    'label' => 'Suuraa gara bitaatti socho\'i',
                ],
                'move_right' => [
                    'label' => 'Suuraa gara mirgaatti socho\'i',
                ],
                'move_up' => [
                    'label' => 'Suuraa ol kaasi',
                ],
                'reset' => [
                    'label' => 'Haaromsi',
                ],
                'rotate_left' => [
                    'label' => 'Suuraa gara bitaatti naanneessi',
                ],
                'rotate_right' => [
                    'label' => 'Suuraa gara mirgaatti naanneessi',
                ],
                'set_aspect_ratio' => [
                    'label' => 'Walmadaalina :ratio tti kaa\'i',
                ],
                'save' => [
                    'label' => 'Olkaa\'i',
                ],
                'zoom_100' => [
                    'label' => 'Suuraa 100% tti guddisi',
                ],
                'zoom_in' => [
                    'label' => 'Guddisi',
                ],
                'zoom_out' => [
                    'label' => 'Xiqqeessi',
                ],
            ],
            'fields' => [
                'height' => [
                    'label' => 'Dheerina',
                    'unit' => 'px',
                ],
                'rotation' => [
                    'label' => 'Naannessuu',
                    'unit' => 'deg',
                ],
                'width' => [
                    'label' => 'Bal\'ina',
                    'unit' => 'px',
                ],
                'x_position' => [
                    'label' => 'X',
                    'unit' => 'px',
                ],
                'y_position' => [
                    'label' => 'Y',
                    'unit' => 'px',
                ],
            ],
            'aspect_ratios' => [
                'label' => 'Walmadaalinaa',
                'no_fixed' => [
                    'label' => 'Bilisa',
                ],
            ],
            'svg' => [
                'messages' => [
                    'confirmation' => 'Faayilii SVG gulaaluun yeroo guddisamu qulqullina hir\'isuu waan danda\'uuf hin gorfamu.\\n Dhugumaan itti fufuu barbaadda?',
                    'disabled' => 'Faayilii SVG gulaaluun yeroo guddisamu qulqullina hir\'isuu waan danda\'uuf dhorkameera.',
                ],
            ],
        ],
    ],
    'radio' => [
        'boolean' => [
            'true' => 'Eeyyee',
            'false' => 'Lakki',
        ],
    ],
    'repeater' => [
        'reordering' => [
            'item' => 'Wanta',
            'position' => ':label, iddoo :position kan :count',
            'moved' => ':label iddoo :position kan :count tti socho\'eera',
        ],
        'columns' => [
            'actions' => [
                'label' => 'Tarkaanfiiwwan',
            ],
            'reorder' => [
                'label' => 'Irra deebi\'ii tarreessi',
            ],
        ],
        'actions' => [
            'add' => [
                'label' => 'Gara :label tti dabali',
            ],
            'add_between' => [
                'label' => 'Gidduu galchi',
            ],
            'delete' => [
                'label' => 'Haqi',
            ],
            'clone' => [
                'label' => 'Garagalchi',
            ],
            'reorder' => [
                'label' => 'Socho\'i',
            ],
            'move_down' => [
                'label' => 'Gadi buusi',
            ],
            'move_up' => [
                'label' => 'Ol kaasi',
            ],
            'collapse' => [
                'label' => 'Dhoksi',
            ],
            'expand' => [
                'label' => 'Bal\'isi',
            ],
            'collapse_all' => [
                'label' => 'Hunda dhoksi',
            ],
            'expand_all' => [
                'label' => 'Hunda bal\'isi',
            ],
        ],
    ],
    'select' => [
        'actions' => [
            'clear' => [
                'label' => 'Filannoo haqi',
            ],
            'create_option' => [
                'label' => 'Uumi',
                'modal' => [
                    'heading' => 'Uumi',
                    'actions' => [
                        'create' => [
                            'label' => 'Uumi',
                        ],
                        'create_another' => [
                            'label' => 'Uumiitii kan biraa uumi',
                        ],
                    ],
                ],
            ],
            'edit_option' => [
                'label' => 'Gulaali',
                'modal' => [
                    'heading' => 'Gulaali',
                    'actions' => [
                        'save' => [
                            'label' => 'Olkaa\'i',
                        ],
                    ],
                ],
            ],
            'remove_option' => [
                'label' => ':label haqi',
            ],
        ],
        'boolean' => [
            'true' => 'Eeyyee',
            'false' => 'Lakki',
        ],
        'error_message' => 'Filannoowwan fe\'amuu hin dandeenye. Maaloo irra deebi\'ii yaali.',
        'loading_message' => 'Fe\'aa jira...',
        'max_items_message' => ':count qofatu filatamuu danda\'a.',
        'no_options_message' => 'Filannoon hin jiru.',
        'no_search_results_message' => 'Filannoon barbaacha kee waliin wal simu hin jiru.',
        'placeholder' => 'Filannoo tokko filadhu',
        'required_description' => 'Filannoo tokko filadhu.',
        'searching_message' => 'Barbaadaa jira...',
        'search_label' => 'Barbaadi',
        'search_prompt' => 'Barbaaduuf barreessuu jalqabi',
    ],
    'text_input' => [
        'actions' => [
            'copy' => [
                'label' => 'Garagalchi',
                'message' => 'Garagalfameera',
            ],
            'hide_password' => [
                'label' => 'Jecha darbii dhoksi',
            ],
            'show_password' => [
                'label' => 'Jecha darbii agarsiisi',
            ],
        ],
    ],
];
