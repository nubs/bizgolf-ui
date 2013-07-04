<?php
return function(\Slim\Slim $app, array $models) {
    $loadAuth = require 'middleware/loadAuth.php';
    $auth = require 'middleware/auth.php';
    return ['loadAuth' => $loadAuth($app, $models['user']), 'auth' => $auth($app)];
};
