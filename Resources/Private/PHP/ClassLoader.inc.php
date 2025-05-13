<?php

if (! class_exists('source-span\SourceSpan')) {
    spl_autoload_register(function ($class) {
        if (0 !== strpos($class, 'SourceSpan\\')) {
            return;
        }

        $subClass = substr($class, strlen('SourceSpan\\'));
        $path = __DIR__ . '/source-span/src/' . str_replace('\\', '/', $subClass) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    });
}

if (! class_exists('uri-interfaces\Encoder')) {
    spl_autoload_register(function ($class) {
        if (0 !== strpos($class, 'League\Uri\\')) {
            return;
        }

        $subClass = substr($class, strlen('League\Uri\\'));
        $path = __DIR__ . '/uri-interfaces/' . str_replace('\\', '/', $subClass) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    });
}

if (! class_exists('uri\Uri')) {
    spl_autoload_register(function ($class) {
        if (0 !== strpos($class, 'League\Uri\\')) {
            return;
        }

        $subClass = substr($class, strlen('League\Uri\\'));
        $path = __DIR__ . '/uri/' . str_replace('\\', '/', $subClass) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    });
}

if (! class_exists('scssphp\src\Version')) {
    spl_autoload_register(function ($class) {
        if (0 !== strpos($class, 'ScssPhp\ScssPhp\\')) {
            // Not a ScssPhp class
            return;
        }

        $subClass = substr($class, strlen('ScssPhp\ScssPhp\\'));
        $path = __DIR__ . '/scssphp/src/' . str_replace('\\', '/', $subClass) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    });
}
