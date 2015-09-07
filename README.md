Yii2 Meta ActiveRecord
======================
Providers WordPress-like meta table functionality for tables represented by an ActiveRecord class

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist mipotech/yii2-meta-activerecord "*"
```

or add

```
"mipotech/yii2-meta-activerecord": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code as follows  :

```php
<?php

use mipotech\metaActiveRecord\MetaActiveRecord;

class User extends MetaActiveRecord
{
	...
}
```

To create a new record:

```php
$user = new User;
$user->firstName = 'John';		// not part of user table schema
$user->lastName = 'Doe';		// not part of user table schema
$user->username = 'johndoe1';
$user->password = Yii::$app->security->generatePasswordHash('123456');
$user->email = 'johndoe@foobar.com';
$user->registered = time();
$ret = $user->save();
```

To update an existing record:

```php
$user = User::findOne(['email'=>'johndoe@foobar.com']);
$user->firstName = 'Jane';		// not part of user table schema
$user->lastName = 'Doe1';		// not part of user table schema
$user->save();
```
