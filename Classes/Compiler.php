<?php

declare(strict_types=1);

namespace WapplerSystems\WsScss;

use Psr\EventDispatcher\EventDispatcherInterface;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\WsScss\Event\AfterScssCompilationEvent;

class Compiler
{

    /**
     * Compile a SASS string by writing it to a temp file and delegating to compileFile().
     *
     * @param string $scssContent Raw SCSS source
     * @param array $variables SCSS variables
     * @param string|null $cssFilename Target CSS file name (auto-generated if null)
     * @param bool $useSourceMap Enable inline source maps
     * @param OutputStyle $outputStyle CSS output style
     * @return string Path to compiled CSS file
     * @throws FileDoesNotExistException
     * @throws NoSuchCacheException
     * @throws SassException
     */
    public static function compileSassString(string $scssContent, array $variables, ?string $cssFilename = null, bool $useSourceMap = false, OutputStyle $outputStyle = OutputStyle::COMPRESSED): string
    {
        $hash = sha1($scssContent);
        $tempScssFilePath = 'typo3temp/assets/scss/' . $hash . '.scss';
        $absoluteTempScssFilePath = GeneralUtility::getFileAbsFileName($tempScssFilePath);

        if (!file_exists($absoluteTempScssFilePath)) {
            GeneralUtility::mkdir_deep(dirname($absoluteTempScssFilePath));
            GeneralUtility::writeFile($absoluteTempScssFilePath, $scssContent);
        }

        return self::compileFile($tempScssFilePath, $variables, $cssFilename, $useSourceMap, $outputStyle);
    }

