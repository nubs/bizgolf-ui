<?php
return function(\Slim\Slim $app, array $holeModel, callable $loadAuth, callable $auth) {
    $app->get('/holes/new', $loadAuth, $auth, function() use($app) {
        if (empty($app->config('codegolf.user')['isAdmin'])) {
            $app->flash('error', 'Access restricted.');
            $app->redirect($app->urlFor('home'));
        }

        $app->render('new-hole.html', ['user' => $app->config('codegolf.user')]);
    })->name('newHole');

    $app->get('/holes/:holeId', $loadAuth, function($holeId) use($app, $holeModel) {
        $hole = null;
        try {
            $hole = $holeModel['findOne']($holeId);
            if (empty($app->config('codegolf.user')['isAdmin']) && !$hole['hasStarted']) {
                throw new Exception('Hole has not started.');
            }
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('home'));
        }

        $hole['submissions'] = array_slice($hole['submissions'], 0, 20);
        $app->render('hole.html', ['hole' => $hole, 'user' => $app->config('codegolf.user')]);
    })->name('hole');

    $app->post('/holes/:holeId/submissions', $loadAuth, $auth, function($holeId) use ($app, $holeModel) {
        try {
            if (empty($_FILES['submission']) || $_FILES['submission']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error uploading submission.');
            }

            $hole = $holeModel['findOne']($holeId);
            if (empty($app->config('codegolf.user')['isAdmin']) && !$hole['isOpen']) {
                throw new Exception('Hole is not active.');
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

        if ($submission['result']) {
            $submission['diff'] = '';
        } else {
            $diff = new \cogpowered\FineDiff\Diff();
            $submission['diff'] = $diff->render($submission['output'], $submission['sample']);
        }

        $app->render('submission.html', ['hole' => $hole, 'submission' => $submission, 'user' => $user]);
    })->name('submission');

    $app->post('/holes/:holeId/submissions/revalidate', $loadAuth, $auth, function($holeId) use ($app, $holeModel) {
        try {
            if (empty($app->config('codegolf.user')['isAdmin'])) {
                throw new Exception('Access restricted.');
            }

            $app->render('revalidate.html', ['changedSubmissions' => $holeModel['revalidateSubmissions']($holeId)]);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('hole', ['holeId' => $holeId]));
        }
    });

    $app->post('/holes', $loadAuth, $auth, function() use($app, $holeModel) {
        $hole = null;
        try {
            if (empty($app->config('codegolf.user')['isAdmin'])) {
                throw new Exception('Access restricted.');
            }

            $req = $app->request();
            $fileName = $req->post('fileName');
            $fields = [
                'title' => $req->post('title'),
                'shortDescription' => $req->post('shortDescription'),
                'description' => $req->post('description'),
                'sample' => $req->post('sample'),
                'disabledFunctions' => $req->post('disabledFunctions') ?: null,
                'startDate' => $req->post('startDate') ?: null,
                'endDate' => $req->post('endDate') ?: null,
            ];

            if ($fileName) {
                $fields['fileName'] = $fileName;
            } else {
                $specification = [
                    'constantName' => $req->post('constantName') ?: null,
                    'disabledFunctionality' => explode(',', $req->post('disabledFunctionality') ?: ''),
                    'constantValues' => $req->post('constantValues'),
                    'sample' => $req->post('specification-sample'),
                    'trim' => $req->post('trim'),
                ];

                if ($req->post('constantValuesIsAFunction')) {
                    $specification['constantValues'] = ['type' => 'function', 'body' => $specification['constantValues']];
                } elseif(empty($specification['constantValues'])) {
                    $specification['constantValues'] = ['type' => 'array', 'values' => []];
                } else {
                    $specification['constantValues'] = ['type' => 'array', 'values' => explode(',', $specification['constantValues'])];
                }

                if ($req->post('sampleIsAFunction')) {
                    $specification['sample'] = [
                        'type' => 'function',
                        'arguments' => $req->post('sampleArguments'),
                        'body' => $specification['sample'],
                    ];
                } else {
                    $specification['sample'] = ['type' => 'string', 'value' => $specification['sample']];
                }

                $fields['specification'] = $specification;
            }

            $hole = $holeModel['create']($fields);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('newHole'));
        }

        $app->redirect($app->urlFor('hole', ['holeId' => (string)$hole['_id']]));
    });
};
