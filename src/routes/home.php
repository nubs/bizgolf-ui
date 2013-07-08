<?php
return function(\Slim\Slim $app, array $holeModel, array $userModel, callable $loadAuth) {
    $app->get('/', $loadAuth, function() use($app, $holeModel, $userModel) {
        $holes = $holeModel['find']();
        $submissions = [];
        foreach ($holes as $hole) {
            foreach ($hole['submissions'] as $submission) {
                $submission['hole'] = $hole;
                $submissions[] = $submission;
            }
        }

        usort($submissions, function($a, $b) {
            return $b['_id']->getTimestamp() - $a['_id']->getTimestamp();
        });

        $submissions = array_slice($submissions, 0, 20);

        $app->render(
            'home.html',
            ['holes' => $holes, 'users' => $userModel['find'](), 'user' => $app->config('codegolf.user'), 'submissions' => $submissions]
        );
    })->name('home');
};
