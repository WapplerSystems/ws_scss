<?php

namespace WapplerSystems\WsScss;

use League\Uri\Uri;
use Psr\EventDispatcher\EventDispatcherInterface;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\Util\Path;
use ScssPhp\ScssPhp\Value\SassColor;
use ScssPhp\ScssPhp\Value\SassNumber;
use ScssPhp\ScssPhp\Value\SassString;
use ScssPhp\ScssPhp\Value\Value;
use ScssPhp\ScssPhp\ValueConverter;
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
use TYPO3\CMS\Core\Utility\PathUtility;
use WapplerSystems\WsScss\Event\AfterScssCompilationEvent;
use WapplerSystems\WsScss\Importer\ExtensionFilesystemImporter;
use WapplerSystems\WsScss\Importer\FilesystemImporter;
use WapplerSystems\WsScss\Importer\VariableFilesystemImporter;

class Compiler
{

    public static string $CURRENT_LOAD_PATH = '';

    /**
     * @param $scssContent
     * @param $variables
     * @param null $cssFilename
     * @param bool $useSourceMap
     * @param string $outputStyle
     * @return string
     * @throws FileDoesNotExistException
     * @throws NoSuchCacheException
     * @throws SassException
     */
    public static function compileSassString($scssContent, $variables, $cssFilename = null, bool $useSourceMap = false, ?OutputStyle $outputStyle = null): string
    {
        if ($outputStyle === null) {
            $outputStyle = OutputStyle::COMPRESSED;
        }

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
     * @param string $scssFilePath
     * @param array $variables
     * @param string|null $cssFilePath
     * @param bool $useSourceMap
     * @param string $outputStyle
     * @return string the compiled css file as path
     * @throws FileDoesNotExistException
     * @throws NoSuchCacheException
     * @throws SassException
     */
    public static function compileFile(string $scssFilePath, array $variables, ?string $cssFilePath = null, bool $useSourceMap = false, ?OutputStyle $outputStyle = null): string
    {
        if ($outputStyle === null) {
            $outputStyle = OutputStyle::COMPRESSED;
        }
        $scssFilePath = GeneralUtility::getFileAbsFileName($scssFilePath);
        $variablesHash = hash('md5', implode(',', $variables) . $scssFilePath);
        $sitePath = Environment::getPublicPath() . '/';

        if (!file_exists($scssFilePath)) {
            throw new FileDoesNotExistException($scssFilePath);
        }

        if ($cssFilePath === null) {
            // no target filename -> auto

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



        // Sass compiler cache
        $cacheDir = $sitePath . 'typo3temp/assets/scss/cache/';
        if (!is_dir($cacheDir)) {
            GeneralUtility::mkdir_deep($cacheDir);
        }
        if (!is_writable($cacheDir)) {
            // TODO: Error message
            return '';
        }

        $convertedVariables = [];
        foreach ($variables as $varName => $varValue) {

            if ($varValue instanceof Value) {
                $convertedVariables[$varName] = $varValue;
                continue;
            }

            if (str_ends_with($varValue, 'rem')) {
                $convertedVariables[$varName] = SassNumber::create((float)$varValue, 'rem');
            } elseif (str_ends_with($varValue, 'px')) {
                $convertedVariables[$varName] = SassNumber::create((int)$varValue, 'px');
            } elseif (str_starts_with($varValue, '#')) {
                $rgb = self::hex2rgb($varValue);
                $convertedVariables[$varName] = SassColor::rgb($rgb[0], $rgb[1], $rgb[2]);
            } elseif (str_contains($varName,'font-family')) {
                $convertedVariables[$varName] = new SassString($varValue, false);
            } else {
                $convertedVariables[$varName] = ValueConverter::fromPhp($varValue);
            }
        }

        $scssCompiler = new \ScssPhp\ScssPhp\Compiler();
        $scssCompiler->addVariables($convertedVariables);
        $scssCompiler->setOutputStyle($outputStyle);

        if ($useSourceMap) {
            $scssCompiler->setSourceMap(\ScssPhp\ScssPhp\Compiler::SOURCE_MAP_INLINE);

            $scssCompiler->setSourceMapOptions([
                'sourceMapBasepath' => $sitePath,
                'sourceMapRootpath' => '/',
            ]);
        }

        $absoluteFilePath = dirname($scssFilePath);
        $relativeFilePath = PathUtility::getAbsoluteWebPath($absoluteFilePath);

        $visualImportPath = dirname($scssFilePath);

        self::$CURRENT_LOAD_PATH = '';
        $importers = [
            new ExtensionFilesystemImporter($visualImportPath),
            new VariableFilesystemImporter($absoluteFilePath, $scssCompiler),
            new FilesystemImporter($absoluteFilePath)
        ];

        foreach ($importers as $importer) {
            $scssCompiler->addImporter($importer);
        }


        $scssCompiler->registerFunction(
            'url',
            function ($args) use (
                $scssCompiler,
                $absoluteFilePath,
                $relativeFilePath
            ): SassString {
                $marker = $args[0][1];
                $args[0][1] = '';
                $result = $scssCompiler->compileValue($args[0]);
                if (str_starts_with($result,'data:')) {
                    return new SassString('url(' . $marker . $result . $marker . ')', false);
                }
                if (is_file(PathUtility::getCanonicalPath($absoluteFilePath . '/' . $result))) {
                    $result = PathUtility::getAbsoluteWebPath(PathUtility::getCanonicalPath($relativeFilePath . '/' . $result));
                } elseif (str_starts_with($result, 'EXT:')) {
                    $file = strstr($result, '?', true);
                    if (is_file(GeneralUtility::getFileAbsFileName($file))) {
                        $result = PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($result));
                    }
                }
                //$result = str_starts_with($result, '/') ? substr($result, 1) : $result;

                return new SassString( 'url(' . $marker . $result . $marker . ')', false);
            },
            [0 => 'string']
        );

        $importResolver = new ImportResolver($importers);

        /** @var FileBackend $cache */
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('ws_scss');

        $scssFilePathUri = Uri::new($scssFilePath);

        $cacheKey = hash('sha1', $scssFilePath);
        $calculatedContentHash = self::calculateContentHash($importResolver, $scssFilePathUri, $variables);
        $calculatedContentHash .= md5($cssFilePath);
        if ($useSourceMap) {
            $calculatedContentHash .= 'sm';
        }

        $calculatedContentHash .= $outputStyle->value;

        if ($cache->has($cacheKey)) {
            $contentHashCache = $cache->get($cacheKey);
            if ($contentHashCache === $calculatedContentHash) {
                return $cssFilePath;
            }
        }


        try {


            $scssSource = GeneralUtility::getUrl($scssFilePath);
            if ($scssSource === false) {
                throw new FileDoesNotExistException('SCSS file not found: ' . $scssFilePath, 1633031234);
            }
            $result = $scssCompiler->compileString($scssSource);
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
    public static function calculateContentHash(ImportResolver $importerResolver, Uri $scssFileName, array $vars = [], array $visitedFiles = []): string
    {
        if (\in_array($scssFileName->toString(), $visitedFiles, true)) {
            return '';
        }
        $visitedFiles[] = $scssFileName->toString();

        $path = Path::fromUri($scssFileName);
        $content = file_get_contents($path);
        $pathInfo = pathinfo($scssFileName);

        $hash = hash('sha1', $content);
        $hash = hash('sha1', $hash . implode(',', $vars));

        $imports = self::collectImports($content);
        foreach ($imports as $importPath) {

            $absoluteImportPath = $importerResolver->resolveImportPath($importPath, $pathInfo['dirname']);
            if ($absoluteImportPath !== null) {
                $hashImport = self::calculateContentHash($importerResolver, $absoluteImportPath, $visitedFiles);
                if ($hashImport !== '') {
                    $hash = hash('sha1', $hash . $hashImport);
                }
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

    private static function hex2rgb($hex)
    {
        $hex = str_replace("#", "", $hex);
        if (strlen($hex) === 3) {
            $r = hexdec($hex[0] . $hex[0]);
            $g = hexdec($hex[1] . $hex[1]);
            $b = hexdec($hex[2] . $hex[2]);
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }

}
