<?php
declare(strict_types=1);

namespace WapplerSystems\WsScss\ViewHelpers\Asset;

use ScssPhp\ScssPhp\Exception\SassException;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;
use WapplerSystems\WsScss\Compiler;

/**
 * SCSS Asset ViewHelper — compiles SCSS to CSS and registers it via AssetCollector.
 *
 * Usage (file):
 *   <wsscss:asset.scss identifier="main" href="EXT:my_ext/Resources/Private/Scss/main.scss" />
 *
 * Usage (inline):
 *   <wsscss:asset.scss identifier="inline">
 *     $color: red; body { background: $color; }
 *   </wsscss:asset.scss>
 */
final class ScssViewHelper extends AbstractTagBasedViewHelper
{
    protected $escapeOutput = false;
    protected $escapeChildren = true;

    public function __construct(
        private readonly AssetCollector $assetCollector,
    ) {
        parent::__construct();
    }

    public function initialize(): void
    {
        $this->setTagBuilder(
            new class () extends TagBuilder {
                public function addAttribute($attributeName, $attributeValue, $escapeSpecialCharacters = false): void
                {
                    parent::addAttribute($attributeName, $attributeValue, false);
                }
            }
        );
        parent::initialize();
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('disabled', 'bool', 'Define whether or not the described stylesheet should be loaded and applied to the document.');
        $this->registerArgument('identifier', 'string', 'Use this identifier within templates to only inject your CSS once, even though it is added multiple times.', true);
        $this->registerArgument('priority', 'boolean', 'Define whether the CSS should be included before other CSS. CSS will always be output in the <head> tag.', false, false);
        $this->registerArgument('scssVariables', 'mixed', 'An optional array of variables to be set inside the SCSS context');
        $this->registerArgument('outputfile', 'string', 'Path for the compiled CSS output file');
        $this->registerArgument('forcedOutputLocation', 'string', 'Force "inline" or "file"', false, '');
    }

    /**
     * @throws FileDoesNotExistException
     * @throws SassException
     * @throws NoSuchCacheException
     * @throws ContentRenderingException
     */
    public function render(): string
    {
        $identifier = (string)$this->arguments['identifier'];
        $attributes = $this->tag->getAttributes();
        $variables = (array)($this->arguments['scssVariables'] ?? []);
        $outputFile = $this->arguments['outputfile'] ?? null;
        $forcedOutputLocation = $this->arguments['forcedOutputLocation'] ?? '';

        if ($this->arguments['disabled'] ?? false) {
            $attributes['disabled'] = 'disabled';
        }

        $file = $attributes['href'] ?? null;
        unset($attributes['href']);

        $options = [
            'priority' => $this->arguments['priority'],
        ];

        $variables = $this->cleanVariables($variables);

        if ($file !== null) {
            $scssFilePath = GeneralUtility::getFileAbsFileName($file);
            if ($scssFilePath === '') {
                throw new ContentRenderingException('Could not resolve the path to the SCSS file. Path: ' . $file);
            }
            $pathChunks = explode('/', PathUtility::getAbsoluteWebPath($scssFilePath));
            if (self::usesComposerClassLoading()) {
                $assetPath = implode('/', array_splice($pathChunks, 0, 3)) . '/';
            } else {
                $assetPath = implode('/', array_splice($pathChunks, 0, 6)) . '/';
            }
            $variables['extAssetPath'] = $assetPath;

            $cssFile = Compiler::compileFile($file, $variables, $outputFile);

            if ($forcedOutputLocation === 'inline') {
                $content = file_get_contents($cssFile);
                $this->assetCollector->addInlineStyleSheet($identifier, $content, $attributes, $options);
            } else {
                $this->assetCollector->addStyleSheet($identifier, $cssFile, $attributes, $options);
            }
        } else {
            $content = (string)$this->renderChildren();
            $cssFile = Compiler::compileSassString($content, $variables, $outputFile);

            if ($forcedOutputLocation === 'file') {
                $this->assetCollector->addStyleSheet($identifier, $cssFile, $attributes, $options);
            } else {
                $content = file_get_contents($cssFile);
                $this->assetCollector->addInlineStyleSheet($identifier, $content, $attributes, $options);
            }
        }
        return '';
    }

    private function cleanVariables(array $variables): array
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $variables[$key] = $this->cleanVariables($value);
            }
            if (empty($variables[$key])) {
                unset($variables[$key]);
            }
        }
        return $variables;
    }

    private static function usesComposerClassLoading(): bool
    {
        return defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
    }
}