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

$EM_CONF[$_EXTKEY] = [
	'title' => 'SASS compiler for TYPO3',
	'description' => 'Compiles scss files to CSS files.',
	'category' => 'fe',
	'shy' => 0,
	'version' => '1.1.11',
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
            'php' => '7.0.0-7.2.99',
			'typo3' => '8.7.0-9.5.99',
        ],
		'conflicts' => [
        ],
		'suggests' => [
        ],
    ],
	'_md5_values_when_last_written' => 'a:7:{s:12:"ext_icon.gif";s:4:"31d4";s:17:"ext_localconf.php";s:4:"be03";s:24:"ext_typoscript_setup.txt";s:4:"b038";s:40:"Classes/Hooks/RenderPreProcessorHook.php";s:4:"7467";s:37:"Classes/Utility/Lessphp/LessCache.php";s:4:"d4e5";s:38:"Classes/Utility/Lessphp/LessParser.php";s:4:"3c72";s:14:"doc/manual.sxw";s:4:"6d4c";}',
	'suggests' => [
    ],
];
