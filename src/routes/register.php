<?php
return function(\Slim\Slim $app, array $userModel) {
    $app->get('/register', function() use($app) {
        $app->render('register.html');
    })->name('register');

    $app->post('/register', function() use($app, $userModel) {
        $req = $app->request();
        $username = $req->post('username');
        $password = $req->post('password');

        if (empty($username) || empty($password)) {
            $app->flash('error', "You must enter a username and a password.");
            $app->redirect($app->urlFor('register'));
        }

        if ($userModel['exists'](['username' =>$username])) {
            $app->flash('error', "Username {$username} taken.");
            $app->redirect($app->urlFor('register'));
        }

        try {
            $userModel['create'](['username' => $username, 'password' => $password]);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage);
            $app->redirect($app->urlFor('register'));
        }

        $app->setEncryptedCookie('auth', json_encode(['username' => $username, 'password' => $password]));
        $app->redirect($app->urlFor('home'));
    });
};
