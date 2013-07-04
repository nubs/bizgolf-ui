<?php
return function(\Slim\Slim $app, array $holeModel, callable $loadAuth) {
    $app->get('/', $loadAuth, function() use($app, $holeModel) {
        $app->render('home.html', ['holes' => $holeModel['find'](), 'username' => $app->config('codegolf.username')]);
    })->name('home');
};
