<?php
return function(MongoDB $db) {
    $collection = $db->holes;

    $partitionByUser = function(array $submissions) {
        $users = [];
        foreach ($submissions as $submission) {
            $userId = (string)$submission['user']['_id'];
            if (array_key_exists($userId, $users)) {
                $users[$userId][] = $submission;
            } else {
                $users[$userId] = [$submission];
            }
        }

        return $users;
    };

    $shortestSubmission = function(array $submissions) {
        return array_reduce($submissions, function($min, $submission) {
            if ($submission['result'] && ($min === null || $submission['length'] < $min['length'])) {
                return $submission;
            }

            return $min;
        });
    };

    $bestForEachUser = function(array $submissions) use($partitionByUser, $shortestSubmission) {
        $users = $partitionByUser($submissions);
        $result = [];

        foreach ($users as $userId => $userSubmissions) {
            $shortest = $shortestSubmission($userSubmissions);
            if ($shortest !== null) {
                $result[$userId] = $shortest;
            }
        }

        usort($result, function($a, $b) {
            return $a['length'] - $b['length'] ?: $a['_id']->getTimestamp() - $b['_id']->getTimestamp();
        });

        return $result;
    };

    $fleshOutHole = function($hole) use ($collection, $bestForEachUser, $shortestSubmission) {
        if (!array_key_exists('submissions', $hole)) {
            $hole['submissions'] = [];
        }

        $shortest = $shortestSubmission($hole['submissions']);
        foreach ($hole['submissions'] as &$submission) {
            if ($submission['result']) {
                $submission['score'] = (int)((float)$shortest['length'] * 1000.0 / (float)$submission['length']);
            } else {
                $submission['score'] = 0;
            }
        }

        usort($hole['submissions'], function($a, $b) {
            return $b['_id']->getTimestamp() - $a['_id']->getTimestamp();
        });

        $hole['scoreboard'] = $bestForEachUser($hole['submissions']);

        $hole['hasStarted'] = empty($hole['startDate']) || $hole['startDate'] <= time();
        $hole['hasEnded'] = !empty($hole['endDate']) && $hole['endDate'] < time();
        $hole['isOpen'] = $hole['hasStarted'] && !$hole['hasEnded'];

        return $hole;
    };

    $findOne = function($id) use($collection, $fleshOutHole) {
        $hole = null;
        try {
            $hole = $collection->findOne(['_id' => new MongoId($id)]);
        } catch (Exception $e) {
        }

        if ($hole === null) {
            throw new Exception("Hole '{$id}' does not exist.");
        }

        return $fleshOutHole($hole);
    };

    return [
        'find' => function(array $conditions = []) use($collection, $fleshOutHole) {
            $holes = iterator_to_array($collection->find($conditions));
            foreach ($holes as &$hole) {
                $hole = $fleshOutHole($hole);
            }

            return $holes;
        },
        'findOne' => $findOne,
        'addSubmission' => function($id, array $user, $submission)  use($collection, $findOne) {
            $hole = $findOne($id);
            $result = \Bizgolf\judge(\Bizgolf\loadHole($hole['fileName']), \Bizgolf\createImage('php-5.5', $submission));
            $result['_id'] = new MongoId();
            $result['user'] = $user;

            $code = file_get_contents($submission);
            $result['code'] = utf8_encode($code);
            $result['length'] = strlen($code);

            $collection->update(['_id' => new MongoId($id)], ['$push' => ['submissions' => $result]]);
        },
    ];
};
