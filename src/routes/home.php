<?php
return function(\Slim\Slim $app, array $holeModel, array $userModel, callable $loadAuth) {
    $app->get('/', $loadAuth, function() use($app, $holeModel, $userModel) {
        $app->render('home.html', ['holes' => $holeModel['find'](), 'users' => $userModel['find'](), 'user' => $app->config('codegolf.user')]);
    })->name('home');
};
