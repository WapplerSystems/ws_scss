<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "ws_scss".
 *
 * Auto generated 07-02-2014 02:04
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF['ws_scss'] = [
    'title' => 'SASS compiler for TYPO3',
    'description' => 'Compiles scss files to CSS files.',
    'category' => 'fe',
    'author' => 'Sven Wappler',
    'author_email' => 'typo3YYYY@wappler.systems',
    'author_company' => 'WapplerSystems',
    'version' => '1.2.0',
    'state' => 'alpha',
    'clearCacheOnLoad' => false,
    'constraints' => [
        'depends' => [
            'php' => '7.4.0-7.4.99',
            'typo3' => '10.4.0-11.1.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'WapplerSystems\\WsScss\\' => 'Classes',
            'ScssPhp\\ScssPhp\\' => 'Resources/Private/scssphp/src/'
        ]
    ]
];
