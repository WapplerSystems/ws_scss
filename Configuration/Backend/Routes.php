<?php

return [
    \WapplerSystems\WsScss\Backend\Event\FlushCacheActionEvent::ITEM_KEY => [
        'path' => '/tx_ws_scss_flushcache/clear',
        'target' => \WapplerSystems\WsScss\Controller\Backend\FlushCacheController::class . '::flushCache',
    ],
];
