<?php

namespace WapplerSystems\WsScss;

use ScssPhp\ScssPhp\OutputStyle;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Compiler
{


    public static function compileSassString($scssContent, $variables, $cssFilename = null): string
    {

        $hash = sha1($scssContent);
        $fileName = 'typo3temp/assets/scss/' . $hash.'.scss';
        $absoluteFileName = GeneralUtility::getFileAbsFileName($fileName);


        $file = self::compileFile();



        GeneralUtility::writeFile($absoluteFileName, $content);


        $sitePath = Environment::getPublicPath() . '/';
        $relativeFileName = $sitePath . $fileName;

    }


    /**
     * @throws FileDoesNotExistException
     * @throws \ScssPhp\ScssPhp\Exception\CompilerException
     */
    public static function compileFile($scssFilePath, $variables, $cssFilePath = null): string
    {

        $scssFilePath = GeneralUtility::getFileAbsFileName($scssFilePath);
        $variablesHash = \count($variables) > 0 ? hash('md5', implode(',', $variables)) : null;
        $sitePath = Environment::getPublicPath() . '/';

        $outputStyle = OutputStyle::EXPANDED;

        if (!file_exists($scssFilePath)) {
            throw new FileDoesNotExistException($scssFilePath);
        }

        if ($cssFilePath === null) {
            // no target filename -> auto

            $pathInfo = pathinfo($scssFilePath);
            $filename = $pathInfo['filename'];
            $outputDir = $defaultOutputDir = 'typo3temp/assets/css/';


            $outputDir = (substr($outputDir, -1) === '/') ? $outputDir : $outputDir . '/';
            if (!strcmp(substr($outputDir, 0, 4), 'EXT:')) {
                [$extKey, $script] = explode('/', substr($outputDir, 4), 2);
                if ($extKey && ExtensionManagementUtility::isLoaded($extKey)) {
                    $extPath = ExtensionManagementUtility::extPath($extKey);
                    $outputDir = substr($extPath, \strlen($sitePath)) . $script;
                }
            }

            $cssFilePath = $outputDir . $filename . ($variablesHash ? '_' . $variablesHash : '')  . '.css';

        }


        //GeneralUtility::mkdir_deep($sitePath . $outputDir);




        /** @var FileBackend $cache */
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('ws_scss');

        $cacheKey = hash('sha1', $scssFilePath);
        $calculatedContentHash = self::calculateContentHash($scssFilePath, $variables);
        $calculatedContentHash .= md5($cssFilePath);

        /*
        if ($useSourceMap) {
            $calculatedContentHash .= 'sm';
        }
        $calculatedContentHash .= $formatter;
*/

        $contentHashCache = '';
        if ($cache->has($cacheKey)) {
            $contentHashCache = $cache->get($cacheKey);
            if ($contentHashCache === $calculatedContentHash) {
                return $cssFilePath;
            }
        }


        $sourceMapOptions = [
            'sourceMapBasepath' => $sitePath,
        ];

        // Sass compiler cache
        $cacheDir = $sitePath . 'typo3temp/assets/scss/cache/';
        if (!is_dir($cacheDir)) {
            GeneralUtility::mkdir_deep($cacheDir);
        }
        if (!is_writable($cacheDir)) {
            // TODO: Error message
            return '';
        }

        $cacheOptions = [
            'cacheDir' => $cacheDir,
            'prefix' => md5($cssFilePath),
        ];

        if (!class_exists(\ScssPhp\ScssPhp\Version::class, true)) {
            require_once __DIR__ . '../Resources/Private/scssphp/scss.inc.php';
        }
        $parser = new \ScssPhp\ScssPhp\Compiler($cacheOptions);
        $parser->setVariables($variables);

        $parser->setOutputStyle($outputStyle);

        /*
        if ($sourceMapOptions !== null) {
            $parser->setSourceMap(\ScssPhp\ScssPhp\Compiler::SOURCE_MAP_INLINE);
            $parser->setSourceMapOptions($sourceMapOptions);
        }*/

        try {
            $css = $parser->compile('@import "' . $scssFilePath . '";');
            $cache->set($cacheKey, $calculatedContentHash, ['scss'], 0);
            GeneralUtility::writeFile(GeneralUtility::getFileAbsFileName($cssFilePath), $css);
        } catch (\Exception $ex) {
            DebugUtility::debug($ex->getMessage());

            /** @var $logger Logger */
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error($ex->getMessage());
        }

        return $cssFilePath;
    }


    /**
     * Calculating content hash to detect changes
     *
     * @param string $scssFileName Existing scss file absolute path
     * @param array $vars
     * @param array $visitedFiles
     * @return string
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
        if ($vars !== '') {
            $hash = hash('sha1', $hash . implode(',', $vars));
        } // hash variables too

        $imports = self::collectImports($content);
        foreach ($imports as $import) {
            $hashImport = '';

            if (file_exists($pathInfo['dirname'] . '/' . $import . '.scss')) {
                $hashImport = self::calculateContentHash($pathInfo['dirname'] . '/' . $import . '.scss', $visitedFiles);
            } else {
                $parts = explode('/', $import);
                $filename = '_' . array_pop($parts);
                $parts[] = $filename;
                if (file_exists($pathInfo['dirname'] . '/' . implode('/', $parts) . '.scss')) {
                    $hashImport = self::calculateContentHash($pathInfo['dirname'] . '/' . implode('/',
                            $parts) . '.scss', [], $visitedFiles);
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