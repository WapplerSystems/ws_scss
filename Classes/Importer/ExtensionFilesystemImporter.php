<?php

namespace WapplerSystems\WsScss\Importer;

use League\Uri\Contracts\UriInterface;
use League\Uri\Uri;
use ScssPhp\ScssPhp\Importer\Importer;
use ScssPhp\ScssPhp\Importer\ImporterResult;
use ScssPhp\ScssPhp\Syntax;
use ScssPhp\ScssPhp\Util\Path;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use WapplerSystems\WsScss\Compiler;

/**
 * An importer that loads files from a load path on the filesystem.
 */
final class ExtensionFilesystemImporter extends Importer
{

    private string $visualImportPath;

    public function __construct(string $visualImportPath)
    {
        $this->visualImportPath = $visualImportPath;
    }

    public function canonicalize(UriInterface $url): ?UriInterface
    {

        // Resolve potential back paths manually using PathUtility::getCanonicalPath,
        // but make sure we do not break out of TYPO3 application path using GeneralUtility::getFileAbsFileName
        // Also resolve EXT: paths if given
        $url = str_replace('ext:', 'EXT:', $url);
        if (!str_contains($url, 'EXT:')) {
            return null;
        }

        $isTypo3Absolute = (str_starts_with($url, 'EXT:')) || PathUtility::isAbsolutePath($url);
        $fileName = $isTypo3Absolute ? $url : $this->visualImportPath . '/' . $url;
        $full = GeneralUtility::getFileAbsFileName(PathUtility::getCanonicalPath($fileName));
        // The API forces us to check the existence of files paths, with or without url.
        // We must only return a string if the file to be imported actually exists.
        $hasExtension = (bool) preg_match('/[.]s?css$/', $url);
        if (
            is_file($file = pathinfo($full, PATHINFO_DIRNAME) . '/' . basename($full) . '.scss') ||
            is_file($file = pathinfo($full, PATHINFO_DIRNAME) . '/_' . basename($full) . '.scss') ||
            ($hasExtension && is_file($file = $full))
        ) {
            Compiler::$CURRENT_LOAD_PATH = dirname($file);
            return Uri::new('file://'.$file);
        }

        return null;
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
        return $this->loadPath ?? '<extension file importer>';
    }
}
