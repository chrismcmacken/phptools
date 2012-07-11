Ultralite PHP Templates
=======================

Initially spawned from an idea from PHPUnit's [TextTemplate], this now inherits
much of what [Templum] does, since the syntax is lighter and easier.  All extra
functionality, like internationalization, intuitive variable scoping,
inheritance, HTML escaping and caching has been removed from the main class.
Features like that could be added back in by overriding the class as needed.

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
    Alternately, I can use {{$name}}, which is better.

Variables assigned in your PHP can be used as `{{$var_name}}`, which the
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
            {{$item->name}} costs {{$item->price}}
			@$this->inc('item_detail.tpl');
        @endforeach
    @else:
        There is nothing in your cart
    @endif

More About Including Templates
------------------------------

Templates operate within a method of a class.  First, all variable assigned to
the template engine are `extract()`ed into local variables, which lets the
engine more easily handle `{{$name}}` and `[[ echo $name ]]` style constructs.
You're happier too, since you may use PHP code to assign variables, and then
you don't need to remember if you should use `{{$this->name}}` or `{{$name}}`
in your templates.

Included templates also get all assigned variables inherited, but none of your
local variables.  This can cause confusion.  Here is some PHP code that will
help illustrate my point.

    <?php // including_templates.php
    require_once('../ultralite.class.php');
    $ul = new Ultralite(__DIR__);
    $ul->name = "Jane Doe";
    $ul->age = 42;
    $cat = new stdClass();
    $cat->name = "Whiskers";
    $ul->pets = array($cat);
    $ul->favoritePet = $cat;
    echo $ul->render('test1.tpl');
    echo $ul->render('test2.tpl');
    echo $ul->render('test3.tpl');

Wow, that's a lot of setup, but it will really help out with the examples we're
going to use.  I will first give you the contents of the templates.  Then, I'll
show you the output of the template and why it works that way.  All of these
files are in the `examples/` directory.

    # test1.tpl
    Local: {{$name}} is {{$age}} years old.
    This:  {{$this->name}} is {{$this->age}} years old.
    @$age /= 2
    Local: {{$name}} is {{$age}} years old. -- CHANGED
    This:  {{$this->name}} is {{$this->age}} years old. -- SAME

Results:

    # test1.tpl
    Local: Jane Doe is 42 years old.
    This:  Jane Doe is 42 years old.
    Local: Jane Doe is 21 years old. -- CHANGED
    This:  Jane Doe is 42 years old. -- SAME

You'll see that we changed the local variable, `$age`, but not the variable
assigned to the class, `$this->age`.  However, you'll notice that when we
change an object, the object is changed with either reference method since
objects are always passed by reference.

    # test2.tpl
    Local: {{$favoritePet->name}} is the favorite.
    This:  {{$this->favoritePet->name}} is the favorite.
    @$favoritePet->name = 'Meow Meow';
    Local: {{$favoritePet->name}} is the favorite. -- CHANGED
    This:  {{$this->favoritePet->name}} is the favorite. -- CHANGED

Results:

    # test2.tpl
    Local: Whiskers is the favorite.
    This:  Whiskers is the favorite.
    Local: Meow Meow is the favorite. -- CHANGED
    This:  Meow Meow is the favorite. -- CHANGED

Growing upon this concept, we now include a template that is supposed to show
the cat names.  This one includes a child template.

    # test3.tpl
    -- This won't work
    @foreach ($pets as $pet):
    @$this->inc('test3b.tpl')
    @endforeach
    -- This does work
    @foreach ($pets as $pet):
    @$this->inc('test3b.tpl', array('pet', $pet))
    @endforeach

    # test3b.tpl
    @if (isset($pet)):
    Pet name: {{$pet->name}}
    @else:
    No pet object passed.
    @endif

Results:

    # test3.tpl
    -- This won't work
    # test3b.tpl
    No pet object passed.
    -- This does work
    # test3b.tpl
    No pet object passed.

The foreach loop in `test3.tpl` assigns the pets into a local variable, `$pet`.
This is not assigned to the template engine and thus is not inherited when you
include a child template via `$this->inc('test3b.tpl')`.  To get around this
problem, you can pass an array of additional values to assign in the child
template when you call `$this->inc()`, like how we do it for the second half of
`test3.tpl`.

It's a little complicated and I hope that I didn't lose you.

Looking For More?
-----------------

Need better variable handling?  Want security so arbitrary PHP can't be called
from inside your templates?  If you need a little more out of a template
engine, I strongly urge you to check out [Templum].  For even more power, maybe
look at [Twig], [Dwoo], [Smarty] or many other template engines.

[Alternate Syntax]: http://us3.php.net/alternative_syntax
[Dwoo]: http://dwoo.org/
[Smarty]: http://www.smarty.net/
[Templum]: http://templum.electricmonk.nl/
[TextTemplate]: https://github.com/sebastianbergmann/php-text-template
[Twig]: http://twig.sensiolabs.org/
