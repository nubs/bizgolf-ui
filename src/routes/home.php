<?php
return function(\Slim\Slim $app, array $holeModel, array $userModel, callable $loadAuth) {
    $app->get('/', $loadAuth, function() use($app, $holeModel, $userModel) {
        $holes = $holeModel['find'](['visibleBy' => $app->config('codegolf.user')]);

        $submissions = [];
        foreach ($holes as $hole) {
            $submissions = array_merge($submissions, $hole['submissions']);
        }

        usort($submissions, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $submissions = array_slice($submissions, 0, 20);

        $app->render(
            'home.html',
            ['holes' => $holes, 'users' => $userModel['find']([], $holes), 'user' => $app->config('codegolf.user'), 'submissions' => $submissions]
        );
    })->name('home');
};
