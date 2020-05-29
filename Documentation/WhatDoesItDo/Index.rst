

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


What does it do?
^^^^^^^^^^^^^^^^

This extension compiles the scss (sass) files into the css file format. To
include your scss file, you can use the page.includeCSS typoscript
command as usual.

This extensions uses the SCSSPHP compiler: https://scssphp.github.io/scssphp/

The extension comes with a cache function. That means, that the scss
files will be compiled only if necessary.

You can pass values via typoscript to the scss files.

