<?php

namespace WapplerSystems\WsScss\Compiler;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ScssResolver
{

    /**
     * @var FrontendInterface
     */
    private $cache;

    private static $defaultOutputDir = 'typo3temp/assets/css/';

    private static $visitedFiles = [];

    public function __construct(FrontendInterface $cache) {
        $this->cache = $cache;
    }

    private static function calcCacheKey(string $cssRelativeFilename) {
        return hash('sha1', $cssRelativeFilename);
    }

    /**
     * Calculating content hash to detect changes
     *
     * @param string $scssFilename Existing scss file absolute path
     * @param string $vars
     * @return string
     */
    protected function calculateContentHash(string $scssFilename, string $vars = ''): string {
        if (\in_array($scssFilename, self::$visitedFiles, true)) {
            return '';
        }
        self::$visitedFiles[] = $scssFilename;

        $content = file_get_contents($scssFilename);
        $pathinfo = pathinfo($scssFilename);

        $hash = hash('sha1', $content);
        if ($vars !== '') {
            $hash = hash('sha1', $hash . $vars);
        } // hash variables too

        $imports = self::collectImports($content);
        foreach ($imports as $import) {
            $hashImport = '';


            if (file_exists($pathinfo['dirname'] . '/' . $import . '.scss')) {
                $hashImport = $this->calculateContentHash($pathinfo['dirname'] . '/' . $import . '.scss');
            } else {
                $parts = explode('/', $import);
                $filename = '_' . array_pop($parts);
                $parts[] = $filename;
                if (file_exists($pathinfo['dirname'] . '/' . implode('/', $parts) . '.scss')) {
                    $hashImport = $this->calculateContentHash($pathinfo['dirname'] . '/' . implode('/',
                            $parts) . '.scss');
                }
            }
            if ($hashImport !== '') {
                $hash = hash('sha1', $hash . $hashImport);
            }
        }

        return $hash;
    }

    /**
     * Collect all @import files in the given content.
     *
     * @param string $content
     * @return array
     */
    private static function collectImports(string $content): array {
        $matches = [];
        $imports = [];

        preg_match_all('/@import([^;]*);/', $content, $matches);

        foreach ($matches[1] as $importString) {
            $files = explode(',', $importString);

            array_walk($files, static function (string &$file) {
                $file = trim($file, " \t\n\r\0\x0B'\"");
            });

            $imports = array_merge($imports, $files);
        }

        return $imports;
    }

    public function resolve(string $file, $outputDir = null, $formatter = null, array $variables = [],  $showLineNumber = false, $useSourceMap = false, $outputFile = null, $inline = false): ?array {
        $sitePath = Environment::getPublicPath() . '/';
        $pathInfo = pathinfo($file);
        $filename = $pathInfo['filename'];
        if (empty($outputDir)) {
            $outputDir = self::$defaultOutputDir;
        }

        if ($outputFile !== null) {
            $outputDir = \dirname($outputFile);
            $filename = basename($outputFile);
        }

        $outputDir = (substr($outputDir, -1) === '/') ? $outputDir : $outputDir . '/';

        if (!strcmp(substr($outputDir, 0, 4), 'EXT:')) {
            [$extKey, $script] = explode('/', substr($outputDir, 4), 2);
            if ($extKey && ExtensionManagementUtility::isLoaded($extKey)) {
                $extPath = ExtensionManagementUtility::extPath($extKey);
                $outputDir = substr($extPath, \strlen($sitePath)) . $script;
            }
        }


        $scssFilename = GeneralUtility::getFileAbsFileName($file);

        // create filename - hash is important due to the possible
        // conflicts with same filename in different folders
        GeneralUtility::mkdir_deep($sitePath . $outputDir);
        $fileEnding = (substr($filename,-4) === '.css') ? '' : '.css';
        if ($outputFile === null) {
            $variablesCount = \count($variables);
            $variablesHash = $variablesCount > 0 ? hash('md5', implode(',', $variables)) : null;
            $variablesHashString = $variablesCount > 0 ? '_' . $variablesHash : '';
            $fileNameOutputString = ($outputDir === self::$defaultOutputDir) ? '_' . hash('sha1', $file) : $variablesHashString;

            $cssRelativeFilename = $outputDir . $filename . $fileNameOutputString . $fileEnding;
        } else {
            $cssRelativeFilename = $outputDir . $filename . $fileEnding;
        }


        $cssFilename = $sitePath . $cssRelativeFilename;

        $cacheKey = self::calcCacheKey($cssRelativeFilename);
        $contentHash = $this->calculateContentHash($scssFilename, implode(',', $variables));
        if ($showLineNumber) {
            $contentHash .= 'l1';
        }
        if ($useSourceMap) {
            $contentHash .= 'sm';
        }
        $contentHash .= $formatter;

        $contentHashCache = '';
        if ($this->cache->has($cacheKey)) {
            $contentHashCache = $this->cache->get($cacheKey);
        }


        $css = $this->compile($scssFilename, $cssFilename, $contentHashCache, $contentHash, $cacheKey, $variables, $showLineNumber, $formatter, $cssRelativeFilename, $useSourceMap);
        // error
        if ($css === null) {
            return null;
        }
        if ($inline) {
            if ($css === '') {
                $css = file_get_contents($cssFilename);
            }
            return [$cssRelativeFilename, $css];
        }
        return [$cssRelativeFilename, null];
    }

    private function compile(string $scssFilename,
                       string $cssFilename,
                       string $contentHashCache,
                       string $contentHash,
                       string $cacheKey,
                       array $variables = [],
                       bool $showLineNumber = false,
                       $formatter = null,
                       $cssRelativeFilename = null,
                       bool $useSourceMap = false): ?string {
        $css = '';

        try {
            if ($contentHashCache === '' || $contentHashCache !== $contentHash) {
                $css = ScssCompiler::compileScss($scssFilename, $cssFilename, $variables, $showLineNumber, $formatter, $cssRelativeFilename, $useSourceMap);

                $this->cache->set($cacheKey, $contentHash, ['scss'], 0);
            }
            return $css;
        } catch (\Exception $ex) {
            DebugUtility::debug($ex->getMessage());

            /** @var $logger Logger */
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error($ex->getMessage());
        }
        return null;
    }

}
