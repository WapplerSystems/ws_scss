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

use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
     * @throws FileDoesNotExistException
     * @throws NoSuchCacheException
     * @throws SassException
     */
    public function renderPreProcessorProc(array &$params, PageRenderer $pagerenderer): void
    {
        if (!\is_array($params['cssFiles'])) {
            return;
        }

        $defaultOutputDir = 'typo3temp/assets/css/';

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

                } elseif (!str_ends_with($variable, '.')) {
                    $parsedTypoScriptVariables[$variable] = $key;
                }
            }
            $this->variables = $parsedTypoScriptVariables;
        }


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
            $useSourceMap = false;
            $outputFilePath = null;
            $outputStyle = OutputStyle::COMPRESSED;

            // search settings for scss file
            if (is_array($GLOBALS['TSFE']->pSetup['includeCSS.'] ?? [])) {
                foreach ($GLOBALS['TSFE']->pSetup['includeCSS.'] as $key => $keyValue) {
                    if (str_ends_with($key, '.')) {
                        continue;
                    }

                    if ($file === $keyValue) {
                        $subConf = $GLOBALS['TSFE']->pSetup['includeCSS.'][$key.'.'] ?? [];

                        $outputDir = $subConf['outputdir'] ?? $outputDir;
                        $outputFilePath = $subConf['outputfile'] ?? null;
                        $useSourceMap = isset($subConf['sourceMap']);
                        if (isset($subConf['outputStyle']) && ($subConf['outputStyle'] === 'expanded' || $subConf['outputStyle'] === 'compressed')) {
                            $outputStyle = $subConf['outputStyle'];
                        }

                        if ($subConf['inlineOutput'] ?? false) {
                            $inlineOutput = (bool)trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput']);
                        }
                    }
                }
            }


            $scssFilePath = GeneralUtility::getFileAbsFileName($conf['file']);

            $cssFilePath = Compiler::compileFile($scssFilePath, $this->variables, $outputFilePath, $useSourceMap, $outputStyle);

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
