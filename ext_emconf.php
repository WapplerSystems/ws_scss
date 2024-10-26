<?php

$EM_CONF['ws_scss'] = [
    'title' => 'SASS compiler for TYPO3',
    'description' => 'Compiles scss files to CSS files.',
    'category' => 'fe',
    'version' => '13.0.1',
    'state' => 'stable',
    'clearcacheonload' => 0,
    'author' => 'Sven Wappler',
    'author_email' => 'typo3YYYY@wappler.systems',
    'author_company' => 'WapplerSystems',
    'constraints' => [
        'depends' => [
            'php' => '8.0.0-8.3.99',
            'typo3' => '13.0.0-13.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
