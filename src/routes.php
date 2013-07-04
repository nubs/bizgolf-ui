<?php
return function(\Slim\Slim $app, array $models, array $middleware) {
    $loginRoute = require 'routes/login.php';
    $loginRoute($app, $models['user']);

    $registerRoute = require 'routes/register.php';
    $registerRoute($app, $models['user']);

    $homeRoute = require 'routes/home.php';
    $homeRoute($app, $models['hole'], $middleware['loadAuth']);

    $holeRoute = require 'routes/hole.php';
    $holeRoute($app, $models['hole'], $middleware['loadAuth']);
};
