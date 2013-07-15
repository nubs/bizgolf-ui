<?php
$appDir = dirname(__DIR__);
require_once "{$appDir}/vendor/autoload.php";

$mongoUrl = getenv('MONGOHQ_URL') ?: die('Missing MONGOHQ_URL environment variable');
$cookieSecretKey = getenv('COOKIE_SECRET_KEY') ?: die('Missing COOKIE_SECRET_KEY environment variable');

$app = new \Slim\Slim([
    'cookies.lifetime' => '1 month',
    'cookies.secret_key' => $cookieSecretKey,
    'view' => new \Slim\Views\Twig(),
    'templates.path' => "{$appDir}/src/templates",
]);
$app->add(new \Slim\Middleware\SessionCookie(['secret' => $cookieSecretKey, 'name' => 'session']));

$models = require "{$appDir}/src/models.php";
$models = $models($mongoUrl);

$middleware = require "{$appDir}/src/middleware.php";
$middleware = $middleware($app, $models);

$routes = require "{$appDir}/src/routes.php";
$routes($app, $models, $middleware);

$app->run();
