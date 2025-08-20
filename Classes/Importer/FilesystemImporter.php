<?php

/**
 * SCSSPHP
 *
 * @copyright 2012-2020 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://scssphp.github.io/scssphp
 */

namespace WapplerSystems\WsScss\Importer;

use League\Uri\Contracts\UriInterface;
use ScssPhp\ScssPhp\Importer\ImportContext;
use ScssPhp\ScssPhp\Importer\Importer;
use ScssPhp\ScssPhp\Importer\ImporterResult;
use ScssPhp\ScssPhp\Importer\ImportUtil;
use ScssPhp\ScssPhp\Syntax;
use ScssPhp\ScssPhp\Util\Path;
use TYPO3\CMS\Core\Utility\DebugUtility;
use WapplerSystems\WsScss\Compiler;

/**
 * An importer that loads files from a load path on the filesystem.
 */
final class FilesystemImporter extends Importer
{
    /**
     * The path relative to which this importer looks for files.
     *
     * If this is `null`, this importer will _only_ load absolute `file:` URLs
     * and URLs relative to the current file.
     */
    private ?string $loadPath;

    public function __construct(?string $loadPath = null)
    {
        $this->loadPath = $loadPath !== null ? Path::absolute($loadPath) : null;
    }

    public function canonicalize(UriInterface $url): ?UriInterface
    {
        //DebugUtility::debug($url, 'FilesystemImporter canonicalize');

        if ($url->getScheme() === 'file') {

            //DebugUtility::debug('file scheme', 'FilesystemImporter canonicalize');

            $resolved = ImportUtil::resolveImportPath(Path::fromUri($url));
        } elseif ($url->getScheme() !== null) {
            return null;
        } elseif ($this->loadPath !== null) {
            $path = $this->normalizePath(Path::join($this->loadPath, Path::fromUri($url)));
            //DebugUtility::debug('loadPath: ' . $this->loadPath. ' path: '.$path);
            if (ImportContext::isFromImport()) {

                //DebugUtility::debug(ImportContext::getCanonicalizeContext()->getContainingUrl(),' ImportContext 2');

                $originUrl = ImportContext::getCanonicalizeContext()->getContainingUrl();
                //DebugUtility::debug(dirname(Path::fromUri($originUrl)));

                $path = Path::join(dirname(Path::fromUri($originUrl)), $url->getPath());
                //DebugUtility::debug($path, 'new path');


            }
            $resolved = ImportUtil::resolveImportPath($path);
        } else {
            return null;
        }
        //DebugUtility::debug('cannot resolve', 'FilesystemImporter canonicalize');

        if ($resolved === null) {
            return null;
        }
        //DebugUtility::debug(Path::toUri(Path::canonicalize($resolved))->toString(), 'FilesystemImporter resolved');

        return Path::toUri(Path::canonicalize($resolved));
    }

    public function load(UriInterface $url): ?ImporterResult
    {
        $path = Path::fromUri($url);
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \Exception("Could not read file $path");
        }

        return new ImporterResult($content, Syntax::forPath($path), $url);
    }

    public function couldCanonicalize(UriInterface $url, UriInterface $canonicalUrl): bool
    {
        if ($url->getScheme() !== 'file' && $url->getScheme() !== null) {
            return false;
        }

        if ($canonicalUrl->getScheme() !== 'file') {
            return false;
        }

        $basename = basename((string) $url);
        $canonicalBasename = basename((string) $canonicalUrl);

        if (!str_starts_with($basename, '_') && str_starts_with($canonicalBasename, '_')) {
            $canonicalBasename = substr($canonicalBasename, 1);
        }

        return $basename === $canonicalBasename || $basename === Path::withoutExtension($canonicalBasename);
    }

    public function __toString(): string
    {
        return $this->loadPath ?? '<absolute file importer>';
    }

    private function normalizePath(string $path): string {
        $parts = explode('/', $path);
        $absolutes = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        return '/' . implode('/', $absolutes);
    }

}
