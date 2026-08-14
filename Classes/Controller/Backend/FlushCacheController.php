<?php
declare(strict_types=1);

namespace WapplerSystems\WsScss\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend route target for the "Flush SCSS cache" clear-cache toolbar
 * action (see FlushCacheActionEvent / Configuration/Backend/Routes.php).
 */
class FlushCacheController
{
    public function flushCache(ServerRequestInterface $request): ResponseInterface
    {
        GeneralUtility::makeInstance(CacheManager::class)->getCache('ws_scss')->flushByTag('scss');

        return new Response();
    }
}
