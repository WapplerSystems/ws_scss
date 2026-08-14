<?php
declare(strict_types=1);

namespace WapplerSystems\WsScss\Backend\Event;

use TYPO3\CMS\Backend\Backend\Event\ModifyClearCacheActionsEvent;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adds a manual "Flush SCSS cache" action to the Backend's clear-cache
 * toolbar menu. Needed because NonFlushableFileBackend deliberately keeps
 * the ws_scss cache out of reach of the generic "Clear all caches" action
 * -- this is the ad-hoc escape hatch for editors/admins when a .scss
 * source changed outside of a deploy (a project's composer post-update
 * hook would normally already run the wsscss:flush command).
 */
class FlushCacheActionEvent
{
    public const ITEM_KEY = 'flushWsScssCache';

    #[AsEventListener(identifier: 'ws_scss/flush-cache-action')]
    public function addClearCacheActions(ModifyClearCacheActionsEvent $event): void
    {
        if (!$this->isCacheItemAvailable()) {
            return;
        }
        $cacheActionConfiguration = $this->getCacheActionConfiguration();
        if (!empty($cacheActionConfiguration)) {
            $event->addCacheAction($cacheActionConfiguration);
            $event->addCacheActionIdentifier(self::ITEM_KEY);
        }
    }

    protected function isCacheItemAvailable(): bool
    {
        return $this->getBackendUser()->isAdmin()
            || ($this->getBackendUser()->getTSConfig()['options.']['clearCache.'][self::ITEM_KEY] ?? false);
    }

    /**
     * @return string[]
     */
    protected function getCacheActionConfiguration(): array
    {
        try {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            return [
                'id' => self::ITEM_KEY,
                'title' => 'LLL:EXT:ws_scss/Resources/Private/Language/locallang.xlf:flushCache',
                'description' => 'LLL:EXT:ws_scss/Resources/Private/Language/locallang.xlf:flushCache.description',
                'href' => (string)$uriBuilder->buildUriFromRoute(self::ITEM_KEY),
                // Stock TYPO3 icon, same one the core "Clear all caches" /
                // "Clear pages caches" actions already use.
                'iconIdentifier' => 'actions-bolt-alt',
            ];
        } catch (RouteNotFoundException $e) {
            return [];
        }
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
