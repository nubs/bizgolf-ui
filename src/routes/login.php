<?php
return function(\Slim\Slim $app, array $userModel) {
    $app->get('/login', function() use($app) {
        $app->render('login.html');
    })->name('login');

    $app->post('/login', function() use($app, $userModel) {
        $req = $app->request();
        $credentials = ['username' => $req->post('username'), 'password' => $req->post('password')];

        try {
            $userModel['findOne']($credentials);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('login'));
        }

        $app->setEncryptedCookie('auth', json_encode($credentials));
        $app->redirect($app->urlFor('home'));
    });
};
