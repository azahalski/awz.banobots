<?php
return [
    'ui.entity-selector' => [
        'value' => [
            'entities' => [
                [
                    'entityId' => 'awzbanobots-user',
                    'provider' => [
                        'moduleId' => 'awz.banobots',
                        'className' => '\\Awz\\Banobots\\Access\\EntitySelectors\\User'
                    ],
                ],
                [
                    'entityId' => 'awzbanobots-group',
                    'provider' => [
                        'moduleId' => 'awz.banobots',
                        'className' => '\\Awz\\Banobots\\Access\\EntitySelectors\\Group'
                    ],
                ],
            ]
        ],
        'readonly' => true,
    ]
];