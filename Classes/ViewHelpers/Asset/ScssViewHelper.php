<?php
declare (strict_types=1);

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

class ScssViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * This VH does not produce direct output, thus does not need to be wrapped in an escaping node
     *
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Rendered children string is passed as CSS code,
     * there is no point in HTML encoding anything from that.
     *
     * @var bool
     */
    protected $escapeChildren = true;

    protected AssetCollector $assetCollector;

    public function injectAssetCollector(AssetCollector $assetCollector): void
    {
        $this->assetCollector = $assetCollector;
    }

    public function initialize(): void
    {
        // Add a tag builder, that does not html encode values, because rendering with encoding happens in AssetRenderer
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
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('as', 'string', 'Define the type of content being loaded (For rel="preload" or rel="prefetch" only).', false);
        $this->registerTagAttribute('crossorigin', 'string', 'Define how to handle crossorigin requests.', false);
        $this->registerTagAttribute('disabled', 'bool', 'Define whether or not the described stylesheet should be loaded and applied to the document.', false);
        $this->registerTagAttribute('href', 'string', 'Define the URL of the resource (absolute or relative).', false);
        $this->registerTagAttribute('hreflang', 'string', 'Define the language of the resource (Only to be used if \'href\' is set).', false);
        $this->registerTagAttribute('importance', 'string', 'Define the relative fetch priority of the resource.', false);
        $this->registerTagAttribute('integrity', 'string', 'Define base64-encoded cryptographic hash of the resource that allows browsers to verify what they fetch.', false);
        $this->registerTagAttribute('media', 'string', 'Define which media type the resources applies to.', false);
        $this->registerTagAttribute('referrerpolicy', 'string', 'Define which referrer is sent when fetching the resource.', false);
        $this->registerTagAttribute('rel', 'string', 'Define the relationship of the target object to the link object.', false);
        $this->registerTagAttribute('sizes', 'string', 'Define the icon size of the resource.', false);
        $this->registerTagAttribute('type', 'string', 'Define the MIME type (usually \'text/css\').', false);
        $this->registerTagAttribute('nonce', 'string', 'Define a cryptographic nonce (number used once) used to whitelist inline styles in a style-src Content-Security-Policy.', false);
        $this->registerArgument(
            'identifier',
            'string',
            'Use this identifier within templates to only inject your CSS once, even though it is added multiple times.',
            true
        );
        $this->registerArgument(
            'priority',
            'boolean',
            'Define whether the CSS should be included before other CSS. CSS will always be output in the <head> tag.',
            false,
            false
        );
        $this->registerArgument('scssVariables', 'mixed', 'An optional array of variables to be set inside the SCSS context', false);
        $this->registerArgument('outputfile', 'string', '', false);
        $this->registerArgument('forcedOutputLocation', 'string', 'force "inline" or "file"', false, '');
    }


    /**
     * @return string
     * @throws FileDoesNotExistException
     * @throws SassException
     * @throws NoSuchCacheException
     * @throws ContentRenderingException
     */
    public function render(): string
    {

        $identifier = (string)$this->arguments['identifier'];
        $attributes = $this->tag->getAttributes();
        $variables = (array)$this->arguments['scssVariables'];
        $outputFile = $this->arguments['outputfile'];
        $forcedOutputLocation = $this->arguments['forcedOutputLocation'];

        // boolean attributes shall output attr="attr" if set
        if ($attributes['disabled'] ?? false) {
            $attributes['disabled'] = 'disabled';
        }

        $file = $this->tag->getAttribute('href');
        unset($attributes['href']);

        $options = [
            'priority' => $this->arguments['priority']
        ];

        $variables = $this->cleanVariables($variables);

        if ($file !== null) {

            $scssFilePath = GeneralUtility::getFileAbsFileName($file);
            if ($scssFilePath === '') {
                throw new ContentRenderingException('Could not resolve the path to the SCSS file. Probably the path is not correct! Path: '.$file);
            }
            $pathChunks = explode('/',PathUtility::getAbsoluteWebPath($scssFilePath));
            if (self::usesComposerClassLoading()) {
                $assetPath = implode('/',array_splice($pathChunks,0,3)).'/';
            } else {
                $assetPath = implode('/',array_splice($pathChunks,0,6)).'/';
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

    private function cleanVariables($variables): array
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $variables[$key] = $this->cleanVariables($variables[$key]);
            }
            if (empty($variables[$key])) {
                unset($variables[$key]);
            }
        }
        return $variables;
    }

    protected static function usesComposerClassLoading(): bool
    {
        return defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
    }

}
