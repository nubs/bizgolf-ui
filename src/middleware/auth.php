<?php
return function(\Slim\Slim $app) {
    return function() use($app) {
        if ($app->config('codegolf.user') === null) {
            $app->redirect($app->urlFor('login'));
        }
    };
};
