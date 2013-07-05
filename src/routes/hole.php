<?php
return function(\Slim\Slim $app, array $holeModel, callable $loadAuth, callable $auth) {
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

    $app->post('/holes/:holeId/submissions', $loadAuth, $auth, function($holeId) use ($app, $holeModel) {
        try {
            if (empty($_FILES['submission']) || $_FILES['submission']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error uploading submission.');
            }

            $holeModel['addSubmission']($holeId, $app->config('codegolf.username'), $_FILES['submission']['tmp_name']);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
        }

        $app->redirect($app->urlFor('hole', ['holeId' => $holeId]));
    });
};