    /**
     * Compile a SCSS file to CSS with caching, variable injection and vendor import support.
     *
     * @param string $scssFilePath Path to SCSS file (EXT: or absolute)
     * @param array $variables SCSS variables to inject
     * @param string|null $cssFilePath Target CSS file path (auto-generated if null)
     * @param bool $useSourceMap Enable inline source maps
     * @param OutputStyle $outputStyle CSS output style
     * @return string Path to compiled CSS file
     * @throws FileDoesNotExistException
     * @throws NoSuchCacheException
     * @throws SassException
     */
    public static function compileFile(string $scssFilePath, array $variables, ?string $cssFilePath = null, bool $useSourceMap = false, OutputStyle $outputStyle = OutputStyle::COMPRESSED): string
    {
        $scssFilePath = GeneralUtility::getFileAbsFileName($scssFilePath);
        $variablesHash = hash('md5', implode(',', $variables) . $scssFilePath);
        $sitePath = Environment::getPublicPath() . '/';

        if (!file_exists($scssFilePath)) {
            throw new FileDoesNotExistException($scssFilePath);
        }

        if ($cssFilePath === null) {
            $pathInfo = pathinfo($scssFilePath);
            $filename = $pathInfo['filename'];
            $outputDir = 'typo3temp/assets/css/';

            $outputDir = str_ends_with($outputDir, '/') ? $outputDir : $outputDir . '/';
            if (!strcmp(substr($outputDir, 0, 4), 'EXT:')) {
                [$extKey, $script] = explode('/', substr($outputDir, 4), 2);
                if ($extKey && ExtensionManagementUtility::isLoaded($extKey)) {
                    $extPath = ExtensionManagementUtility::extPath($extKey);
                    $outputDir = substr($extPath, \strlen($sitePath)) . $script;
                }
            }

            $cssFilePath = $outputDir . $filename . ($variablesHash ? '_' . $variablesHash : '') . '.css';
        }

        /** @var FileBackend $cache */
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('ws_scss');

        // Include cssFilePath in cache key so different outputfile targets
        // (e.g. theme.css vs themeSkarupke.css) get separate cache entries
        $cacheKey = hash('sha1', $scssFilePath . '|' . $cssFilePath);
        $calculatedContentHash = self::calculateContentHash($scssFilePath, $variables);
        $calculatedContentHash .= md5($cssFilePath);
        if ($useSourceMap) {
            $calculatedContentHash .= 'sm';
        }
        $calculatedContentHash .= $outputStyle->value;

        if ($cache->has($cacheKey)) {
            $contentHashCache = $cache->get($cacheKey);
            if ($contentHashCache === $calculatedContentHash) {
                // Verify the CSS file actually exists before returning from cache
                $absoluteCssPath = GeneralUtility::getFileAbsFileName($cssFilePath);
                if ($absoluteCssPath !== '' && file_exists($absoluteCssPath)) {
                    return $cssFilePath;
                }
            }
        }

        $parser = new \ScssPhp\ScssPhp\Compiler();
        $parser->setOutputStyle($outputStyle);

        // Add the SCSS file's directory as import path for relative imports
        $parser->addImportPath(dirname($scssFilePath));

        // Add a callable resolver for EXT: prefixed imports
        $parser->addImportPath(function (string $path): ?string {
            if (str_starts_with($path, 'EXT:')) {
                $resolved = GeneralUtility::getFileAbsFileName($path);
                if ($resolved !== '' && file_exists($resolved)) {
                    return $resolved;
                }
                if (!str_ends_with($path, '.scss')) {
                    $resolved = GeneralUtility::getFileAbsFileName($path . '.scss');
                    if ($resolved !== '' && file_exists($resolved)) {
                        return $resolved;
                    }
                }
            }
            return null;
        });

        // Add vendor directory as import path so SCSS files can use
        // @import "vendor-name/package-name/..." without fragile relative paths
        foreach (self::getAdditionalImportPaths() as $importPath) {
            $parser->addImportPath($importPath);
        }

        if ($useSourceMap) {
            $parser->setSourceMap(\ScssPhp\ScssPhp\Compiler::SOURCE_MAP_INLINE);

            $parser->setSourceMapOptions([
                'sourceMapBasepath' => $sitePath,
                'sourceMapRootpath' => '/',
            ]);
        }

        // Build SCSS source: variable declarations followed by file import.
        // Variables are injected as SCSS code so expressions referencing other
        // variables (e.g. "$line-height-base - .25") are evaluated in context.
        //
        // Two-phase injection:
        // - Phase 1 (prepend): Literals and expressions referencing only our own
        //   TypoScript variables → placed BEFORE import to override !default
        // - Phase 2 (append): Values referencing external SCSS variables (e.g.
        //   Bootstrap's $cyan, $white) → placed AFTER import where they exist
        //
        // Transitive dependencies: if a variable references another variable
        // that is in the append phase, it must also be appended.

        // Pass 1: Categorize variables as prepend or append
        $appendSet = [];
        foreach ($variables as $name => $value) {
            $strValue = (string)$value;
            if ($strValue === '') {
                continue;
            }
            if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_-]*)/', $strValue, $refs)) {
                foreach ($refs[1] as $refName) {
                    if (!array_key_exists($refName, $variables)) {
                        $appendSet[$name] = true;
                        break;
                    }
                }
            }
        }

        // Pass 2: Propagate — variables referencing append-phase variables must also append
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($variables as $name => $value) {
                if (isset($appendSet[$name])) {
                    continue;
                }
                $strValue = (string)$value;
                if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_-]*)/', $strValue, $refs)) {
                    foreach ($refs[1] as $refName) {
                        if (isset($appendSet[$refName])) {
                            $appendSet[$name] = true;
                            $changed = true;
                            break;
                        }
                    }
                }
            }
        }

        // Pass 3: Build prepend and append source
        $prependVars = '';
        $appendVars = '';
        foreach ($variables as $name => $value) {
            $strValue = (string)$value;
            if ($strValue === '') {
                continue;
            }
            // Quote path values (start with / or ./) that don't contain SCSS
            // variable references — otherwise the leading / is parsed as division
            if (preg_match('#^\.{0,2}/#', $strValue) && !str_contains($strValue, '$')) {
                $strValue = "'" . str_replace("'", "\\'", $strValue) . "'";
            }
            if (isset($appendSet[$name])) {
                $appendVars .= '$' . $name . ': ' . $strValue . ";\n";
            } else {
                $prependVars .= '$' . $name . ': ' . $strValue . ";\n";
            }
        }
        $scssSource = $prependVars . '@import "' . $scssFilePath . '";' . "\n" . $appendVars;

        try {
            $result = $parser->compileString($scssSource);
            $cssCode = $result->getCss();

            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
            $event = $eventDispatcher->dispatch(
                new AfterScssCompilationEvent($cssCode)
            );
            $cssCode = $event->getCssCode();

            $cache->set($cacheKey, $calculatedContentHash, ['scss'], 0);
            GeneralUtility::mkdir_deep(dirname(GeneralUtility::getFileAbsFileName($cssFilePath)));
            GeneralUtility::writeFile(GeneralUtility::getFileAbsFileName($cssFilePath), $cssCode);
        } catch (\Exception $ex) {
            DebugUtility::debug($ex->getMessage());

            /** @var Logger $logger */
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error($ex->getMessage());
        }

        return $cssFilePath;
    }

    /**
     * Calculate a content hash to detect changes in the SCSS source tree.
     *
     * Recursively resolves @import statements and includes imported file
     * content in the hash. Supports partial files (_filename.scss) and
     * vendor import paths.
     *
     * @param string $scssFileName Absolute path to SCSS file
     * @param array $vars SCSS variables to include in hash
     * @param array $visitedFiles Already visited files (cycle protection)
     * @return string SHA1 content hash
     */
    public static function calculateContentHash(string $scssFileName, array $vars = [], array $visitedFiles = []): string
    {
        if (\in_array($scssFileName, $visitedFiles, true)) {
            return '';
        }
        $visitedFiles[] = $scssFileName;

        $content = file_get_contents($scssFileName);
        $pathInfo = pathinfo($scssFileName);

        $hash = hash('sha1', $content);
        if ($vars !== []) {
            $hash = hash('sha1', $hash . implode(',', $vars));
        }

        $imports = self::collectImports($content);
        foreach ($imports as $import) {
            $hashImport = '';

            if (file_exists($pathInfo['dirname'] . '/' . $import . '.scss')) {
                $hashImport = self::calculateContentHash($pathInfo['dirname'] . '/' . $import . '.scss', $vars, $visitedFiles);
            } else {
                // Try partial file (_filename.scss)
                $parts = explode('/', $import);
                $filename = '_' . array_pop($parts);
                $parts[] = $filename;
                if (file_exists($pathInfo['dirname'] . '/' . implode('/', $parts) . '.scss')) {
                    $hashImport = self::calculateContentHash(
                        $pathInfo['dirname'] . '/' . implode('/', $parts) . '.scss',
                        $vars,
                        $visitedFiles
                    );
                }
            }

            // Fallback: resolve via additional import paths (e.g. vendor/)
            if ($hashImport === '') {
                foreach (self::getAdditionalImportPaths() as $importBasePath) {
                    if (file_exists($importBasePath . '/' . $import . '.scss')) {
                        $hashImport = self::calculateContentHash($importBasePath . '/' . $import . '.scss', $vars, $visitedFiles);
                        break;
                    }
                    $parts = explode('/', $import);
                    $filename = '_' . array_pop($parts);
                    $parts[] = $filename;
                    if (file_exists($importBasePath . '/' . implode('/', $parts) . '.scss')) {
                        $hashImport = self::calculateContentHash($importBasePath . '/' . implode('/', $parts) . '.scss', $vars, $visitedFiles);
                        break;
                    }
                }
            }

            if ($hashImport !== '') {
                $hash = hash('sha1', $hash . $hashImport);
            }
        }

        return $hash;
    }

    /**
     * Get additional import paths for the SCSS compiler.
     *
     * Adds the Composer vendor directory so SCSS files can import
     * packages using vendor-relative paths (e.g. "vendor-name/package-name/...").
     *
     * @return array<string>
     */
    private static function getAdditionalImportPaths(): array
    {
        $paths = [];
        $vendorPath = Environment::getProjectPath() . '/vendor';
        if (is_dir($vendorPath)) {
            $paths[] = $vendorPath;
        }

        return $paths;
    }

    /**
     * Collect all @import files in the given content.
     *
     * @param string $content SCSS source content
     * @return array<string> List of import paths
     */
    private static function collectImports(string $content): array
    {
        $matches = [];
        $imports = [];

        preg_match_all('/@import([^;]*);/', $content, $matches);

        foreach ($matches[1] as $importString) {
            $files = explode(',', $importString);

            array_walk($files, function (string &$file) {
                $file = trim($file, " \t\n\r\0\x0B'\"");
            });

            $imports = array_merge($imports, $files);
        }

        return $imports;
    }
}
