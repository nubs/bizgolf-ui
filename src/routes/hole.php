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

        $app->render('hole.html', ['hole' => $hole, 'user' => $app->config('codegolf.user')]);
    })->name('hole');

    $app->post('/holes/:holeId/submissions', $loadAuth, $auth, function($holeId) use ($app, $holeModel) {
        try {
            if (empty($_FILES['submission']) || $_FILES['submission']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error uploading submission.');
            }

            $holeModel['addSubmission']($holeId, $app->config('codegolf.user'), $_FILES['submission']['tmp_name']);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
        }

        $app->redirect($app->urlFor('hole', ['holeId' => $holeId]));
    });

    $app->get('/holes/:holeId/submissions/:submissionId', $loadAuth, $auth, function($holeId, $submissionId) use($app, $holeModel) {
        $hole = null;
        try {
            $hole = $holeModel['findOne']($holeId);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('home'));
        }

        $user = $app->config('codegolf.user');
        $submission = null;
        foreach ($hole['submissions'] as $holeSubmission) {
            if (
                (string)$holeSubmission['_id'] === $submissionId &&
                ((string)$holeSubmission['user']['_id'] === (string)$user['_id'] || !empty($user['isAdmin']))
            ) {
                $submission = $holeSubmission;
                break;
            }
        }

        if ($submission === null) {
            $app->flash('error', "Invalid submission {$submissionId}");
            $app->redirect($app->urlFor('hole', ['holeId' => $holeId]));
        }

        $submission['rawCode'] = utf8_decode($submission['code']);

        if ($submission['result']) {
            $submission['diff'] = '';
        } else {
            $diff = new \cogpowered\FineDiff\Diff();
            $submission['diff'] = $diff->render($submission['output'], $submission['sample']);
        }

        $app->render('submission.html', ['hole' => $hole, 'submission' => $submission, 'user' => $user]);
    })->name('submission');
};
