<?php

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess']['wsscss'] = \WapplerSystems\WsScss\Hooks\RenderPreProcessorHook::class . '->renderPreProcessorProc';


// Content-hash bookkeeping cache for the compiled SCSS. NonFlushableFileBackend
// ignores a generic cache:flush -- see its docblock for why: a blanket flush
// would otherwise force every visitor hitting the site concurrently right
// after a deploy/flush to recompile the whole @import tree from scratch, even
// though the .scss sources didn't actually change. Use flushByTag('scss')
// (wsscss:flush CLI command, or the Backend's "Flush SCSS cache" clear-cache
// toolbar action) when a .scss source actually changed.
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss'] ?? null)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ws_scss'] = [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => \WapplerSystems\WsScss\Cache\Backend\NonFlushableFileBackend::class,
        'options' => [
            'defaultLifetime' => 0,
        ]
    ];
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['wsscss'] = ['WapplerSystems\\WsScss\\ViewHelpers'];

if (!class_exists(\ScssPhp\ScssPhp\Version::class, true)) {
    $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('ws_scss');
    require_once $extPath . 'Resources/Private/PHP/ClassLoader.inc.php';
}
