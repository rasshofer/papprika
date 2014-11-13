# papprika

> papprika is a small but spicy application toolkit created to help you building awesome RESTful web applications with ease.

papprika ~~is in active development,~~ (see note below) offers you everything you need but nothing you don’t and is easy to use for both beginners and professionals. All you need to use papprika is a web server like Apache or nginx running PHP >=5.3. You’re going to love it.

## Deprecation (* 2012 † 2014)

Please note that papprika is abandoned and no longer maintained as of 2014. This repository is kept alive in order to provide the neccessary documentation for maintaining existing papprika projects. If you’re looking for an alternative to papprika, you should have a look at the awesome [Silex micro-framework](https://github.com/silexphp/Silex) and all the wonderful [Symfony components](http://symfony.com/de/components).

## Yet another PHP toolkit?

I made papprika because I wanted to help awesome people make awesome things. More than anything else, that’s entirely why I work on papprika whenever I can. It started off as a resource to help make better looking and more usable internal tools at [fapprik](http://fapprik.com/) and all along the development process, I knew others could use this to do cool things, too. Since I’m a huge proponent of open source tools, I wanted to give back to the community with this one, too.

## Getting started

### Download papprika

At first, you have to [download](https://github.com/rasshofer/papprika/zipball/master) the current papprika version and unzip it into your virtual host’s public directory.

### Set up your webserver

To have beautiful URLs like `http://example.com/about`, papprika requires you to do some little server configuration—which is pretty easy as you can find everything you need below.

#### Apache

If you’re running papprika on an Apache webserver, please check if your hosting provider enabled the `mod_rewrite` module (nowadays it’s usually enabled). If it’s enabled, you’ve to create a `.htaccess` file in the root directory of your papprika installation (if it doesn’t exist already) and add the following rewrite rules to it.

```apache
RewriteEngine on
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

#### nginx

If you’re running papprika on an nginx webserver, you’ve to add the following rewrite rules to your sites’s configuration.

```nginx
location / {

    root /path/to/papprika;
    index index.php;

    if (!-f $request_filename) {
        rewrite ^(.*)$ /index.php last;
        break;
    }

    if (!-d $request_filename) {
        rewrite ^(.*)$ /index.php last;
        break;
    }

}
```

## papprika\Application

To get started with papprika, all you need to do is `require` the `papprika.php` file and create an instance of `papprika\Application`. After your controller definitions, call the `run` method on your application.

```php
require_once './papprika.php';

$app = new papprika\Application();

// your controller-definitions

$app->run();
```

### Sub directories

Of course papprika also works in sub directories, too. Just pass the sub directory path through the constructor (without a trailing slash).

```php
$app = new papprika\Application('/sub/directory');
```

### Routing

Using papprika, you’ve to define routes and the appropriate controller(s), which are called when the route is matched. At the start of every route, you have to choose the appropriate method (`GET`, `POST`, `PUT` or `DELETE`), which describes the interaction with the resource. If you can’t decide which method to choose, you can use `$app->any('/home', …)` to use all four methods. Each route has one or more patterns, which define the paths that point to your resources and can include variables which you’re able to catch and work with. If you want to use more than one pattern for a route, simply pass an array instead of a string. Last but not least you’ve to provide your controllers using closures or methods (see »Scaling papprika« for more information and examples).

```php
$app->get('/home', function() {
    // do something
});
```

That was easy, huh? Don’t worry, the rest is equally simple. Let’s have a look at a more detailed example, which shows the advantages of closures—anonymous functions that can import stuff from outside of their definition. This is different from globals, because the outer state doesn’t have to be global.

```php
$news = array(
    1 => array(
        'date' => '2010-01-01',
        'author' => 'thomas',
        'title' => 'Hello World!',
        'text' => 'Hello World!'
    ),
    2 => array(
        'date' => '2011-01-01',
        'author' => 'thomas',
        'title' => 'papprika 0.1 released',
        'text' => 'Hello World!'
    ),
    3 => array(
        'date' => '2012-01-01',
        'author' => 'thomas',
        'title' => 'papprika 0.2 released',
        'text' => 'Hello World!'
    )
);

$app->get('/news', function() use ($news) {
    echo '<ul>';
    foreach($news as $id => $post) {
        echo '<li><a href="/news/'.$id.'">'.$post['title'].'</a></li>';
    }
    echo '</ul>';
});
```

Visiting `/news` will return a list of news. The `use`-statement tells the closure to import the `$news`-variable from the outer scope, which allows us to use it within the closure.

### Dynamic routing

Now we need another controller for viewing individual news, which is realized through dynamic routing.

```php
$app->get('/news/:id', function($id) use ($news) {
    if(!array_key_exists($id, $news)) {
        die('Post #'.$id.' wasn\'t found…');
    }
}, function($id) use ($news) {
    echo '<h1>'.$news[$id]['title'].'</h1>';
    echo '<h2>'.$news[$id]['date'].'</h2>';
    echo nl2br($news[$id]['text']);
});
```

Noticed the two changes? On the one hand we appended `:id` to the pattern, which causes that this route definition has a variable called `id`, which is passed to the closure. Of course you can use as many of these variables as you want. On the other hand we’re using two controllers for the pattern in this example. You may provide an unlimited number of these in-between callbacks, which must be callable closures/methods and which will be invoked in the order specified. This has no bigger reason, but allows you to separate your code (first check if the passed ID exists and then show it).

As you know everything about getting pages now, it’s time for a first post route: a contact form wherefore we’ll use the `mail`-function to send an email.

```php
$app->post('/contact', function() use ($app) {
    $message = $app->request('message');
    mail('&#112;&#097;&#112;&#112;&#114;&#105;&#107;&#097;&#064;&#116;&#104;&#111;&#109;&#097;&#115;&#114;&#097;&#115;&#115;&#104;&#111;&#102;&#101;&#114;&#046;&#099;&#111;&#109;', 'Contact', $message);
});
```

papprika offers you a simple opportunity to fetch transmitted variables depending on the request method. In the example above, `$app->request('message')` returns `$_POST['message']`. This works for all methods.

### Add routes from a configuration file

Sometimes it may make sense to separate your routes from your `index.php`—for example if you want to make your `index.php` reusable and independent of an application’s routes. Therefore you can add routes from a configuration file (INI files).

```php
$app->ini('./routing.ini');
```

```ini
[get]
/contact = Thomas\TestApp\Controller\ContactController::indexAction

[post]
/contact = Thomas\TestApp\Controller\ContactController::sendAction
```

As you may have noticed, you have to use methods for defining callbacks to your routes. For more information and examples, see »Scaling papprika«.

You should deny any access to this configuration file through your webserver for security reasons.

### Conditions

You may want to only match certain expressions in some cases—therefore papprika allows you to define conditions using regular expressions by calling `assert` on the controller object, which is returned by the routing methods.

```php
$app->get('/news/:id', function($id) use ($news) {
    // do something
})->assert('id', '\d+');```

```php
$app->get('/hello/:name', function($name) {
    echo 'Hello, '.$name;
})->assert('name', '([a-zA-Z]+)');
```

The example above makes sure the `id` argument is numeric, since `\d+` is a regular expression for matching any amount of digits.

### before / after / error

papprika allows you to run code before and after every request or if the requested page wasn’t found. All you need to do is passing closures/methods.

```php
$app->before(function() {
    echo '<!DOCTYPE html>';
    echo '<html>';
        echo '<head>';
            echo '<meta charset="utf-8">';
        echo '</head>';
        echo '<body>';
            echo '<header>Test-Application</header>';
})->after(function() {
            echo '<footer>© papprika</footer>';
        echo '</body>';
    echo '</html>';
})->error(function() {
    echo 'The page you requested couldn’t be found.';
});
```

As you see, `before` and `after` are pretty handsome for some header/footer stuff. Did you notice the routes in the example above were chained? This is a bit jQuery-inspired and perfect for organizing/grouping your routes.

```php
$app->get(array('/', '/news'), function() use ($news) {
    // do something
})->get('/news/:id/:title', function($id, $title) use ($news) {
    // do something with $id and $title
});
```

## papprika\Templates

While there are several template-systems available, none of their approaches makes really happy. Many of them use their own pseudo syntax which makes things more complicated instead of simplifying the whole templating-thing. Therefore papprika brings its own small template system, which is really easy to understand and use. This may be a bit dangerous regarding the separation of layout and logic and requires special care at this point, but it’ll be rewarded by a very fast implementation and highly variable applications. So always think twice where functions belong.

Because 3 lines of code are worth a thousand words, let’s have a look at how easy it is to use templates in your papprika application!

```php
echo new papprika\Template('./footer.php', array(
    'copyright' => 2011-'.date('Y')
));
```

Wow, that was quick and painless, huh? Let’s have a look at the `footer.php`-file…

```php
        echo '<footer>© '.$this->copyright.' papprika</footer>';
    echo '</body>';
echo '</html>';
```

That’s it.

### Customizing / beforeParse / afterParse

If you need/want, you can customize the class. Additionally there are two methods called `beforeParse` and `afterParse`, which are executed before and after the template is parsed and filled with your contents. They also need some customization. Want an example?

```php
class tpl extends papprika\Template {

    public function __construct($file, $data = array()) {
        parent::__construct('/var/www/templates/'.$file, $data);
    }

    function afterParse() {
        $this->_output = str_replace('HTML', '<abbr title="Hypertext Markup Language">HTML</abbr>', $this->_output);
    }

}
```

## papprika\File

papprika also provides some useful and handy functions for working with files in your application.

| Method               | Description                                             |
|:--------------------:|:--------------------------------------------------------|
| `$file->name()`      | Returns the name of the file (without extension)        |
| `$file->filename()`  | Returns the file’s entire name                          |
| `$file->extension()` | Returns the file extension                              |
| `$file->modified()`  | Returns the file’s last modify date (as unix timestamp) |
| `$file->size()`      | Returns the raw filesize (in bytes)                     |
| `$file->niceSize()`  | Returns the readable filesize (KB, MB, …)               |

```php
$latest = new papprika\File('./latest.zip');
echo '<a href="latest.zip">Download <em>'.$latest->name().'</em>.'.$latest->extension().' ('.$latest->niceSize().')</a>';
```

By the way: There are further informations provided to every file for images.

| Method            | Description                        |
|:-----------------:|:-----------------------------------|
| `$file->width()`  | Returns the width of the image     |
| `$file->height()` | Returns the height of the image    |
| `$file->mime()`   | Returns the mime-type of the image |

```php
$team = new papprika\File('./team.jpg');
echo '<img src="team.jpg" alt="" width="'.$team->width().'"> height="'.$team->height().'">';
```

Unfortunately papprika has no built-in image resizer yet, but it provides you some useful functions for recalculating your image’s sizes. Of course you won’t get smaller files, but you can embed your images into your application with different sizes.

```php
$logo = new papprika\File('./logo.jpg')->max(200);
echo '<img src="logo.jpg" alt="" width="'.$logo->width().'" height="'.$logo->height().'">';
```

```php
$logo = new papprika\File('./logo.jpg')->maxWidth(200);
echo '<img src="logo.jpg" alt="" width="'.$logo->width().'" height="'.$logo->height().'">';
```

```php
$logo = new papprika\File('./logo.jpg')->maxHeight(200);
echo '<img src="logo.jpg" alt="" width="'.$logo->width().'" height="'.$logo->height().'">';
```

## papprika\MySQL\Connection

papprika offers a simple opportunity to use MySQL databases in your application. At first, you connect to your database using the host, username and password and select the database.

```php
$db = new papprika\MySQL\Connection('localhost', 'root', '123', 'app');
```

### Charset

You can also set the default character set for the appropriate connection easily.

```php
$db->charset('utf8');
```

## papprika\MySQL\Queries

As we now have a connection established, we can start to execute some queries and fetch the result rows, which are returned as objects.

```php
$res = new papprika\MySQL\Query('SELECT * FROM news', $db);
echo '<ul>';
while($post = $res->fetch()) {
    echo '<li><a href="/news/'.$post->id.'">'.$post->title.'</a></li>';
}
echo '</ul>';
```

Besides the `fetch` method, there are three other methods which may also be interessting for your daily database business.

### Get rows

The `rows` method retrieves the number of rows from the appropriate result set. This command is only valid for statements like `SELECT` or `SHOW` that return an actual result set.

```php
$res = new papprika\MySQL\Query('SELECT * FROM news', $db);
echo $res->rows().' news found.';
```

### Affected rows

To retrieve the number of rows affected by a `INSERT`, `UPDATE`, `REPLACE` or `DELETE` query, the `affected` method is your friend.

```php
$res = new papprika\MySQL\Query('UPDATE news SET title = "abc"', $db);
echo $res->affected().' news were affected.';
```

### Inserted ID

Last but not least you may want to retrieve the ID generated for an `AUTO_INCREMENT` column by the appropriate query.

```php
$res = new papprika\MySQL\Query('INSERT INTO news (title) VALUES ("Hello World")', $db);
$newsId = $res->id();
```

### Escaping

As we all know, there are several evil people around who try to do some SQL injections to your app. papprika hates these guys just like you, therefore it offers a really easy syntax to build your queries while it prepares and escapes everything to prevent injections. Let’s have a look at an example.

```php
$a = $app->request('a');
$b = $app->request('b');
$query = 'SELECT * FROM news WHERE a = "%s" && b = "%s"';
$res = new papprika\MySQL\Query($query, $a, $b, $db);
```

`$a` and `$b` get automatically escaped and inserted for the `%s` placeholders. The usage is equivalent to [`sprintf`](http://php.net/manual/en/function.sprintf.php).

### Only MySQL? :(

As you may have noticed, we swaped the MySQL stuff into its own sub-namespace (`papprika\MySQL`). So, guess what! The implementation of other database extensions in the near future is already planned! :)

## Scaling papprika

While working on big projects with papprika, you may have 20-30 of long inline controllers and everything gets a bit confusing. One of the common complaints that I hear is that papprika forces you to put all of your code into a single file. But you aren’t forced to do so, instead I highly recommend to move your controllers into classes.

```php
namespace Thomas\TestApp\Controller;

class ChatController {

    public function messageAction($id) {
        …
    }

}
```

```php
$app->get('/chat/message/:id', 'Thomas\TestApp\Controller\ChatController::messageAction');
```

And if those class names are too long, you can easily write a fancy function to shorten them.

```php
function controller($name) {
    list($class, $method) = explode('/', $name, 2);
    return sprintf('Thomas\TestApp\Controller\%sController::%sAction', ucfirst($class), $method);
}

$app->get('/chat/message/:id', controller('chat/message'));
```

So, as you hopefully see, papprika is able to grow organically as your code base grows.

## papprika vs. frameworks

It’s difficult to draw the line between papprika and its big brothers, all these fancy frameworks. papprika is no framework, but it provides you all you need to build your own framework with. Use papprika if you’re comfortable with making all of your own architecture decisions and use a full stack framework if not. To put it in other words: it really doesn’t matter how large your application is and how many routes, controllers and services you have. You’ll always find a solution for that on a technical level. The challenge you’ll actually face is people.

## Full example

Concluding the following example shows the combination of several things mentioned above.

```php
$app = new papprika\Application();

$app->before(function() {
    echo new papprika\Template('./header.php', array(
        'title' => 'News @ '.$_SERVER['HTTP_HOST'],
        'time' => time()
    ));
})->after(function() {
    echo new papprika\Template('./footer.php', array(
        'copyright' => '2011-'.date('Y')
    ));
})->error(function() {
    echo new papprika\Template('./404.php');
});

$db = new papprika\MySQL\Connection('localhost', 'root', '123', 'app');
$db->charset('utf8');

$app->get('/news', function() use ($db) {
    $news = new papprika\MySQL\Query('SELECT * FROM news', $db);
    echo '<ul>';
    while($post = $news->fetch()) {
        echo '<li><a href="/news/'.$post->id.'">'.$post->title.'</a></li>';
    }
    echo '</ul>';
});

$app->get('/news/:id', function($id) use ($db, $templates) {
    $query = 'SELECT title, text, date FROM news WHERE id = "'.$id.'"';
    $res = new papprika\MySQL\Query($query, $db);
    if($res->rows() == 0) {
        echo 'FOUR OH FOUR';
    } else {
        $post = $res->fetch();
        echo new papprika\Template('./post.php', array(
            'title' => $post->title,
            'text' => nl2br($post->text),
            'date' => date('F jS Y', $post->date)
        ));
    }
})->assert('id', '\d+');

$app->get('/search', function() use ($db, $app) {
    $q = $app->request('q');
    $query = 'SELECT title, text, date FROM news WHERE title = "%s"';
    $res = new papprika\MySQL\Query($query, $q, $db);
    if($res->rows() == 0) {
        echo 'Nothing found…';
    } else {
        echo '<ul>';
        while($post = $res->fetch()) {
            echo '<li><a href="/news/'.$post->id.'">'.$post->title.'</a></li>';
        }
        echo '</ul>';
    }
});

$app->run();
```

## License

Copyright (c) 2012-2014 [Thomas Rasshofer](http://thomasrasshofer.com/)  
Licensed under the MIT license.

See LICENSE for more info.
