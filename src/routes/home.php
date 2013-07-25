<?php
return function(\Slim\Slim $app, array $holeModel, array $userModel, callable $loadAuth) {
    $app->get('/', $loadAuth, function() use($app, $holeModel, $userModel) {
        $user = $app->config('codegolf.user');
        $holes = $holeModel['find'](['visibleBy' => $user]);

        $submissions = [];
        foreach ($holes as $hole) {
            $submissions = array_merge($submissions, $hole['submissions']);
        }

        usort($submissions, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $submissions = array_slice($submissions, 0, 20);

        $users = $userModel['find']([], $holes);
        $app->render('home.html', ['holes' => $holes, 'users' => $users, 'user' => $user, 'submissions' => $submissions]);
    })->name('home');
};
