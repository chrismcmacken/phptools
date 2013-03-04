Friends in PHP
==============

*Only friends touch each other's private parts.*

Ever need access to an object's private or protected methods?  Private or protected properties?  How about static properties?  In testing legacy code or when working with code that is not my own I have often had this requirement.  Whether I can't modify it because it's under an infectious license, because I don't wish to extend each and every class or maybe I'm more lazy today.  For any reason, `Friend` is here.

Getting Started
---------------

1.  Instantiate an object that has the private or protected things.

    $myObject = new RestrictedClass();
    
2.  Make a `Friend`.

    $friend = new Friend($myObject);
    
3.  It's ready to use.  Call protected methods.  Get and set private variables with `$friend` instead of `$myObject`.  It mostly just work as you'd expect.

Bigger Example
--------------

First, you need to either use the PHPTools autoloader, found in the root of the repository, or use the Friend class itself.  The Friend class also uses the Skeleton class to make skeletons that are used when accessing static properties.

Second, I will only use private methods and properties, but the same thing works great with protected and public ones as well.

    class RestrictedClass {
        private $privateVar = 'private';
        
        private function privateMethod() {
            return 'OK';
        }
    }
    
    $privates = new RestrictedClass();
    
    // echo $privates->privateVar;  // Results in a fatal error
    // --> Cannot access private property SampleClass::$privateVar

    // echo $privates->privateMethod();  // Results in a fatal error
    // --> Call to private method SampleClass::privateMethod()
    
    $friend = new Friend($privates);
    
    echo $friend->privateMethod() . "\n";  // --> OK
    echo $friend->privateVar . "\n";  // --> private
    $friend->privateVar = 'not so private';
    echo $friend->privateVar . "\n";  // --> not so private

Class Methods
-------------

This class does not expose many methods publicly in order to avoid conflicting with your own method names.  The ones that are exposed are magic methods or are prefixed with `__friend` to keep them separate.

### __construct($object)

Creates a new `Friend` of the given object.  If not passed an instantiated object, this method throws an `ErrorException`.

### __call($name, $arguments)

Chains the call up to your object's methods, regardless of their accessibility.  See the Special Notes section, below.

### __friend_object()

Returns the original object that was friended.

### __friend_get_static($name)

Gets a static class property.  The only drawback to this is that you must make an instance of the class for `Friend` to be initialized.  If we had real access in PHP, we could just use RestrictedClass::$staticProperty instead.

### __friend_set_static($name)

Sets a static class property.  The reverse of `__friend_get_static()`.

### __get($name)

Gets a property from your restricted class.  See the Special Notes section, below.

### __isset($name)

Determines if a property is set or is not set.  This uses the actual property that exists, if possible.  See the Special Notes section, below.

### __set($name)

Sets a property on the object if one was defined.  See the Special Notes section for notes about chaining.

If the property is not found, this does not set a new public property on your object.

### __unset($name)

Sets a property to `null` on the object if one was defined.  See the Special Notes section for notes about chaining.

If the property is not found, this does not set a new public property to `null` on your object.

Special Notes
-------------

### Magic Methods

The `Friend` class automatically handles chaining calls to your magic methods if the method or property does not exist.  So, if you try to get a property called `foo`, the `Friend` class would look for `RestrictedClass->$foo` and fall back to calling `RestrictedClass->__get('foo')` if the property wasn't found.

This behavior differs from what things from *outside* your class might call.  This class prefers to call the method that exists first instead of seeing that we're not allowed access like a public call to the method.

### Accessing Properties

Because of the way that

    /**
     * Gets $this->object->$name and makes the property public so we can fetch it
     *
     * @param  $name
     * @return mixed The property value
     */
    public function __get($name) {
        $property = $this->getProperty($name);

		if (! is_null($property)) {
			return $property->getValue($this->object);
		}

		return $this->callMagic('__get', array($name), 'Property ' . $name . ' does not exist');
    } magic getters and setters work, you **can not** use code like the following to append a value to an array.

    // This does NOT work!
    $friend->someProperty[] = 'new array value';
    
Instead you must always get and then set the value.

    // This works
    $array = $friend->someProperty;
    $array[] = 'new array value';
    $friend->someProperty = $array;
    
License
-------

This is licensed under an MIT license with an additional non-advertising clause.  See the header in [Friend.php] or in the [license] for additional information.

[Friend.php]: Friend.php
[License]: ../docs/license
