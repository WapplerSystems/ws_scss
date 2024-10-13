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

use Psr\EventDispatcher\EventDispatcherInterface;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use WapplerSystems\WsScss\Compiler;
use WapplerSystems\WsScss\Event\AfterVariableDefinitionEvent;

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
     * @param PageRenderer $pageRenderer Pagerenderer object
     * @return void
     * @throws FileDoesNotExistException
     * @throws NoSuchCacheException
     * @throws SassException
     */
    public function renderPreProcessorProc(array &$params, PageRenderer $pageRenderer): void
    {
        if ($GLOBALS['TYPO3_REQUEST'] == null ||
            !ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()
        ) {
            return;
        }

        if (!\is_array($params['cssFiles'])) {
            return;
        }

        $setup = $GLOBALS['TSFE']->tmpl->setup;
        if (\is_array($setup['plugin.']['tx_wsscss.']['variables.'] ?? null)) {

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

        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
        $event = $eventDispatcher->dispatch(new AfterVariableDefinitionEvent($this->variables));
        $this->variables = $event->getVariables();

        // we need to rebuild the CSS array to keep order of CSS files
        $cssFiles = [];
        foreach ($params['cssFiles'] as $file => $conf) {
            $pathInfo = pathinfo($conf['file']);

            if (!isset($pathInfo['extension']) || $pathInfo['extension'] !== 'scss') {
                $cssFiles[$file] = $conf;
                continue;
            }

            $inlineOutput = false;
            $useSourceMap = false;
            $outputFilePath = null;
            $outputStyle = OutputStyle::COMPRESSED;
            $variables = [];
            $unlink = false;

            // search settings for scss file
            if (is_array($GLOBALS['TSFE']->pSetup['includeCSS.'] ?? [])) {
                foreach ($GLOBALS['TSFE']->pSetup['includeCSS.'] as $key => $keyValue) {
                    if (str_ends_with($key, '.')) {
                        continue;
                    }

                    if ($file === $keyValue) {
                        $subConf = $GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.'] ?? [];

                        $outputFilePath = $subConf['outputfile'] ?? null;
                        $useSourceMap = $this->parseBooleanSetting($subConf['sourceMap'] ?? false, false);
                        $unlink = $this->parseBooleanSetting($subConf['unlink'] ?? false, false);
                        if (isset($subConf['outputStyle']) && ($subConf['outputStyle'] === 'expanded' || $subConf['outputStyle'] === 'compressed')) {
                            $outputStyle = $subConf['outputStyle'];
                        }
                        $variables = array_filter($subConf['variables.'] ?? []);
                        $inlineOutput = $this->parseBooleanSetting($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput'] ?? false, false);
                    }
                }
            }

            $scssFilePath = GeneralUtility::getFileAbsFileName($conf['file']);
            $pathChunks = explode('/', PathUtility::getAbsoluteWebPath($scssFilePath));
            if (self::usesComposerClassLoading()) {
                $assetPath = implode('/',array_splice($pathChunks,0,3)).'/';
            } else {
                $assetPath = implode('/',array_splice($pathChunks,0,6)).'/';
            }

            if ($inlineOutput) {
                $useSourceMap = false;
            }
            $cssFilePath = Compiler::compileFile($scssFilePath, array_merge($this->variables, ['extAssetPath' => $assetPath], $variables), $outputFilePath, $useSourceMap, $outputStyle);

            if ($inlineOutput) {
                // TODO: compression
                $params['cssInline'][$file] = [
                    'code' => file_get_contents(GeneralUtility::getFileAbsFileName($cssFilePath)),
                    'forceOnTop' => false,
                ];
            } else if (!$unlink) {

                unset($conf['tagAttributes']['inlineOutput']);
                unset($conf['tagAttributes']['sourceMap']);
                unset($conf['tagAttributes']['variables.']);
                unset($conf['tagAttributes']['outputfile']);

                $cssFiles[$cssFilePath] = $conf;
                $cssFiles[$cssFilePath]['file'] = $cssFilePath;
            }
        }
        $params['cssFiles'] = $cssFiles;
    }

    private function parseBooleanSetting(string $value, bool $defaultValue): bool
    {
        if (trim($value) === 'true' || trim($value) === '1') {
            return true;
        }
        if (trim($value) === 'false' || trim($value) === '0') {
            return false;
        }
        return $defaultValue;
    }

    protected static function usesComposerClassLoading(): bool
    {
        return defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
    }
}
