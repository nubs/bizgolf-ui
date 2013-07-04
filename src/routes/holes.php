<?php
return function(\Slim\Slim $app, array $holeModel) {
    $app->get('/holes/:holeId', function($holeId) use($app, $holeModel) {
        $hole = null;
        try {
            $hole = $holeModel['findOne']($holeId);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('home'));
        }

        $app->render('hole.html', ['hole' => $hole]);
    })->name('hole');
};
