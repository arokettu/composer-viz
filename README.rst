ComposerViz
===========

.. image:: https://img.shields.io/packagist/v/sandfoxme/composer-viz.svg?maxAge=2592000
   :target: https://packagist.org/packages/sandfoxme/composer-viz
   :alt: Packagist
.. image:: https://img.shields.io/github/license/sandfoxme/composer-viz.svg?maxAge=2592000
   :target: https://opensource.org/licenses/MIT
   :alt: Packagist
.. image:: https://img.shields.io/codeclimate/maintainability/sandfoxme/composer-viz.svg?maxAge=2592000
   :target: https://codeclimate.com/github/sandfoxme/composer-viz
   :alt: Code Climate

A Composer plugin to generate dependency graph by GraphViz inspired by ``bundle viz``

Installation
------------

Install globally so you can use it on any project

.. code-block:: bash

   composer global require sandfoxme/composer-viz:^2.0

Example
-------

Running composer-viz 2.0.0 for itself: ``composer viz --output=composer-viz-2.0.0.svg``

.. image:: https://sandfoxme.github.io/composer-viz/composer-viz-2.0.0.svg
   :target: https://sandfoxme.github.io/composer-viz/composer-viz-2.0.0.svg
   :width: 100%
   :alt: selfie

Usage
-----

.. code-block::

   composer viz [-o|--output OUTPUT] [-f|--format FORMAT] 
                [--no-dev] [--no-php] [--no-ext] [--no-platform] 
                [--no-pkg-versions] [--no-dep-versions] [--no-versions]

-o OUTPUT, --output=OUTPUT  Set output file. If not set, the result will be displayed from temporary file
-f FORMAT, --format=FORMAT  Set output file format. Useful if it is not detected from ``--output``
--no-dev                    Do not show development dependencies
--no-php                    Do not show PHP as a dependency (php and php64)
--no-ext                    Do not show extensions as dependencies
--no-platform               ``--no-php`` + ``--no-ext``
--no-pkg-versions           Do not show package versions on graph vertices
--no-dep-versions           Do not show package versions on graph edges
--no-versions               ``--no-pkg-versions`` + ``--no-dep-versions``

GraphViz
--------

You should install GraphViz on your system first.

Use this in Ubuntu:

.. code-block:: bash

   sudo apt-get install graphviz

Sometimes you may need additional packages like in Fedora you should also install ``graphviz-gd`` to be able
to export images, like this:

.. code-block:: bash

   # dot, svg and postscript work without graphviz-gd but png, jpeg and gif don't
   sudo dnf install graphviz graphviz-gd
