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
    'version' => '12.0.3',
    'state' => 'stable',
    'clearcacheonload' => 0,
    'author' => 'Sven Wappler',
    'author_email' => 'typo3YYYY@wappler.systems',
    'author_company' => 'WapplerSystems',
    'constraints' => [
        'depends' => [
            'php' => '8.0.0-8.2.99',
            'typo3' => '12.0.0-12.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
