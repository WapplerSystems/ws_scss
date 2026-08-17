<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'SASS compiler for TYPO3',
    'description' => 'Compiles SCSS to CSS at runtime with caching, TypoScript variables and EXT: import support',
    'category' => 'fe',
    'version' => '14.1.0',
    'state' => 'stable',
    'clearcacheonload' => 0,
    'author' => 'Sven Wappler',
    'author_email' => 'typo3YYYY@wappler.systems',
    'author_company' => 'WapplerSystems',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '14.0.0-14.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
