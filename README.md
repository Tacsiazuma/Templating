Templating
==========

The HTML template engine for my framework

Usage:

Create a new instance of the Templating class providing the template file name with or without the '.tpl' extension. The constructor can be overloaded with another string to provide an output file name.

Then you can assign variables to the map where the engine gonna get the values.

After you've finished assigning, then you can either display the compiled content by calling the Display method (Notice: You can provide a TTL in seconds for a simple page caching.) or you can create the file with the create method.

The .tpl files are basically HTML files with TPL syntax inserted.

The base TPL syntax blocks are {* *} for comments. (Notice: These aren't going to appear in the compiled html file.)

The {# } blocks contains control structure start/end tags.

The {% } blocks contains identifiers which should've been assigned to the engine before the compilation process.


Identifiers
===========


Control structures
==================
