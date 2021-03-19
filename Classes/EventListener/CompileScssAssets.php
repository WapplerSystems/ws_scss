<?php

namespace WapplerSystems\WsScss\EventListener;

use \TYPO3\CMS\Core\Page\Event\BeforeStylesheetsRenderingEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\WsScss\Compiler\ScssResolver;

class CompileScssAssets
{

    public function __invoke(BeforeStylesheetsRenderingEvent $event): void {
        if ($event->isInline()) {
            return;
        }
        $assetCollector = $event->getAssetCollector();
        $assets = $assetCollector->getStyleSheets($event->isPriority());

        foreach ($assets as $identifier => $asset) {
            if (!empty($asset['source'])) {
                $pathInfo = pathinfo($asset['source']);
                if ($pathInfo['extension'] === 'scss') {
                    $assetCollector->removeStyleSheet($identifier);
                    /** @var ScssResolver $scssResolver */
                    $scssResolver = GeneralUtility::makeInstance(ScssResolver::class);
                    $resolved = $scssResolver->resolve(
                        $asset['source'],
                        null,
                        'WapplerSystems\WsScss\Formatter\Autoprefixer',
                        [],
                        false,
                        false,
                        null,
                        false);
                    if ($resolved === null) {
                        // error remove
                        return;
                    }
                    $assetCollector->addStyleSheet($identifier, $resolved[0], $asset['attributes'], $asset['options']);
                }
            }
        }
    }
}
