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

    $bestForEachUser = function(array $submissions) use($partitionByUser) {
        $users = $partitionByUser($submissions);
        $result = [];

        foreach ($users as $userId => $userSubmissions) {
            $passingSubmissions = array_filter($userSubmissions, function($submission) {
                return $submission['result'];
            });

            if (!empty($passingSubmissions)) {
                $result[$userId] = array_reduce($passingSubmissions, function($min, $submission) {
                    if ($min === null || $submission['length'] < $min['length']) {
                        return $submission;
                    }

                    return $min;
                });

            }
        }

        usort($result, function($a, $b) {
            if ($a['length'] === $b['length']) {
                return 0;
            }

            return $a['length'] < $b['length'] ? -1 : 1;
        });

        return $result;
    };

    $addScores = function(array $sortedSubmissions) {
        if (empty($sortedSubmissions)) {
            return $sortedSubmissions;
        }

        $bestLength = (float)$sortedSubmissions[0]['length'];

        foreach ($sortedSubmissions as &$submission) {
            $submission['score'] = (int)($bestLength * 1000.0 / (float)$submission['length']);
        }

        return $sortedSubmissions;
    };

    $findOne = function($id) use($collection, $bestForEachUser, $addScores) {
        $hole = null;
        try {
            $hole = $collection->findOne(['_id' => new MongoId($id)]);
        } catch (Exception $e) {
        }

        if ($hole === null) {
            throw new Exception("Hole '{$id}' does not exist.");
        }

        if (!array_key_exists('submissions', $hole)) {
            $hole['submissions'] = [];
        }

        $hole['scoreboard'] = $addScores($bestForEachUser($hole['submissions']));

        return $hole;
    };

    return [
        'find' => function(array $conditions = []) use($collection, $bestForEachUser, $addScores) {
            $holes = iterator_to_array($collection->find($conditions));
            foreach ($holes as &$hole) {
                if (!array_key_exists('submissions', $hole)) {
                    $hole['submissions'] = [];
                }

                $hole['scoreboard'] = $addScores($bestForEachUser($hole['submissions']));
            }

            return $holes;
        },
        'findOne' => $findOne,
        'addSubmission' => function($id, array $user, $submission)  use($collection, $findOne) {
            $hole = $findOne($id);
            $result = \Codegolf\judge(\Codegolf\loadHole($hole['fileName']), \Codegolf\createImage('php-5.5', $submission));
            $result['_id'] = new MongoId();
            $result['user'] = $user;

            $code = file_get_contents($submission);
            $result['code'] = utf8_encode($code);
            $result['length'] = strlen($code);

            $collection->update(['_id' => new MongoId($id)], ['$push' => ['submissions' => $result]]);
        },
    ];
};
