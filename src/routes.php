<?php
return function(\Slim\Slim $app, array $models, array $middleware) {
    $userRoute = require 'routes/user.php';
    $userRoute($app, $models['user'], $models['hole'], $middleware['loadAuth']);

    $homeRoute = require 'routes/home.php';
    $homeRoute($app, $models['hole'], $models['user'], $middleware['loadAuth']);

    $holeRoute = require 'routes/hole.php';
    $holeRoute($app, $models['hole'], $middleware['loadAuth'], $middleware['auth']);
};
