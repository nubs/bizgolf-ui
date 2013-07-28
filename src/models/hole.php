<?php
return function(MongoDB $db) {
    $collection = $db->holes;

    $shortestSubmission = function(array $submissions) {
        return array_reduce($submissions, function($min, $submission) {
            if ($submission['result'] && ($min === null || $submission['length'] < $min['length'])) {
                return $submission;
            }

            return $min;
        });
    };

    $bestForEachUser = function(array $submissions) use($shortestSubmission) {
        $users = [];
        foreach ($submissions as $submission) {
            $userId = (string)$submission['user']['_id'];
            if (array_key_exists($userId, $users)) {
                $users[$userId][] = $submission;
            } else {
                $users[$userId] = [$submission];
            }
        }

        $result = [];
        foreach ($users as $userId => $userSubmissions) {
            $shortest = $shortestSubmission($userSubmissions);
            if ($shortest !== null) {
                $result[$userId] = $shortest;
            }
        }

        usort($result, function($a, $b) {
            return $a['length'] - $b['length'] ?: $a['timestamp'] - $b['timestamp'];
        });

        return $result;
    };

    $fleshOutHole = function($hole, array $conditions = []) use ($collection, $bestForEachUser, $shortestSubmission) {
        $hole['hasStarted'] = empty($hole['startDate']) || $hole['startDate'] <= time();
        $hole['hasEnded'] = !empty($hole['endDate']) && $hole['endDate'] < time();
        $hole['isOpen'] = $hole['hasStarted'] && !$hole['hasEnded'];

        if (!array_key_exists('submissions', $hole)) {
            $hole['submissions'] = [];
        }

        $shortest = $shortestSubmission($hole['submissions']);
        foreach ($hole['submissions'] as &$submission) {
            $submission['hole'] = $hole;
            $submission['rawCode'] = utf8_decode($submission['code']);
            $submission['timestamp'] = $submission['_id']->getTimestamp();
            $submission['timestampFormatted'] = \Carbon\Carbon::createFromTimeStamp($submission['timestamp'])->diffForHumans();

            if ($submission['result']) {
                $submission['score'] = (int)((float)$shortest['length'] * 1000.0 / (float)$submission['length']);
            } else {
                $submission['score'] = 0;
            }

            $submission['viewableByUser'] = array_key_exists('visibleBy', $conditions) &&
                (
                    !empty($conditions['visibleBy']['isAdmin']) ||
                    $hole['hasEnded'] ||
                    (!empty($conditions['visibleBy']['_id']) && $conditions['visibleBy']['_id'] == $submission['user']['_id'])
                );
        }

        usort($hole['submissions'], function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $hole['scoreboard'] = $bestForEachUser($hole['submissions']);

        $trims = ['trim' => 'Full Trim', 'ltrim' => 'Left Trim', 'rtrim' => 'Right Trim'];
        if (empty($hole['fileName'])) {
            if ($hole['specification']['constantValues']['type'] === 'array') {
                $hole['specification']['constantValues'] = $hole['specification']['constantValues']['values'];
            } else {
                $hole['specification']['constantValues'] = create_function('', $hole['specification']['constantValues']['body']);
            }

            if ($hole['specification']['sample']['type'] === 'string') {
                $hole['specification']['sample'] = $hole['specification']['sample']['value'];
            } else {
                $hole['specification']['sample'] = create_function(
                    $hole['specification']['sample']['arguments'],
                    $hole['specification']['sample']['body']
                );
            }
        } else {
            $hole['specification'] = \Bizgolf\loadHole($hole['fileName']);
        }

        $trim = $hole['specification']['trim'];
        $hole['trim'] = isset($trims[$trim]) ? $trims[$trim] : $trim;

        $hole['startDateFormatted'] = empty($hole['startDate']) ? null : date(DATE_RFC2822, $hole['startDate']);
        $hole['endDateFormatted'] = empty($hole['endDate']) ? null : date(DATE_RFC2822, $hole['endDate']);
        $hole['description'] = (new \dflydev\markdown\MarkdownParser())->transformMarkdown($hole['description']);
        $hole['sample'] = (new \FSHL\Highlighter(new \FSHL\Output\Html()))->setLexer(new \FSHL\Lexer\Php())->highlight($hole['sample']);

        return $hole;
    };

    $findOne = function($id, array $conditions = []) use($collection, $fleshOutHole) {
        $hole = null;
        try {
            $hole = $collection->findOne(['_id' => new MongoId($id)]);
        } catch (Exception $e) {
        }

        if ($hole === null) {
            throw new Exception("Hole '{$id}' does not exist.");
        }

        $hole = $fleshOutHole($hole, $conditions);

        if (array_key_exists('visibleBy', $conditions) && empty($conditions['visibleBy']['isAdmin']) && !$hole['hasStarted']) {
            throw new Exception('Hole has not started.');
        }

        if (array_key_exists('canSubmit', $conditions) && empty($conditions['canSubmit']['isAdmin']) && !$hole['isOpen']) {
            throw new Exception('Hole is not active.');
        }

        return $hole;
    };

    return [
        'find' => function(array $conditions = []) use($collection, $fleshOutHole) {
            $visibleBy = null;
            if (array_key_exists('visibleBy', $conditions)) {
                $visibleBy = $conditions['visibleBy'];
                unset($conditions['visibleBy']);
            }

            $holes = [];
            foreach (iterator_to_array($collection->find($conditions)) as $hole) {
                $hole = $fleshOutHole($hole, ['visibleBy' => $visibleBy]);
                if (!empty($visibleBy['isAdmin']) || $hole['hasStarted']) {
                    $holes[] = $hole;
                }
            }

            return $holes;
        },
        'findOne' => $findOne,
        'addSubmission' => function($id, array $user, $submission)  use($collection, $findOne) {
            $hole = $findOne($id, ['canSubmit' => $user]);
            $result = \Bizgolf\judge($hole['specification'], 'php-5.5', $submission);
            $result['_id'] = new MongoId();
            $result['user'] = $user;

            $code = file_get_contents($submission);
            $result['code'] = utf8_encode($code);
            $result['output'] = utf8_encode($result['output']);
            $result['stderr'] = utf8_encode($result['stderr']);
            $result['length'] = strlen($code);

            $collection->update(['_id' => new MongoId($id)], ['$push' => ['submissions' => $result]]);
        },
        'revalidateSubmissions' => function($id) use ($collection, $findOne) {
            $hole = $findOne($id);
            $changedSubmissions = [];
            foreach ($hole['submissions'] as $submission) {
                $submissionFile = tempnam(sys_get_temp_dir(), 'revalidate');
                file_put_contents($submissionFile, $submission['rawCode']);
                $result = \Bizgolf\judge($hole['specification'], 'php-5.5', $submissionFile);
                if ($result['result'] !== $submission['result']) {
                    $submission['result'] = $result['result'];
                    $collection->update(
                        ['_id' => new MongoId($id), 'submissions._id' => $submission['_id']],
                        ['$set' => ['submissions.$.result' => $submission['result']]]
                    );
                    $changedSubmissions[] = $submission;
                }
            }

            return $changedSubmissions;
        },
        'create' => function(array $fields) use($collection) {
            $collection->insert($fields);

            return $fields;
        },
    ];
};
