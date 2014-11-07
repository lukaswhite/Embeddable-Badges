<?php
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request,
		Symfony\Component\HttpFoundation\Response,
		WideImage\WideImage;

$app = new Silex\Application();

// Register the Twig service provider
$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__.'/../views',
));

/**
 * For simplicity, our datastore is a really simple, static array
 */
$app['datastore'] = function(){
	return [
		'users'	=>	[
			'dave'	=>	[				
				'avatar'			=>	'man1.png',
				'trophies'		=>	1,
				'rank'				=>	'Novice',
			],
			'jim'	=>	[
				'avatar'			=>	'man2.png',
				'trophies'		=>	2,
				'rank'				=>	'Intermediate',
			],
			'helen' => [
				'avatar'			=>	'woman1.png',
				'trophies'		=>	4,
				'rank'				=>	'Grand Master',
			],
		]
	];
};

// Turn on debugging
$app['debug'] = true;

/**
 * Dynamically-generated image
 */
$app->get('/image/{username}', function($username) use ($app) {

	// Check that the user in question exists
	if (!isset($app['datastore']['users'][$username])) {
		// No user with that username, throw a 404
		$app->abort(404, "User $username does not exist.");
	}

	// Get the user record
	$user = $app['datastore']['users'][$username];
	
	// Load the background
	$background = WideImage::load(__DIR__.'/../resources/images/background.png');

	// Load the avatar
	$avatar = WideImage::load(__DIR__.'/images/' . $user['avatar']);

	// Load the trophy image
	$trophy = WideImage::load(__DIR__.'/images/trophy.png');

	// Paste the avatar onto the background
	$im = $background->merge($avatar, 10, 20);

	// Get the canvas
	$canvas = $im->getCanvas();

	// Set the font for the username
	$canvas->useFont(__DIR__.'/../resources/fonts/VeraBd.ttf', 12, $im->allocateColor(0, 0, 0));

	// Write the username onto the canvas
	$canvas->writeText(70, 15, $username);

	// Choose a slightly smaller, non-bold font
	$canvas->useFont(__DIR__.'/../resources/fonts/Vera.ttf', 9, $im->allocateColor(0, 0, 0));

	// Write the rank
	$canvas->writeText(70, 35, $user['rank']);

	// Now add the appropriate number of trophies
	$x = 70;

	for ($i = 0; $i < $user['trophies']; $i++) {
		$im = $im->merge($trophy, $x, 55);
		$x += 20;
	}

	// Finally, output the image to the screen
	return $im->output('png');

});

/**
 * Dynamically-generated JavaScript
 */
$app->get('/js/{username}', function(Request $request, $username) use ($app) {

	// Check that the user in question exists
	if (!isset($app['datastore']['users'][$username])) {
		// No user with that username, throw a 404
		$app->abort(404, "User $username does not exist.");
	}

	// Get the user record
	$user = $app['datastore']['users'][$username];

	// Build the HTML
	$html = $app['twig']->render('badge.twig',
		[
			'username'		=>	$username,
			'imagepath'		=>	( ($request->server->get('HTTP_PORT') == 443) ? 'https' : 'http' ) . '://' . $request->server->get('HTTP_HOST') . '/images',
			'user'  			=> 	$user,
		]
	);
	
	// Minify the HTML, ensuring we wind up with one long string
	$minified = preg_replace(
    array(
			'/ {2,}/',
			'/<!--.*?-->|\t|(?:\r?\n[ \t]*)+/s'
    ),
    array(
			' ',
			''
		),
    $html
  );

	// Return a document.write with the minified, populated HTML as its argument
	return new Response(
		sprintf('document.write(\'%s\');', $minified),
		200,
		[ 'Content-Type', 'text/javascript' ]
	);

});

/**
 * Dynamically-generated HTML for embedding in an iFrame
 */
$app->get('/iframe/{username}', function(Request $request, $username) use ($app) {

	// Check that the user in question exists
	if (!isset($app['datastore']['users'][$username])) {
		// No user with that username, throw a 404
		$app->abort(404, "User $username does not exist.");
	}

	// Get the user record
	$user = $app['datastore']['users'][$username];

	return $app['twig']->render('badge.twig',
		[
			'username'		=>	$username,
			'imagepath'		=>	( ($request->server->get('HTTP_PORT') == 443) ? 'https' : 'http' ) . '://' . $request->server->get('HTTP_HOST') . '/images',
			'user'  			=> 	$user,
		]
	);

});

$app->get('/', function() use ($app) {

	return $app['twig']->render('home.twig');

});

$app->run();
