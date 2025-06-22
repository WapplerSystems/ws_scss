<?php

namespace WapplerSystems\WsScss;

use League\Uri\Uri;
use ScssPhp\ScssPhp\Importer\Importer;

class ImportResolver
{

    public function __construct(
        readonly array $importers
    ) {
    }

    public function resolveImportPath(string $importPath, mixed $dirname) : ?Uri
    {
        $url = Uri::new($importPath);

        /** @var Importer $importer */
        foreach ($this->importers as $importer) {

            $resolved = $importer->canonicalize($url);
            if ($resolved !== null) {
                return Uri::new($resolved);
            }
        }

        return null;
    }
}
