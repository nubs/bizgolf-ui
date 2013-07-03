<?php
return function(\Slim\Slim $app, array $holeModel) {
    $app->get('/', function() use($app, $holeModel) {
        $app->render('home.html', ['holes' => $holeModel['find']()]);
    })->name('home');
};
