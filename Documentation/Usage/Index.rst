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
     bootstrap.outputdir = typo3temp/assets/css/
   
   }

You can also leave off the  *outputdir* . Then the extension writes
the css files into the typo3temp/ws\_scss dir.

Another way is to define the output file

::

   page.includeCSS {

     bootstrap = EXT:sitepackage/Resources/Private/SCSS/bootstrap.scss
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

Formatters are deprecated by the scssphp compiler and not supported anymore.


OutputStyle
-----------

The following values are possible:

- compressed (default)
- expanded


::

   page.includeCSS {
       bootstrap = EXT:sitepackage/Resources/Private/SCSS/bootstrap.scss
       bootstrap.outputStyle = expanded
   }


Debugging
---------

You can use a source map.

::

   page.includeCSS {
       bootstrap = EXT:sitepackage/Resources/Private/SCSS/bootstrap.scss
       bootstrap.sourceMap = true
   }


For developing it is practical, if you force to render the template
(switch off the TYPO3 template cache). Then the scss files will be
compiled after modification and you can see the result of your
changes. Go into your backend user settings and use this command:

|img-3|


Credits
-------

Sven Wappler. `TYPO3 Agentur Aachen <https://wappler.systems/>`_



