<?php
return function(\Slim\Slim $app, array $holeModel, callable $loadAuth) {
    $app->get('/holes/:holeId', $loadAuth, function($holeId) use($app, $holeModel) {
        $hole = null;
        try {
            $hole = $holeModel['findOne']($holeId);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('home'));
        }

        $app->render('hole.html', ['hole' => $hole, 'username' => $app->config('codegolf.username')]);
    })->name('hole');
};
