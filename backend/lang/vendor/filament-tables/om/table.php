<?php

// Afaan Oromoo for Filament's built-in text. Please review with a native speaker.

return [
    'column_manager' => [
        'heading' => 'Utubaalee',
        'position' => 'Iddoo :position kan :count.',
        'position_in_group' => 'Iddoo :position kan :count :group keessatti.',
        'reordered' => ':column iddoo :position kan :count tti socho\'eera.',
        'reordered_deferred' => ':column iddoo :position kan :count tti socho\'eera. Jijjiirama olkaa\'uuf utubaalee hojiirra oolchi.',
        'reordered_in_group' => ':column iddoo :position kan :count :group keessatti socho\'eera.',
        'reordered_in_group_deferred' => ':column iddoo :position kan :count :group keessatti socho\'eera. Jijjiirama olkaa\'uuf utubaalee hojiirra oolchi.',
        'actions' => [
            'apply' => [
                'label' => 'Utubaalee hojiirra oolchi',
            ],
            'move_up' => [
                'label' => ':column ol kaasi',
            ],
            'move_down' => [
                'label' => ':column gadi buusi',
            ],
            'reorder' => [
                'label' => 'Utubaa irra deebi\'ii tarreessi',
            ],
            'reset' => [
                'label' => 'Haaromsi',
            ],
        ],
    ],
    'columns' => [
        'actions' => [
            'label' => 'Tarkaanfii|Tarkaanfiiwwan',
        ],
        'editable' => [
            'saving' => 'Jijjiirama olkaa\'aa jira...',
            'saved' => 'Jijjiiramni olkaa\'ameera.',
            'failure' => 'Olkaa\'uun hin mirkanoofne. Irra deebi\'uu dura fuula haaromsi.',
            'unavailable' => 'Wanti kun kana booda gulaalamuu hin danda\'u.',
        ],
        'icon' => [
            'boolean' => [
                'true' => 'Eeyyee',
                'false' => 'Lakki',
            ],
        ],
        'select' => [
            'loading_message' => 'Fe\'aa jira...',
            'no_options_message' => 'Filannoon hin jiru.',
            'no_search_results_message' => 'Filannoon barbaacha kee waliin wal simu hin jiru.',
            'placeholder' => 'Filannoo tokko filadhu',
            'searching_message' => 'Barbaadaa jira...',
            'search_prompt' => 'Barbaaduuf barreessuu jalqabi',
        ],
        'text' => [
            'actions' => [
                'collapse_list' => ':count xiqqaa agarsiisi',
                'expand_list' => ':count dabalataa agarsiisi',
            ],
            'more_list_items' => 'fi :count dabalataa',
        ],
    ],
    'fields' => [
        'bulk_select_page' => [
            'label' => 'Tarkaanfii walii galaatiif hunda filadhu/dhiisi.',
        ],
        'bulk_select_record' => [
            'label' => 'Tarkaanfii walii galaatiif wanta :key filadhu/dhiisi.',
        ],
        'bulk_select_group' => [
            'label' => 'Tarkaanfii walii galaatiif garee :title filadhu/dhiisi.',
        ],
        'search' => [
            'label' => 'Barbaadi',
            'placeholder' => 'Barbaadi',
            'indicator' => 'Barbaadi',
        ],
    ],
    'summary' => [
        'heading' => 'Cuunfaa',
        'subheadings' => [
            'all' => ':label hunda',
            'group' => 'Cuunfaa :group',
            'page' => 'Fuula kana',
        ],
        'summarizers' => [
            'average' => [
                'label' => 'Giddugaleessa',
            ],
            'count' => [
                'label' => 'Lakkoofsa',
            ],
            'sum' => [
                'label' => 'Ida\'ama',
            ],
        ],
    ],
    'actions' => [
        'disable_reordering' => [
            'label' => 'Tarreessuu xumuri',
        ],
        'enable_reordering' => [
            'label' => 'Galmee irra deebi\'ii tarreessi',
        ],
        'reorder_record' => [
            'label' => 'Wanta :key irra deebi\'ii tarreessi',
        ],
        'move_record_up' => [
            'label' => ':label ol kaasi',
        ],
        'move_record_down' => [
            'label' => ':label gadi buusi',
        ],
        'filter' => [
            'label' => 'Calali',
        ],
        'group' => [
            'label' => 'Garee',
        ],
        'open_bulk_actions' => [
            'label' => 'Tarkaanfii walii galaa',
        ],
        'column_manager' => [
            'label' => 'Bulchaa utubaalee',
        ],
        'toggle_record_content' => [
            'label' => 'Wanta :key bal\'isi/dhoksi',
        ],
    ],
    'empty' => [
        'heading' => ':model hin jiru',
        'description' => 'Jalqabuuf :model uumi.',
    ],
    'filters' => [
        'actions' => [
            'apply' => [
                'label' => 'Calallii hojiirra oolchi',
            ],
            'remove' => [
                'label' => 'Calallii haqi',
            ],
            'remove_all' => [
                'label' => 'Calallii hunda haqi',
                'tooltip' => 'Calallii hunda haqi',
            ],
            'reset' => [
                'label' => 'Haaromsi',
            ],
        ],
        'heading' => 'Calalliiwwan',
        'indicator' => 'Calalliiwwan hojiirra jiran',
        'multi_select' => [
            'placeholder' => 'Hunda',
        ],
        'select' => [
            'placeholder' => 'Hunda',
            'relationship' => [
                'empty_option_label' => 'Homaa',
            ],
        ],
        'trashed' => [
            'label' => 'Galmee haqaman',
            'only_trashed' => 'Galmee haqaman qofa',
            'with_trashed' => 'Galmee haqaman waliin',
            'without_trashed' => 'Galmee haqaman malee',
        ],
    ],
    'grouping' => [
        'fields' => [
            'group' => [
                'label' => 'Gareen qoqqoodi',
            ],
            'direction' => [
                'label' => 'Kallattii garee',
                'options' => [
                    'asc' => 'Ol ka\'aa',
                    'desc' => 'Gad bu\'aa',
                ],
            ],
        ],
    ],
    'loading' => 'Fe\'aa jira...',
    'reorder_indicator' => 'Galmee harkisuun tartiibaan kaa\'i.',
    'reordering' => [
        'position' => ':label, iddoo :position kan :count.',
        'moved' => ':label iddoo :position kan :count tti socho\'eera.',
    ],
    'result_count' => '{0} Bu\'aan hin jiru|{1} Bu\'aa :count|[2,*] Bu\'aalee :count',
    'selection_indicator' => [
        'selected_count' => 'Galmeen 1 filatameera|Galmeewwan :count filatamaniiru',
        'actions' => [
            'select_all' => [
                'label' => 'Hunda :count filadhu',
            ],
            'deselect_all' => [
                'label' => 'Hunda dhiisi',
            ],
        ],
    ],
    'sorting' => [
        'fields' => [
            'column' => [
                'label' => 'Akkaataan tartiibessi',
            ],
            'direction' => [
                'label' => 'Kallattii tartiibaa',
                'options' => [
                    'asc' => 'Ol ka\'aa',
                    'desc' => 'Gad bu\'aa',
                ],
            ],
        ],
    ],
    'default_model_label' => 'galmee',
];
