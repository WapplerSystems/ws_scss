<?php

$EM_CONF['ws_scss'] = [
    'title' => 'SASS compiler for TYPO3',
    'description' => 'Compiles scss files to CSS files.',
    'category' => 'fe',
    'version' => '14.0.0',
    'state' => 'stable',
    'clearcacheonload' => 0,
    'author' => 'Sven Wappler',
    'author_email' => 'typo3YYYY@wappler.systems',
    'author_company' => 'WapplerSystems',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.4.99',
            'typo3' => '14.0.0',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
