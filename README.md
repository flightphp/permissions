# Flight Permissions Plugin
[![Latest Stable Version](https://poser.pugx.org/flightphp/permissions/v)](https://packagist.org/packages/flightphp/permissions)
[![License](https://poser.pugx.org/flightphp/permissions/license)](https://packagist.org/packages/flightphp/permissions)
[![PHP Version Require](https://poser.pugx.org/flightphp/permissions/require/php)](https://packagist.org/packages/flightphp/permissions)
[![Dependencies](https://poser.pugx.org/flightphp/permissions/dependents)](https://packagist.org/packages/flightphp/permissions)

Permissions are an important part to any application. Even in a RESTful API you'll need to check that the API key has permission to perform the action requested. In some cases it makes sense to handle authentication in a middleware, but in other cases, it's more helpful to have a standard set of permissions. 

This library follows a CRUD based permissions systems. See [basic example](#basic-example) for example on how this is accomplished.

## Basic Example

Let's assume you have a feature in your application that checks if a user is logged in. You can create a permissions object like this:

```php
// index.php
require 'vendor/autoload.php';

// some code 

// then you probably have something that tells you who the current role is of the person
// likely you have something where you pull the current role
// from a session variable which defines this
// after someone logs in, otherwise they will have a 'guest' or 'public' role.
$current_role = 'admin';

// setup permissions
$permission = new \flight\Permission($current_role);
$permission->defineRule('loggedIn', function($current_role) {
	return $current_role !== 'guest';
});

// You'll probably want to persist this object in Flight somewhere
Flight::set('permission', $permission);
```

Then in a controller somewhere, you might have something like this.

```php
<?php

// some controller
class SomeController {
	public function someAction() {
		$permission = Flight::get('permission');
		if ($permission->has('loggedIn')) {
			// do something
		} else {
			// do something else
		}
	}
}
```

You can also use this to track if they have permission to do something in your application.
For instance, if your have a way that users can interact with posting on your software, you can 
check if they have permission to perform certain actions.

```php
$current_role = 'admin';

// setup permissions
$permission = new \flight\Permission($current_role);
$permission->defineRule('post', function($current_role) {
	if($current_role === 'admin') {
		$permissions = ['create', 'read', 'update', 'delete'];
	} else if($current_role === 'editor') {
		$permissions = ['create', 'read', 'update'];
	} else if($current_role === 'author') {
		$permissions = ['create', 'read'];
	} else if($current_role === 'contributor') {
		$permissions = ['create'];
	} else {
		$permissions = [];
	}
	return $permissions;
});
Flight::set('permission', $permission);
```

Then in a controller somewhere...

```php
class PostController {
	public function create() {
		$permission = Flight::get('permission');
		if ($permission->can('post.create')) {
			// do something
		} else {
			// do something else
		}
	}
}
```

See how much fun this is? Let's install it and get started!

## Installation

Simply install with Composer

```php
composer require flightphp/permissions 
```

## Documentation

Head over to the [documentation page](https://docs.flightphp.com/awesome-plugins/permissions) to learn more about usage and how cool this thing is! :)

## License

MIT
