<?php
declare (strict_types=1);

namespace WapplerSystems\WsScss\ViewHelpers\Asset;


use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Fluid\ViewHelpers\Asset\CssViewHelper;
use WapplerSystems\WsScss\Compiler;

class ScssViewHelper extends CssViewHelper
{

    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument('scssVariables', 'mixed', 'An optional array of variables to be set inside the SCSS context', false);
        $this->registerArgument('outputfile', 'string', '', false);
        $this->registerArgument('forceOutputLocation', 'string', 'force "inline" or "file"', false, '');
    }


    /**
     * @throws FileDoesNotExistException
     * @throws \ScssPhp\ScssPhp\Exception\CompilerException
     */
    public function render(): string
    {

        $identifier = (string)$this->arguments['identifier'];
        $attributes = $this->tag->getAttributes();
        $variables = (array)$this->arguments['scssVariables'];
        $useSourceMap = false;
        $formatter = null;
        $outputFile = $this->arguments['outputfile'];
        $forceOutputLocation = $this->arguments['forceOutputLocation'];

        // boolean attributes shall output attr="attr" if set
        if ($attributes['disabled'] ?? false) {
            $attributes['disabled'] = 'disabled';
        }

        $file = $this->tag->getAttribute('href');
        unset($attributes['href']);

        $options = [
            'priority' => $this->arguments['priority']
        ];

        if ($file !== null) {

            $cssFile = Compiler::compileFile($file, $variables, $outputFile);

            if ($forceOutputLocation === 'inline') {
                $content = file_get_contents($cssFile);
                $this->assetCollector->addInlineStyleSheet($identifier, $content, $attributes, $options);
            } else {
                $this->assetCollector->addStyleSheet($identifier, $cssFile, $attributes, $options);
            }

        } else {
            $content = (string)$this->renderChildren();
            $cssFile = Compiler::compileSassString($content, $variables, $outputFile);

            if ($forceOutputLocation === 'file') {
                $this->assetCollector->addStyleSheet($identifier, $cssFile, $attributes, $options);
            } else {
                $content = file_get_contents($cssFile);
                $this->assetCollector->addInlineStyleSheet($identifier, $content, $attributes, $options);
            }

        }
        return '';
    }

}
