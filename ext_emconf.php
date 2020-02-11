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
	'shy' => 0,
	'version' => '1.1.13',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Sven Wappler',
	'author_email' => 'typo3YYYY@wappler.systems',
	'author_company' => 'WapplerSystems',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => [
		'depends' => [
            'php' => '7.2.0-7.4.99',
			'typo3' => '9.5.0-10.4.99',
        ],
		'conflicts' => [
        ],
		'suggests' => [
        ],
    ],
	'suggests' => [
    ],
];
