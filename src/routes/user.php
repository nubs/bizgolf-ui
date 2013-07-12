<?php
return function(\Slim\Slim $app, array $userModel, callable $loadAuth) {
    $app->get('/users/:userId', $loadAuth, function($userId) use($app, $userModel) {
        $user = null;
        try {
            $user = $userModel['findOne']($userId);
            if (empty($app->config('codegolf.user')['isAdmin'])) {
                $user['submissions'] = array_filter($user['submissions'], function($submission) {
                    return $submission['hole']['hasStarted'];
                });

                $user['scoreboard'] = array_filter($user['scoreboard'], function($submission) {
                    return $submission['hole']['hasStarted'];
                });
            }
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('home'));
        }

        $app->render('user.html', ['user' => $app->config('codegolf.user'), 'viewUser' => $user]);
    })->name('user');

    $app->get('/login', function() use($app) {
        $app->render('login.html');
    })->name('login');

    $app->post('/login', function() use($app, $userModel) {
        $req = $app->request();
        $credentials = ['username' => $req->post('username'), 'password' => $req->post('password')];

        if (!$userModel['auth']($credentials)) {
            $app->flash('error', 'Failed to authenticate');
            $app->redirect($app->urlFor('login'));
        }

        $app->setEncryptedCookie('auth', json_encode($credentials));
        $app->redirect($app->urlFor('home'));
    });

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

        if ($userModel['auth'](['username' => $username])) {
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
