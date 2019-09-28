<?php
namespace WapplerSystems\WsScss\Hooks;

/***************************************************************
 *  Copyright notice
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Hook to preprocess scss files
 *
 * @author Sven Wappler <typo3YYYY@wapplersystems.de>
 * @author Jozef Spisiak <jozef@pixelant.se>
 *
 */
class RenderPreProcessorHook
{

    private static $visitedFiles = [];

    private $variables = [];
    private $importPaths = [];
    private $setup = false;
    private $parser = false;

    /**
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    private $contentObjectRenderer;

    /**
     * watch import path files for changed files
     * @param integer $lastBuildTime
     * @return array
     */
    public function watch($lastBuildTime){
        $has_changes = false;
        if( boolval( $this->setup['watchImportPath'] ) ){
            $filelist = [];
            foreach ( $this->importPaths as $addPath ) {
                $filelist=array_merge($filelist, glob( $addPath . "/*.scss" ));
                $filelist=array_merge($filelist, glob( $addPath . "/**/*.scss" ));
            }
            foreach ($filelist as $i => &$watchfile){
                if (! realpath($watchfile) or filemtime($watchfile) > $lastBuildTime) {
                    $has_changes = true;
                    break;
                }
                // For Debugging
                //$watchfile = $watchfile.' - '.filemtime($watchfile);
            }
        }
        //debug($filelist);exit;
        return $has_changes;
    }
    /**
     * Main hook function
     *
     * @param array $params Array of CSS/javascript and other files
     * @param PageRenderer $pagerenderer Pagerenderer object
     * @return void
     * @throws \BadFunctionCallException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function renderPreProcessorProc(&$params, PageRenderer $pagerenderer)
    {
        if (!\is_array($params['cssFiles'])) {
            return;
        }

        $defaultOutputDir = 'typo3temp/assets/css/';
        if (VersionNumberUtility::convertVersionNumberToInteger(VersionNumberUtility::getCurrentTypo3Version()) < VersionNumberUtility::convertVersionNumberToInteger('8.0.0')) {
            $defaultOutputDir = 'typo3temp/';
        }
        if ($this->contentObjectRenderer === null) {
            $this->contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        }

        $this->setup = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_wsscss.'];
        if (\is_array($this->setup['importPaths.'])) {
            foreach ($this->setup['importPaths.'] as $index => $importPath) {
                $this->importPaths[] = $this->getStreamlinedPath( $importPath );
            }
        }

        if (\is_array($this->setup['variables.'])) {

            $variables = $this->setup['variables.'];

            $parsedTypoScriptVariables = [];
            foreach ($variables as $variable => $key) {
                if (array_key_exists($variable . '.', $variables)) {
                    $content = $this->contentObjectRenderer->cObjGetSingle($variables[$variable], $variables[$variable . '.']);
                    $parsedTypoScriptVariables[$variable] = $content;

                } elseif (substr($variable, -1) !== '.') {
                    $parsedTypoScriptVariables[$variable] = $key;
                }
            }
            $this->variables = $parsedTypoScriptVariables;
        }

        $variablesHash = \count($this->variables) > 0 ? hash('md5',implode(',', $this->variables)) : null;

        // we need to rebuild the CSS array to keep order of CSS files
        $cssFiles = [];
        foreach ($params['cssFiles'] as $file => $conf) {
            $pathInfo = pathinfo($conf['file']);

            if ($pathInfo['extension'] !== 'scss') {
                $cssFiles[$file] = $conf;
                continue;
            }

            $outputDir = $defaultOutputDir;

            $inlineOutput = false;
            $filename = $pathInfo['filename'];
            $formatter = null;
            $showLineNumber = false;
            $useSourceMap = false;
            $outputFile = '';

            // search settings for scss file
            foreach ($GLOBALS['TSFE']->pSetup['includeCSS.'] as $key => $subconf) {

                if (\is_string($GLOBALS['TSFE']->pSetup['includeCSS.'][$key]) && $GLOBALS['TSFE']->tmpl->getFileName($GLOBALS['TSFE']->pSetup['includeCSS.'][$key]) === $file) {
                    $outputDir = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputdir']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputdir']) : $outputDir;
                    $outputFile = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputfile']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputfile']) : null;
                    $formatter = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['formatter']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['formatter']) : null;
                    $showLineNumber = false;
                    if (isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['linenumber'])) {
                        if ($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['linenumber'] === 'true' || (int)$GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['linenumber'] === 1) {
                            $showLineNumber = true;
                        }
                    }
                    $useSourceMap = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['sourceMap']) ? true : false;

                    if (isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput'])) {
                        $inlineOutput = (bool)trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput']);
                    }
                }
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
                    $outputDir = substr($extPath, \strlen(PATH_site)) . $script;
                }
            }


            $scssFilename = GeneralUtility::getFileAbsFileName($conf['file']);

            // create filename - hash is important due to the possible
            // conflicts with same filename in different folders
            GeneralUtility::mkdir_deep(PATH_site . $outputDir);
            $cssRelativeFilename = $outputDir . $filename . (($outputDir === $defaultOutputDir) ? '_' . hash('sha1',
                        $file) : (\count($this->variables) > 0 ? '_'.$variablesHash : '')) . '.css';
            $cssFilename = PATH_site . $cssRelativeFilename;

            /** @var FileBackend $cache */
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('ws_scss');

            $cacheKey = hash('sha1', $cssRelativeFilename);
            $contentHash = $this->calculateContentHash($scssFilename, implode(',', $this->variables));
            if ($showLineNumber) {
                $contentHash .= 'l1';
            }
            if ($useSourceMap) {
                $contentHash .= 'sm';
            }
            $contentHash .= $formatter;

