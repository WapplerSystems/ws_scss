<?php
namespace WapplerSystems\WsScss\Compiler;


use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ScssCompiler
{

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
     * @throws \ScssPhp\ScssPhp\Exception\CompilerException
     */
    public static function compileScss(
        string $scssFilename,
        string $cssFilename,
        array $vars = [],
        bool $showLineNumber = false,
        $formatter = null,
        $cssRelativeFilename = null,
        bool $useSourceMap = false): string {

        if (!class_exists(\ScssPhp\ScssPhp\Version::class, true)) {
            $extPath = ExtensionManagementUtility::extPath('ws_scss');
            require_once $extPath . 'Resources/Private/scssphp/scss.inc.php';
        }
        $sitePath = Environment::getPublicPath() . '/';

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
        GeneralUtility::mkdir_deep($cacheOptions['cacheDir']);
        $parser = new \ScssPhp\ScssPhp\Compiler($cacheOptions);
        if (file_exists($scssFilename)) {

            $parser->setVariables($vars);

            if ($showLineNumber) {
                $parser->setLineNumberStyle(\ScssPhp\ScssPhp\Compiler::LINE_COMMENTS);
            }
            if ($formatter !== null) {
                $parser->setFormatter($formatter);
            }

            if ($useSourceMap) {
                $parser->setSourceMap(\ScssPhp\ScssPhp\Compiler::SOURCE_MAP_INLINE);

                $parser->setSourceMapOptions([
                    'sourceMapWriteTo' => $cssFilename . '.map',
                    'sourceMapURL' => $cssRelativeFilename . '.map',
                    'sourceMapBasepath' => $sitePath,
                    'sourceMapRootpath' => '/',
                ]);
            }

            $css = $parser->compile('@import "' . $scssFilename . '";');
            GeneralUtility::writeFile($cssFilename, $css);
            return $css;
        }
        return '';
    }

}
