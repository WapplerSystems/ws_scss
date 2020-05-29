<?php
/**
 * SCSSPHP
 *
 * @copyright 2012-2017 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://leafo.github.io/scssphp
 */

namespace WapplerSystems\WsScss\Formatter;

use ScssPhp\ScssPhp\Formatter;
use ScssPhp\ScssPhp\Formatter\OutputBlock;

/**
 * Debug formatter
 *
 * @author Sven Wappler <typo3YYYY@wappler.systems>
 */
class Autoprefixer extends Formatter
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->indentLevel = 0;
        $this->indentChar = '  ';
        $this->break = '';
        $this->open = '{';
        $this->close = '}';
        $this->tagSeparator = ',';
        $this->assignSeparator = ':';
        $this->keepSemicolons = false;
    }

    /**
     * {@inheritdoc}
     */
    public function blockLines(OutputBlock $block)
    {
        $inner = $this->indentStr();

        $glue = $this->break . $inner;

        foreach ($block->lines as $index => $line) {

            require_once __DIR__ . '/../../Resources/Private/csscrush/CssCrush.php';

            $line = csscrush_string('.crushwrapper {'.$line.'}',['minify' => true,'boilerplate' => false, 'formatter' => 'single-line', 'versioning' => false]);
            $line = str_replace(['.crushwrapper {','}'],[''],$line);
            $line = preg_replace( "/\r|\n/", "", $line );

            if (0 === strpos($line, '/*') && $line[2] !== '!') {
                unset($block->lines[$index]);
            } elseif (0 === strpos($line, '/*!')) {
                $block->lines[$index] = '/*' . substr($line, 3);
            } else {
                $block->lines[$index] = $line;
            }
        }

        $this->write($inner . implode($glue, $block->lines));

        if (! empty($block->children)) {
            $this->write($this->break);
        }
    }
}