            $contentHashCache = '';
            if ($cache->has($cacheKey)) {
                $contentHashCache = $cache->get($cacheKey);
            }

            $css = '';

            try {
                if ($contentHashCache === '' || $contentHashCache !== $contentHash || $this->watch(filemtime($cssFilename)) ) {
                    $css = $this->compileScss($scssFilename, $cssFilename, $this->variables, $showLineNumber, $formatter, $cssRelativeFilename, $useSourceMap);

                    $cache->set($cacheKey, $contentHash, ['scss'], 0);
                }
            } catch (\Exception $ex) {
                DebugUtility::debug($ex->getMessage());

                /** @var $logger \TYPO3\CMS\Core\Log\Logger */
                $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
                $logger->error($ex->getMessage());
            }

            if ($inlineOutput) {
                unset($cssFiles[$cssRelativeFilename]);
                if ($css === '') {
                    $css = file_get_contents($cssFilename);
                }

                // TODO: compression
                $params['cssInline'][$cssRelativeFilename] = [
                    'code' => $css,
                ];
            } else {
                $cssFiles[$cssRelativeFilename] = $params['cssFiles'][$file];
                $cssFiles[$cssRelativeFilename]['file'] = $cssRelativeFilename;
            }


        }
        $params['cssFiles'] = $cssFiles;
    }

    /**
     * Compiling Scss with scss
     *
     * @param string $scssFilename Existing scss file absolute path
     * @param string $cssFilename File to be written with compiled CSS
     * @param array $vars Variables to compile
     * @param boolean $showLineNumber Show line numbers
     * @param string $formatter name
     * @param string $cssRelativeFilename
     * @param boolean $useSourceMap Use SourceMap
     * @return string
     * @throws \BadFunctionCallException
     */
    protected function compileScss($scssFilename, $cssFilename, $vars = [], $showLineNumber = false, $formatter = null, $cssRelativeFilename = null, $useSourceMap = false): string
    {

        $extPath = ExtensionManagementUtility::extPath('ws_scss');
        require_once $extPath . 'Resources/Private/scssphp/scss.inc.php';

        $scssCacheOptions = [
            'cache_dir' => $this->getStreamlinedPath('typo3temp/ws_scss/'),
            'prefix' => 'scssphp_',
            'forceRefresh' => false
        ];

        try {
            $this->parser = new \ScssPhp\ScssPhp\Compiler($scssCacheOptions);
        }catch(Exception $e){
            DebugUtility::printArray($e->getMessage());
        }


        if (file_exists($scssFilename)) {
            // set import paths
            try {
                if( $this->setup['importPathMode'] === 'replace'){
                    $this->parser->setImportPaths( $this->importPaths );
                } else {
                    foreach ( $this->importPaths as $addPath ) {
                        $this->parser->addImportPath($addPath);
                    }
                }
                $this->parser->setVariables($vars);
                if ($showLineNumber) {
                    $this->parser->setLineNumberStyle(\ScssPhp\ScssPhp\Compiler::LINE_COMMENTS);
                }
                if ($formatter !== null) {
                    $this->parser->setFormatter($formatter);
                }

                if ($useSourceMap) {
                    $this->parser->setSourceMap(\ScssPhp\ScssPhp\Compiler::SOURCE_MAP_INLINE);

                    $this->parser->setSourceMapOptions([
                            'sourceMapWriteTo' => $cssFilename . '.map',
                            'sourceMapURL' => $cssRelativeFilename . '.map',
                            'sourceMapBasepath' => PATH_site,
                            'sourceMapRootpath' => '/',
                    ]);
                }
                $css = $this->parser->compile(file_get_contents( $scssFilename ));
            }catch(\ScssPhp\ScssPhp\Exception\CompilerException $e){
                DebugUtility::printArray($e->getMessage());
            }

            GeneralUtility::writeFile($cssFilename, $css);

            if( boolval( $this->setup['debug'] ) ){
                debug( $this->parser );
                debug( $this->parser->getParsedFiles() );
                debug(  $this->setup );
            }

            return $css;
        }

        return '';
    }

    /**
     * Calculating content hash to detect changes
     *
     * @param string $scssFilename Existing scss file absolute path
     * @param string $vars
     * @return string
     */
    protected function calculateContentHash($scssFilename, $vars = ''): string
    {
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

        preg_match_all('/@import "([^"]*)"/', $content, $imports);

        foreach ($imports[1] as $import) {
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
     *
     * @param string $path
     * @param boolean $relPath
     * @return string parsed path
     */
    protected function getStreamlinedPath($path, $relPath=false)
    {
        if ( strpos( $path, 'EXT:' ) === 0) {
            $pathParts = explode( '/', substr( $path, 4 ) );
            $extKey = array_shift( $pathParts );

            if ( (string) $extKey !== '' && ExtensionManagementUtility::isLoaded( $extKey ) ) {
                array_unshift( $pathParts, rtrim( ExtensionManagementUtility::extPath( $extKey ), '/') );
            }
            $path = implode( '/', $pathParts );
        } elseif ( strpos( $path, 'DIR:' ) === 0 ) {
            $pathParts = explode( '/', substr( $path, 4 ) );
            array_unshift( $pathParts, rtrim(PATH_site, '/') );
            $path = implode( '/', $pathParts );
        } elseif ( strpos( $path, '..' ) === 0) {
            $path = realpath( $path );
            $path = str_replace(DIRECTORY_SEPARATOR,'/', $path);
        } else {
            $path = GeneralUtility::getFileAbsFileName( $path );
        }

        if($relPath){
            $path = PathUtility::stripPathSitePrefix($path);
        }
        return $path;
    }

}
