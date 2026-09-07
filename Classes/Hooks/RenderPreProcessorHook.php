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
use Psr\Http\Message\ServerRequestInterface;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
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

    private array $variables = [];

    private ?ContentObjectRenderer $contentObjectRenderer = null;

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

        $setup = $GLOBALS['TYPO3_REQUEST']->getAttribute('frontend.typoscript')->getSetupArray();
        if (\is_array($setup['plugin.']['tx_wsscss.']['variables.'] ?? null)) {
            $this->variables = $this->parseTypoScriptVariables($setup['plugin.']['tx_wsscss.']['variables.']);
        }

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

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
            if (is_array($setup['page.']['includeCSS.'] ?? [])) {
                foreach ($setup['page.']['includeCSS.'] as $key => $keyValue) {
                    if (str_ends_with($key, '.')) {
                        continue;
                    }

                    if ($file === $keyValue || GeneralUtility::getFileAbsFileName($file) === GeneralUtility::getFileAbsFileName($keyValue)) {
                        $subConf = $setup['page.']['includeCSS.'][$key . '.'] ?? [];

                        $outputFilePath = $subConf['outputfile'] ?? null;
                        $useSourceMap = $this->parseBooleanSetting($subConf['sourceMap'] ?? false, false);
                        $unlink = $this->parseBooleanSetting($subConf['unlink'] ?? false, false);
                        if (isset($subConf['outputStyle'])) {
                            if ($subConf['outputStyle'] === 'expanded') {
                                $outputStyle = OutputStyle::EXPANDED;
                            } elseif ($subConf['outputStyle'] === 'compressed') {
                                $outputStyle = OutputStyle::COMPRESSED;
                            }
                        }
                        $variables = array_filter($this->parseTypoScriptVariables($subConf['variables.'] ?? []));
                        $inlineOutput = $this->parseBooleanSetting($setup['page.']['includeCSS.'][$key . '.']['inlineOutput'] ?? false, false);
                    }
                }
            }

            if (str_starts_with($conf['file'], 'FAL:')) {

                $fileObject = $resourceFactory->retrieveFileOrFolderObject(substr($conf['file'], 4));
                if ($fileObject === null) {
                    continue;
                }
                $scssFilePath = $fileObject->getForLocalProcessing(false);
            } else {
                $scssFilePath = GeneralUtility::getFileAbsFileName($conf['file']);
            }

            $pathChunks = explode('/', PathUtility::getAbsoluteWebPath($scssFilePath));
            if (self::usesComposerClassLoading()) {
                $assetPath = implode('/',array_splice($pathChunks,0,3)).'/';
            } else {
                $assetPath = implode('/',array_splice($pathChunks,0,6)).'/';
            }

            if ($inlineOutput) {
                $useSourceMap = false;
            }
            $mergedVars = array_merge($this->variables, ['extAssetPath' => $assetPath], $variables);
            $cssFilePath = Compiler::compileFile($scssFilePath, $mergedVars, $outputFilePath, $useSourceMap, $outputStyle);

            if ($inlineOutput && file_exists(GeneralUtility::getFileAbsFileName($cssFilePath))) {
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

    /**
     * Flattens a TypoScript variables array into plain SCSS variable values.
     *
     * A variable may either be a plain value (`myVar = 20px`) or a content
     * object (`myVar = TEXT` + `myVar.value = 20px`). The latter arrives as two
     * keys -- the object name and a `myVar.` sub-array -- and has to be rendered
     * before it can be handed to the compiler. Sub-arrays that are left over
     * are dropped: they would end up in Compiler::compileFile(), where
     * implode() on the variables array triggers an "Array to string conversion"
     * warning and, with TYPO3's error handler, a 500 in the frontend.
     */
    private function parseTypoScriptVariables(array $variables): array
    {
        $parsedVariables = [];

        foreach ($variables as $variable => $variableValue) {
            if (array_key_exists($variable . '.', $variables)) {
                $parsedVariables[$variable] = $this->getContentObjectRenderer()
                    ->cObjGetSingle($variables[$variable], $variables[$variable . '.']);
            } elseif (!str_ends_with($variable, '.')) {
                $parsedVariables[$variable] = $variableValue;
            }
        }

        return $parsedVariables;
    }

    private function getContentObjectRenderer(): ContentObjectRenderer
    {
        if ($this->contentObjectRenderer === null) {
            $this->contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        }

        // cObjGetSingle() ends up in ContentObjectRenderer::getRequest(), which falls back to
        // $GLOBALS['TYPO3_REQUEST'] with a deprecation notice in v14 and loses that fallback in
        // v15. Assign on every access -- the hook instance can outlive a single request.
        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface) {
            $this->contentObjectRenderer->setRequest($GLOBALS['TYPO3_REQUEST']);
        }

        return $this->contentObjectRenderer;
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
