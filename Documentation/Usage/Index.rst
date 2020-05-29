.. include:: Images.txt

.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. ==================================================
.. DEFINE SOME TEXTROLES
.. --------------------------------------------------
.. role::   underline
.. role::   typoscript(code)
.. role::   ts(typoscript)
   :class:  typoscript
.. role::   php(code)


Usage
-----

Use the includeCSS command and define the output dir:

::

   page.includeCSS {
   
     bootstrap = fileadmin/bootstrap/sass/bootstrap.scss
     bootstrap.outputdir = fileadmin/bootstrap/css/
   
   }

You can also leave off the  *outputdir* . Then the extension writes
the css files into the typo3temp/ws\_scss dir.

Another way is to define the output file

::

   page.includeCSS {

     bootstrap = fileadmin/bootstrap/sass/bootstrap.scss
     bootstrap.outputfile = fileadmin/bootstrap/css/mybootstrap.css

   }


Variables
---------

You can set sass variables in typoscript in template setup
part of your template:

::

   plugin.tx_wsscss.variables {
     var1 = #000
     var2 = #666
   }

If you combine *outputfile* and variables you can create different bootstrap styles with same files.


Formatter
---------

It’s possible to customize the formatting of the output CSS by changing the default formatter.

- ScssPhp\ScssPhp\Formatter\Expanded
- ScssPhp\ScssPhp\Formatter\Nested (default)
- ScssPhp\ScssPhp\Formatter\Compressed
- ScssPhp\ScssPhp\Formatter\Compact
- ScssPhp\ScssPhp\Formatter\Crunched


::

   page.includeCSS {

       bootstrap = {$plugin.tx_demotemplate.filepaths.scss}bootstrap.scss
       bootstrap.outputdir = {$plugin.tx_demotemplate.filepaths.css}
       bootstrap.formatter = ScssPhp\ScssPhp\Formatter\Compressed
   }


Debugging
---------

You can output the original SCSS line numbers within the compiled CSS file for better frontend debugging.

::

   page.includeCSS {

       bootstrap = {$plugin.tx_demotemplate.filepaths.scss}bootstrap.scss
       bootstrap.outputdir = {$plugin.tx_demotemplate.filepaths.css}
       bootstrap.linenumber = true
   }


For developing it is practical, if you force to render the template
(switch off the TYPO3 template cache). Then the scss files will be
compiled after modification and you can see the result of your
changes. Go into your backend user settings and use this command:

|img-3|


Credits
-------

Sven Wappler. `TYPO3 Agentur Aachen <http://www.wapplersystems.de/>`_



