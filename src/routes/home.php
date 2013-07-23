<?php
return function(\Slim\Slim $app, array $holeModel, array $userModel, callable $loadAuth) {
    $app->get('/', $loadAuth, function() use($app, $holeModel, $userModel) {
        $holes = $holeModel['find']();
        if (empty($app->config('codegolf.user')['isAdmin'])) {
            $holes = array_filter($holes, function($hole) {
                return $hole['hasStarted'];
            });
        }

        $submissions = [];
        foreach ($holes as $hole) {
            foreach ($hole['submissions'] as $submission) {
                $submission['hole'] = $hole;
                $submissions[] = $submission;
            }
        }

        usort($submissions, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $submissions = array_slice($submissions, 0, 20);

        $app->render(
            'home.html',
            ['holes' => $holes, 'users' => $userModel['find'](), 'user' => $app->config('codegolf.user'), 'submissions' => $submissions]
        );
    })->name('home');
};
