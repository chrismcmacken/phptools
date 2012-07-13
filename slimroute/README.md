SlimRoute
=========

Ever need to organize your code in a way that would promote easy testing?  You probably wanted to change your site from page-level controllers (index.php, users.php, login.php, etc) into maybe a front controller (one PHP file, all hits go through it) and nice, pretty URLs (/, /users/, /login/, etc).

I saw a link on [StackOverflow](http://stackoverflow.com/questions/115629/simplest-php-routing-framework) that mentioned a couple ways to route requests.  I was interested in the hierarchical style controller [Konstrukt](http://konstrukt.dk/), but it did far more than what I needed.  In the spirit of extremely lightweight code that performs just a single task, I wrote SlimRoute.  It only routes requests.  Konstrukt, Symfony, and many others handle form submissions, take care of templating, and provide a lot more features.  Use them if your needs extend beyond the scope of this project.

Then why have this?  We needed a lightweight controller for our lightweight site.  We also used UltraLite with WebRequest to handle the other things we need (both are also in the phptools repository).  We like tiny, simple, unencumbered things.

Usage
-----

First, make a root class that will handle requests at the top level.  Let's say that we want all top level requests handled by RootController, then all requests to "/user/" to go to the UserController.  Everything else should redirect to an index page.

    class RootController extends SlimRoute {
        // $name is the next portion of the URL that we will potentially
        // map to another class
        public function map($name) {
            if ($name == 'user') {
                return UserController;
            }
            $this->redirect('/');  // Redirect requests for anything else
        }
        // Generate content
        public function render() {
            echo "<a href=\"/user/\">Go to user section</a>\n";
        }
    }

Now we need a PHP file (controller.php) to link up to our class.

    require('../../path/to/phptools/autoload.php');
    require('../../path/to/your_own/autoload.php');
    $controller = new RootController();
    $controller->handle();

And we need to force all web requests to go to our controller.  In Apache, you can just set the DocumentRoot to the PHP file, similar to how this VirtualHost looks.

    <VirtualHost *:80>
        ServerName testsite.fuf.me
        DocumentRoot /home/user/project/controller.php
        KeepAlive On
        Alias /media/ "/home/user/project/media/"
        <Directory "/home/user/project/media">
            Options FollowSymlinks
            AllowOverride None
        </Directory>
    </VirtualHost>

After you make your own autoload.php and make sure it can load the RootController class, then you will be able to view the site and get a link that will go to the user section.  Unfortunately, clicking that link will throw an exception saying that the UserController does not exist, but we can fix that.  Let's make a new UserController class.  Since this is just an example, we want all GET requests to return a username as a form and POST requests to be able to update the username.  Hooking this up to your User class (not provided) is an exercise left up to you.

    class UserController extends SlimRoute {
        $user = null;
        // The map() function is not necessary since this does not
        // hand control off to another class.
        public function handleGet() {
            $userId = $this->nextComponent();  // Grab the next portion from the URL
            if (! empty($userId)) {
                $this->loadUser($userId);
            }
        }
        // Handle all POST requests to this controller
        public function handlePost() {
            // $this->request is an instance of WebRequest
            $id = $this->request->req('id');
            $username = $this->request->req('username');
            // Do not allow empty IDs or usernames
            if (empty($id) || empty($username)) {
                $this->redirect();
            }
            $this->loadUser($id);
            $this->user->username = $username;
            $this->user->save();
        }
        // Helper method to load a user and redirect if the user can't be loaded
        public function loadUser($id) {
            $this->user = User::load($userId);
            // If no user found, redirect to "/user/" to show the list of users
            if (! $user) {
                // Redirects to the URL of the user page (/user/)
                $this->redirect();
                // Also calls exit() automatically
            }
        }
        // If no user loaded, show a list
        // If a user loaded, show the form to change username
        public function render() {
            if (! $this->user) {
                // Maybe list user IDs?
                $idList = User::getIds();
                echo "<ul>\n";
                foreach (User::getIds() as $id) {
                    echo "<li><a href=\"" . urlencode($id) . "\">" . htmlentities($id) . "</a></li>\n";
                }
                echo "</ul>\n";
            } else {
                echo "<form method=\"post\" action=\"" . $this->url() . "\">\n";
                echo "<input type=\"hidden\" name=\"id\" value=\"" . urlencode($this->user->id) . "\">\n";
                echo "<input type=\"text\" name=\"username\" value=\"" . urlencode($user->username) . "\">\n";
                echo "<input type=\"submit\" value=\"Update Username\">\n";
                echo "</form>\n";
            }
        }
    }

Whew.  That's a big example, but I hope you see how you can chain classes together with the `map()` function, and now I've illustrated the use of the WebRequest object a bit and two of the handle methods.

Advanced Usage
--------------

There is one feature that makes sense to have added to this router even though it's goal is to be as uncomplicated and simple as possible, and that's the ability to pass variables to the controller.

    $controller = new RootController();
    // Add the DB class, also from phptools
    $controller->db = DB::connect('mysql://localhost/testdb');
    $controller->apiKey = 'abcd1234';
    $controller->handle();

And now any controller that gets called can have access to the things you have assigned.

    class SomeControllerThatIsNotRootController extends SlimRoute {
        public function render() {
            // Bad form, but this is an example
            $res = $this->db->select('*', 'TableName');
            echo "There are " . $res->count() . " records.<br>\n";
            echo "API key is " . $this->apiKey . "<br>\n";
        }
    }

Methods
-------

### public function __construct($uri = null, $parent = null)
Constructor, which is intended to only be used internally.

### public function __get($name)
Used for the magic getter/setter functionality to pass variables to all of the controllers.

### public function __set($name, $value)
Used for the magic getter/setter functionality to pass variables to all of the controllers.

### public function handle()
Determines the right controller to use by calling getController(), then calls one of the handle*() methods, and finally render().

### public function handleDelete()
Called if the DELETE method is used with the request.  This method is intended to be overridden if you want to add functionality for these requests.

### public function handleGet()
Called if the GET method is used with the request.  This method is intended to be overridden if you want to add functionality for these requests.

### public function handleHead()
Called if the Head method is used with the request.  This method is intended to be overridden if you want to add functionality for these requests.

### public function handlePost()
Called if the POST method is used with the request.  This method is intended to be overridden if you want to add functionality for these requests.

### public function handlePut()
Called if the PUT method is used with the request.  This method is intended to be overridden if you want to add functionality for these requests.

### public function getController()
Gets the controller that will be used to handle the request.  For the example above, this will be the UserController for all "/user/" requests.

### public function getUri()
Used to build URLs based on the paths that were all consumed by the parent classes.  Used internally mostly for the url() method.

### protected function map($target)
If $target matches anything that you want to pawn off to another class, then return the
other class's name.  Otherwise, don't return anything, return null, or don't override this method.

### protected function nextComponent()
Grabs the next portion from the URI and return it.  If there isn't anything, returns null.

### protected function redirect($uri = '')
Redirects to $uri on the current site.  If nothing specified, it redirects to the URL of the current module.  If you pass '/', it redirects to the top level of the site.  This is not to be used with URLs that go off the site.  Also calls `exit()` so one never has to worry about the program continuing execution.

### protected function render()
Generates output, typically HTML.  Can return content or echo it.  Whatever is returned by render() is then returned by your call to handle(), so you can perform your output either inside the controller classes or outside.

### public function url($relativePath)
Generate a URL, relative to the currently executing controller (ie. the one returned by getController()).

License
-------
Like everything else in the phptools repository, we use a modified MIT license with a clause regarding advertising.  See the class for the full license.
