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
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use WapplerSystems\WsScss\Compiler;

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

    /**
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    private $contentObjectRenderer;

    /**
     * Main hook function
     *
     * @param array $params Array of CSS/javascript and other files
     * @param PageRenderer $pagerenderer Pagerenderer object
     * @return void
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidFileException
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     * @throws \ScssPhp\ScssPhp\Exception\CompilerException
     */
    public function renderPreProcessorProc(&$params, PageRenderer $pagerenderer)
    {
        if (!\is_array($params['cssFiles'])) {
            return;
        }

        $defaultOutputDir = 'typo3temp/assets/css/';

        $sitePath = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';

        $setup = $GLOBALS['TSFE']->tmpl->setup;
        if (\is_array($setup['plugin.']['tx_wsscss.']['variables.'])) {

            $variables = $setup['plugin.']['tx_wsscss.']['variables.'];

            $parsedTypoScriptVariables = [];
            foreach ($variables as $variable => $key) {
                if (array_key_exists($variable . '.', $variables)) {
                    if ($this->contentObjectRenderer === null) {
                        $this->contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                    }
                    $content = $this->contentObjectRenderer->cObjGetSingle($variables[$variable], $variables[$variable . '.']);
                    $parsedTypoScriptVariables[$variable] = $content;

                } elseif (substr($variable, -1) !== '.') {
                    $parsedTypoScriptVariables[$variable] = $key;
                }
            }
            $this->variables = $parsedTypoScriptVariables;
        }

        $variablesHash = \count($this->variables) > 0 ? hash('md5', implode(',', $this->variables)) : null;

        $filePathSanitizer = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Resource\FilePathSanitizer::class);

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
            $useSourceMap = false;
            $outputFilePath = null;

            // search settings for scss file
            if (\is_array($GLOBALS['TSFE']->pSetup['includeCSS.'])) {
                foreach ($GLOBALS['TSFE']->pSetup['includeCSS.'] as $key => $subconf) {

                    if (\is_string($GLOBALS['TSFE']->pSetup['includeCSS.'][$key]) && trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key]) !== '' && $filePathSanitizer->sanitize($GLOBALS['TSFE']->pSetup['includeCSS.'][$key]) === $file) {
                        $outputDir = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputdir']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputdir']) : $outputDir;
                        $outputFilePath = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputfile']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['outputfile']) : null;
                        $formatter = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['formatter']) ? trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['formatter']) : null;
                        $useSourceMap = isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['sourceMap']);

                        if (isset($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput'])) {
                            $inlineOutput = (boolean)trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput']);
                        }
                    }
                }
            }


            $scssFilePath = GeneralUtility::getFileAbsFileName($conf['file']);


            //DebugUtility::debug($scssFilePath,'scssFilePath');

            $cssFilePath = Compiler::compileFile($scssFilePath,$this->variables, $outputFilePath);



            if ($inlineOutput) {
                unset($cssFiles[$file]);

                // TODO: compression
                $params['cssInline'][$file] = [
                    'code' => file_get_contents(GeneralUtility::getFileAbsFileName($cssFilePath)),
                ];
            } else {
                $cssFiles[$cssFilePath] = $params['cssFiles'][$file];
                $cssFiles[$cssFilePath]['file'] = $cssFilePath;
            }


        }
        $params['cssFiles'] = $cssFiles;
    }




}
