Variable Stream Wrapper
=======================

Ever want to load `include()` a string instead of a file?  Looking for an
alternate to `eval()`?  Have your desired file's content in
`$GLOBALS['varname']` and you want to access it like a file instead?  Welcome
to stream wrappers!  This stream wrapper will let you take any content from a
global variable and access it as though it was a file.

How To Use
----------

This is fantastic because it is easy.  I shall illustrate:

    // Load the object, which registers the "var" stream wrapper
    require_once('variablestream.class.php');

    // Set up some content
    $GLOBALS['blah'] = "This is some sample content.\n';
    
    // And now the magic happens
    echo file_get_contents('var://blah');
    // This echos the string, "This is some sample content.\n"

Done.  Simple, eh?

Uses For This Class
-------------------

You have some code that generates a text file, but you have control over where
the file gets created.  Your unit test doesn't think that writing to the
filesystem is a good idea (probably a safe bet), so let's write to a variable!

    $GLOBALS['contents'] = '';
    createTextFile('var://contents');
    // $GLOBALS['contents'] now contains what was written to the "file"

Let's get around using `eval()` and still generate some code that should be
executed.  I have a test where three underscores in a row should be replaced
with `"_ws_"`.

    $php = file_get_contents('source_file.php');
    $GLOBALS['phpToTest'] = preg_replace('/___/', '_ws_', $php);
    require('var://phpToTest');

There are probably other uses, but I've only come across the ones listed above.
