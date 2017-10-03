<?php
namespace WapplerSystems\WsScss\Hooks;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 WapplerSystems <typo3YYYY@wapplersystems.de>
 *  All rights reserved
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

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Leafo\ScssPhp\Compiler;

/**
 * Hook to preprocess scss files
 *
 * @author Sven Wappler <typo3YYYY@wapplersystems.de>
 * @author Jozef Spisiak <jozef@pixelant.se>
 *
 */
class RenderPreProcessorHook
{

    protected $defaultoutputdir = "typo3temp/ws_scss/";

    private static $visitedFiles = array();

    private $variables = array();

    /**
     * Main hook function
     *
     * @param array $params Array of CSS/javascript and other files
     * @param object $pagerendere Pagerenderer object
     * @return null
     *
     */
    public function renderPreProcessorProc(&$params, \TYPO3\CMS\Core\Page\PageRenderer $pagerenderer)
    {

        if (!is_array($params['cssFiles'])) {
            return;
        }

        $setup = $GLOBALS['TSFE']->tmpl->setup;
        if (is_array($setup['plugin.']['tx_wsscss.']['variables.'])) {
            $this->variables = $setup['plugin.']['tx_wsscss.']['variables.'];
        }

        $variablesHash = count($this->variables) > 0 ? hash('md5',implode(",", $this->variables)) : null;

        // we need to rebuild the CSS array to keep order of CSS files
        $cssFiles = array();
        foreach ($params['cssFiles'] as $file => $conf) {
            $pathinfo = pathinfo($conf['file']);

            if ($pathinfo['extension'] !== 'scss') {
                $cssFiles[$file] = $conf;
                continue;
            }


            $outputdir = $this->defaultoutputdir;
            $inlineOutput = false;
            $filename = $pathinfo['filename'];
            $formatter = null;
            $showLineNumber = false;
            $outputfile = "";

            // search settings for scss file
            foreach ($GLOBALS['TSFE']->pSetup['includeCSS.'] as $key => $subconf) {

                if (is_string($GLOBALS['TSFE']->pSetup['includeCSS.'][$key]) && $GLOBALS['TSFE']->tmpl->getFileName($GLOBALS['TSFE']->pSetup['includeCSS.'][$key]) == $file) {
                    $outputdir = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputdir']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputdir']) : $outputdir;
                    $outputfile = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputfile']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputfile']) : null;
                    $formatter = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['formatter']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['formatter']) : null;
                    $showLineNumber = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['linenumber']) ? true : false;

                    if (isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput'])) {
                        $inlineOutput = boolval(trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput']));
                    }
                }
            }
            if (strlen($outputfile) > 0) {
                $outputdir = dirname($outputfile);
                $filename = basename($outputfile);
            }

            $outputdir = (substr($outputdir, -1) == '/') ? $outputdir : $outputdir . "/";

            if (!strcmp(substr($outputdir, 0, 4), 'EXT:')) {
                list($extKey, $script) = explode('/', substr($outputdir, 4), 2);
                if ($extKey && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($extKey)) {
                    $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($extKey);
                    $outputdir = substr($extPath, strlen(PATH_site)) . $script;
                }
            }


            $scssFilename = GeneralUtility::getFileAbsFileName($conf['file']);

            // create filename - hash is important due to the possible
            // conflicts with same filename in different folders
            GeneralUtility::mkdir_deep(PATH_site . $outputdir);
            $cssRelativeFilename = $outputdir . $filename . (($outputdir == $this->defaultoutputdir) ? "_" . hash('sha1',
                        $file) : (count($this->variables) > 0 ? "_".$variablesHash : "")) . ".css";
            $cssFilename = PATH_site . $cssRelativeFilename;

            /** @var CacheManager $cache */
            $cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager')->getCache('ws_scss');

            $cacheKey = hash('sha1', $cssRelativeFilename);
            $contentHash = $this->calculateContentHash($scssFilename, implode(",", $this->variables));
            if ($showLineNumber) $contentHash .= "l1";
            $contentHash .= $formatter;

            $contentHashCache = '';
            if ($cache->has($cacheKey)) {
                $contentHashCache = $cache->get($cacheKey);
            }

            $css = "";

            try {
                if ($contentHashCache == '' || $contentHashCache != $contentHash) {
                    $css = $this->compileScss($scssFilename, $cssFilename, $this->variables, $showLineNumber, $formatter);

                    $cache->set($cacheKey, $contentHash, array());
                }
            } catch (\Exception $ex) {
                // log the exception to the TYPO3 log as error
                echo $ex->getMessage();

                GeneralUtility::sysLog($ex->getMessage(), GeneralUtility::SYSLOG_SEVERITY_ERROR);

            }

            if ($inlineOutput) {
                unset($cssFiles[$cssRelativeFilename]);
                if ($css == "") {
                    $css = file_get_contents($cssFilename);
                }

                // TODO: compression
                $params['cssInline'][$cssRelativeFilename] = array(
                    'code' => $css,
                );
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
     * @return string
     */
    protected function compileScss($scssFilename, $cssFilename, $vars = [], $showLineNumber = false, $formatter = "")
    {

        $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('ws_scss');
        require_once($extPath . 'Resources/Private/scssphp/scss.inc.php');

        $parser = new \Leafo\ScssPhp\Compiler();
        if (file_exists($scssFilename)) {

            $parser->setVariables($vars);

            if ($showLineNumber) {
                $parser->setLineNumberStyle(\Leafo\ScssPhp\Compiler::LINE_COMMENTS);
            }
            if ($formatter != "") {
                $parser->setFormatter($formatter);
            }

            $css = $parser->compile('@import "' . $scssFilename . '";');

            GeneralUtility::writeFile($cssFilename, $css);

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
    protected function calculateContentHash($scssFilename, $vars = "")
    {
        if (in_array($scssFilename, self::$visitedFiles)) {
            return "";
        }
        self::$visitedFiles[] = $scssFilename;

        $content = file_get_contents($scssFilename);
        $pathinfo = pathinfo($scssFilename);

        $hash = hash('sha1', $content);
        if ($vars != "") {
            $hash = hash('sha1', $hash . $vars);
        } // hash variables too

        preg_match_all('/@import "([^"]*)"/', $content, $imports);

        foreach ($imports[1] as $import) {
            $hashImport = "";


            if (file_exists($pathinfo['dirname'] . '/' . $import . '.scss')) {
                $hashImport = $this->calculateContentHash($pathinfo['dirname'] . '/' . $import . '.scss');
            } else {
                $parts = explode("/", $import);
                $filename = "_" . array_pop($parts);
                $parts[] = $filename;
                if (file_exists($pathinfo['dirname'] . '/' . implode("/", $parts) . '.scss')) {
                    $hashImport = $this->calculateContentHash($pathinfo['dirname'] . '/' . implode("/",
                            $parts) . '.scss');
                }
            }
            if ($hashImport != "") {
                $hash = hash('sha1', $hash . $hashImport);
            }
        }

        return $hash;
    }


}

?>