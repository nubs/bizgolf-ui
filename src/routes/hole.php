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
            $hole = $holeModel['findOne']($holeId, ['visibleBy' => $app->config('codegolf.user')]);
            $hole['submissions'] = array_slice($hole['submissions'], 0, 20);
            $hole['description'] = (new \dflydev\markdown\MarkdownParser())->transformMarkdown($hole['description']);
            $hole['sample'] = (new \FSHL\Highlighter(new \FSHL\Output\Html()))->setLexer(new \FSHL\Lexer\Php())->highlight($hole['sample']);
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

    $app->get('/holes/:holeId/submissions/:submissionId', $loadAuth, function($holeId, $submissionId) use($app, $holeModel, $auth) {
        $hole = null;
        try {
            $hole = $holeModel['findOne']($holeId, ['visibleBy' => $app->config('codegolf.user')]);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('home'));
        }

        if (!$hole['hasEnded']) {
            $auth();
        }

        $user = $app->config('codegolf.user');
        $submission = null;
        foreach ($hole['submissions'] as $holeSubmission) {
            if ((string)$holeSubmission['_id'] === $submissionId && $holeSubmission['viewableByUser']) {
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

        $submission['code'] = (new \FSHL\Highlighter(new \FSHL\Output\Html()))->setLexer(new \FSHL\Lexer\Php())->highlight($submission['code']);

        $submission['invertedCode'] = preg_replace_callback('/[^[:ascii:]]+/', function($matches) {
            return '<span class="inverted-text">' . htmlspecialchars(~$matches[0], ENT_QUOTES | ENT_SUBSTITUTE, 'ISO-8859-1') . '</span>';
        }, utf8_decode($submission['code']));
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
            $fields = [
                'title' => $req->post('title'),
                'shortDescription' => $req->post('shortDescription'),
                'description' => $req->post('description'),
                'sample' => $req->post('sample'),
                'disabledFunctions' => $req->post('disabledFunctions') ?: null,
                'startDate' => $req->post('startDate') ?: null,
                'endDate' => $req->post('endDate') ?: null,
            ];

            $fileName = $req->post('fileName');
            if ($fileName) {
                $fields['fileName'] = $fileName;
            } else {
                $fields['specification'] = $req->post('specification');
            }

            $hole = $holeModel['create']($fields);
        } catch (Exception $e) {
            $app->flash('error', $e->getMessage());
            $app->redirect($app->urlFor('newHole'));
        }

        $app->redirect($app->urlFor('hole', ['holeId' => (string)$hole['_id']]));
    });
};
