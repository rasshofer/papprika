<?php

require './papprika.php';

$app = new papprika\Application();

$app->before(function() {
	echo '<!DOCTYPE html>';
	echo '<html>';
		echo '<head>';
			echo '<meta charset="utf-8">';
		echo '</head>';
		echo '<body>';
			echo '<header>Test-Application</header>';
})->after(function() {
			echo '<footer>&copy; papprika.org</footer>';
		echo '</body>';
	echo '</html>';
})->error(function() {
	echo 'The page you requested couldn\'t be found.';
})->get(array('/', '/home'), function() {
	echo 'Home! Sweet Home!';
});

$news = array(
	1 => array(
		'date' => '2010-01-01',
		'author' => 'thomas',
		'title' => 'Hello World!',
		'text' => 'Hello World!',
	),
	2 => array(
		'date' => '2011-01-01',
		'author' => 'thomas',
		'title' => 'papprika 0.1 released',
		'text' => 'Hello World!',
	),
	3 => array(
		'date' => '2012-01-01',
		'author' => 'thomas',
		'title' => 'papprika 0.2 released',
		'text' => 'Hello World!',
	)
);

$app->get('/news', function() use ($news) {
	echo '<ul>';
	foreach($news AS $id => $post) {
		echo '<li><a href="/news/'.$id.'">'.$post['title'].'</a></li>';
	}
	echo '</ul>';
})->get(array('/news/:id', '/blog/:id'), function($id) use ($news) {
	if(!array_key_exists($id, $news)) {
		die('Post #'.$id.' was not be found...');
	}
}, function($id) use ($news) {
	echo '<h1>'.$news[$id]['title'].'</h1>';
	echo '<h2>'.$news[$id]['date'].'</h2>';
	echo nl2br($news[$id]['title']);
})->assert('id', '\d+');

$app->get('/hello/:name', function($name) {
	echo 'Hello, '.$name;
})->assert('name', '([a-zA-Z]+)');

$app->post('/contact', function(papprika\Application $app) {
	$message = $app->request('message');
	mail('hello@papprika.org', 'Contact', $message);
});

$app->run();

?>