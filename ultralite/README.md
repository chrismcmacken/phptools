Ultralite PHP Templates
=======================

Initially spawned from an idea from PHPUnit's [TextTemplate], this now inherits
much of what [Templum] does, since the syntax is lighter and easier.  All extra
functionality, like internationalization, variable scoping, inheritance, HTML
escaping and caching has been removed.  Things like that could be added back in
by overriding the class as needed.

Goals:

* Super lightweight
* Zero configuration
* Minimal functionality (ie. "If it's a feature, don't have it")
* Extremely easy template syntax
* Works really well for text output, which cares about newlines more than HTML

How It Works
------------

Ultralite reads your template file as a big text file, then performs a couple
regular expression swaps on it to change it into fully executing PHP code.  An
output buffer is started and the template is executed.  There's not much more
to it.

Syntax
------

First, let's show off the PHP code necessary to load the class, populate some
variables and finally parse a sample template.  For this example, templates are
stored in the `template/` subdirectory in your project.

    <?php
    require_once('ultralite.class.php');
    $ul = new Ultralite(__DIR__ . '/templates/');
    $ul->name = 'Jenny Doe';
    $ul->accountNumber = '8675309';
    echo $ul->render('sample.tpl');

Your template can be straight text, it can include PHP, and it can also use
some special syntax that is specific to Ultralite.

    This can be a sample template file.
    I can show you the customer's name with <?php echo $name ?>
    Alternately, I can use {{name}}, which is better.

Variables assigned in your PHP can be used as `{{var_name}}`, which the
template engine will parse specially.  The UltraliteHtml class will also call
`htmlentities()` on variables displayed this way to add a layer of security and
help avoid injection attacks.

    As in the previous example, you can include <?php echo "PHP tags"; ?>.
    A shorthand for this is [[ echo "PHP tags"; ]]
    You can even use them across multiple lines:
    [[
        echo "The time is: ";
        $dt = new DateTime();
        echo $dt->format('r');
    ]]

Lastly, a single line of PHP can be included in your template.

    These two are equivalent:
    <?php echo "Hello!\n"; ?>
    @echo "Hello!\n"

Using PHP's [Alternate Syntax], one can make loops shorter and more readable in
your templates.  You can even include other templates.

    @if (count($cart)):
        @foreach ($cart as $item):
            {{item->name}} costs {{$item->price}}
			@$this->inc('item_detail.tpl');
        @endforeach
    @else:
        There is nothing in your cart
    @endif

Again, if you want a little more out of a template engine, I strongly urge you
to check out [Templum].  For even more power, maybe look at [Twig], [Dwoo],
[Smarty] or many other template engines.

[Alternate Syntax]: http://us3.php.net/alternative_syntax
[Dwoo]: http://dwoo.org/
[Smarty]: http://www.smarty.net/
[Templum]: http://templum.electricmonk.nl/
[TextTemplate]: https://github.com/sebastianbergmann/php-text-template
[Twig]: http://twig.sensiolabs.org/
