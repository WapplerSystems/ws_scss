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

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\CompilerException;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\Version;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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

    private static array $visitedFiles = [];

    private array $variables = [];

    /**
     * @var ContentObjectRenderer
     */
    private ContentObjectRenderer $contentObjectRenderer;

    /**
     * Main hook function
     *
     * @param array $params Array of CSS/javascript and other files
     * @param PageRenderer $pageRenderer PageRenderer object
     * @return void
     * @throws SassException
     * @throws NoSuchCacheException
     */
    public function renderPreProcessorProc(array &$params, PageRenderer $pageRenderer): void
    {
        if (!is_array($params['cssFiles'])) {
            return;
        }

        $defaultOutputDir = 'typo3temp/assets/css/';
        $this->contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $sitePath = Environment::getPublicPath() . '/';

        $setup = $GLOBALS['TSFE']->tmpl->setup;
        if (is_array($setup['plugin.']['tx_wsscss.']['variables.'])) {

            $variables = $setup['plugin.']['tx_wsscss.']['variables.'];

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

        $variablesHash = count($this->variables) > 0 ? hash('md5', implode(',', $this->variables)) : null;

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
            $useSourceMap = false;
			$outputFile = null;
			$dependencies = array();
            $outputStyle = OutputStyle::COMPRESSED;

            // search settings for scss file
            if (\is_array($GLOBALS['TSFE']->pSetup['includeCSS.'] ?? [])) {
                foreach ($GLOBALS['TSFE']->pSetup['includeCSS.'] as $key => $keyValue) {
					if (substr($key,-1) === '.') {
						continue;
					}

                    if ($file === $keyValue) {
						$subConf = $GLOBALS['TSFE']->pSetup['includeCSS.'][$key.'.'] ?? [];


						$outputDir = $subConf['outputdir'] ?? $outputDir;
						$outputFile = $subConf['outputfile'] ?? null;
						$useSourceMap = isset($subConf['sourceMap']);
                        $showLineNumber = false;
						$dependencies = $subConf['dependencies.'] ?? [];

						if ($subConf['outputStyle'] === 'expanded' || $subConf['outputStyle'] === 'compressed') {
							$outputStyle = $subConf['outputStyle'];
						}

						if ($subConf['inlineOutput'] ?? false) {
							$inlineOutput = (bool)trim($GLOBALS['TSFE']->pSetup['includeCSS.'][$key . '.']['inlineOutput']);
						}

                    }
                }
            }
            if ($outputFile !== null) {
                $outputDir = dirname($outputFile);
                $filename = basename($outputFile);
            }

            $outputDir = (substr($outputDir, -1) === '/') ? $outputDir : $outputDir . '/';

            if (!strcmp(substr($outputDir, 0, 4), 'EXT:')) {
                [$extKey, $script] = explode('/', substr($outputDir, 4), 2);
                if ($extKey && ExtensionManagementUtility::isLoaded($extKey)) {
                    $extPath = ExtensionManagementUtility::extPath($extKey);
                    $outputDir = substr($extPath, strlen($sitePath)) . $script;
                }
            }


            $scssFilename = GeneralUtility::getFileAbsFileName($conf['file']);

            foreach($dependencies as &$dependency){
            	$dependency = GeneralUtility::getFileAbsFileName($dependency);
            	$file = basename($dependency);

            	$fileA = '_'.$file.'.scss';
            	$fileB = $file.'.scss';

            	$lastSlashPos = strrpos($dependency,'/');
            	$path = substr($dependency,0,$lastSlashPos);

            	$realDependencyPathA = $path.'/'.$fileA;
            	$realDependencyPathB = $path.'/'.$fileB;

            	if(!file_exists($realDependencyPathA) && !file_exists($realDependencyPathB)){
					DebugUtility::debug($realDependencyPathA.' oder '.$realDependencyPathB.' File does not exists');
				}
			}

            // create filename - hash is important due to the possible
            // conflicts with same filename in different folders
            GeneralUtility::mkdir_deep($sitePath . $outputDir);
            if ($outputFile === null) {
                $cssRelativeFilename = $outputDir . $filename . (($outputDir === $defaultOutputDir) ? '_' . hash('sha1',
                            $file) : (count($this->variables) > 0 ? '_' . $variablesHash : '')) . ((substr($filename,-4) === '.css') ? '' : '.css');
            } else {
                $cssRelativeFilename = $outputDir . $filename . ((substr($filename,-4) === '.css') ? '' : '.css');
            }
            $cssFilename = $sitePath . $cssRelativeFilename;

            /** @var FileBackend $cache */
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('ws_scss');

            $cacheKey = hash('sha1', $cssRelativeFilename);
            $contentHash = $this->calculateContentHash($scssFilename, implode(',', $this->variables));
            if ($useSourceMap) {
                $contentHash .= 'sm';
            }

            $contentHashCache = '';
            if ($cache->has($cacheKey)) {
                $contentHashCache = $cache->get($cacheKey);
            }

            $css = '';

            try {
                if ($contentHashCache === '' || $contentHashCache !== $contentHash) {
                    $css = $this->compileScss($scssFilename, $cssFilename, $outputStyle, $this->variables, $cssRelativeFilename, $useSourceMap, $dependencies);

                    $cache->set($cacheKey, $contentHash, ['scss'], 0);
                }
            } catch (\Exception $ex) {
                DebugUtility::debug($ex->getMessage());

                /** @var $logger Logger */
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
     * @param string $outputStyle
     * @param array $vars Variables to compile
     * @param string|null $cssRelativeFilename
     * @param boolean $useSourceMap Use SourceMap
	 * @param array $dependencies dependencies of a scss file
     * @return string
     * @throws CompilerException
     * @throws SassException
     */
    protected function compileScss(string $scssFilename, string $cssFilename, string $outputStyle, array $vars = [], string $cssRelativeFilename = null, bool $useSourceMap = false, array $dependencies = []): string
    {

        $sitePath = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';
		$cacheDir = $sitePath . 'typo3temp/assets/css/cache/';

		if (!is_dir($cacheDir)) {
			GeneralUtility::mkdir_deep($cacheDir);
		}

		if (!is_writable($cacheDir)) {
			// TODO: Error message
			return '';
		}

        $cacheOptions = [
            'cacheDir' => $cacheDir,
            'prefix' => md5($cssFilename),
        ];
        $parser = new \ScssPhp\ScssPhp\Compiler($cacheOptions);
        if (file_exists($scssFilename)) {

			$parser->addVariables($vars);
			$parser->setOutputStyle($outputStyle);



			if ($useSourceMap) {
				$parser->setSourceMap(Compiler::SOURCE_MAP_INLINE);

				$parser->setSourceMapOptions([
					'sourceMapWriteTo' => $cssFilename . '.map',
					'sourceMapURL' => $cssRelativeFilename . '.map',
					'sourceMapBasepath' => $sitePath,
					'sourceMapRootpath' => '/',
				]);
			}

			$code = '';
			foreach($dependencies as $dependency){
				$code .= '@import "'.$dependency.'";'.PHP_EOL;
			}
			$code .= '@import "' . $scssFilename . '";';

            $result = $parser->compileString($code);

            GeneralUtility::writeFile($cssFilename, $result->getCss());

            return $result->getCss();
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
    protected function calculateContentHash(string $scssFilename, string $vars = ''): string
    {
        if (in_array($scssFilename, self::$visitedFiles, true)) {
            return '';
        }
        self::$visitedFiles[] = $scssFilename;

        $content = file_get_contents($scssFilename);
        $pathInfo = pathinfo($scssFilename);

        $hash = hash('sha1', $content);
        if ($vars !== '') {
            $hash = hash('sha1', $hash . $vars);
        } // hash variables too

        $imports = $this->collectImports($content);
        foreach ($imports as $import) {
            $hashImport = '';


            if (file_exists($pathInfo['dirname'] . '/' . $import . '.scss')) {
                $hashImport = $this->calculateContentHash($pathInfo['dirname'] . '/' . $import . '.scss');
            } else {
                $parts = explode('/', $import);
                $filename = '_' . array_pop($parts);
                $parts[] = $filename;
                if (file_exists($pathInfo['dirname'] . '/' . implode('/', $parts) . '.scss')) {
                    $hashImport = $this->calculateContentHash($pathInfo['dirname'] . '/' . implode('/',
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
    protected function collectImports(string $content): array
    {
        $matches = [];
        $imports = [];

        preg_match_all('/@import([^;]*);/', $content, $matches);

        foreach ($matches[1] as $importString) {
            $files = explode(',', $importString);

            array_walk($files, function(string &$file) {
                $file = trim($file, " \t\n\r\0\x0B'\"");
            });

            $imports = array_merge($imports, $files);
        }

        return $imports;
    }
}
