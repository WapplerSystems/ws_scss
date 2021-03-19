<?php
defined('TYPO3_MODE') || die('Access denied.');

if (TYPO3_MODE === 'FE') {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess']['wsscss'] = \WapplerSystems\WsScss\Hooks\RenderPreProcessorHook::class . '->renderPreProcessorProc';
}


if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss'] = [];
}
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss']['backend'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss']['backend'] = \TYPO3\CMS\Core\Cache\Backend\FileBackend::class;
}
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss']['frontend'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss']['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
}
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss']['options'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss']['options'] = [
        'defaultLifetime' => 0,
    ];
}
