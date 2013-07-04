<?php
return function(\Slim\Slim $app) {
    return function() use($app) {
        if ($app->config('codegolf.username') === null) {
            $app->redirect($app->urlFor('login'));
        }
    };
};
