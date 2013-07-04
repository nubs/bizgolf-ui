<?php
return function(\Slim\Slim $app, array $models, array $middleware) {
    $loginRoute = require 'routes/login.php';
    $loginRoute($app, $models['user']);

    $homeRoute = require 'routes/home.php';
    $homeRoute($app, $models['hole'], $middleware['loadAuth']);

    $holesRoute = require 'routes/holes.php';
    $holesRoute($app, $models['hole']);
};
