<?php
return function(MongoDB $db) {
    $collection = $db->holes;

    $partitionByUser = function(array $submissions) {
        $users = [];
        foreach ($submissions as $submission) {
            $username = $submission['username'];
            if (array_key_exists($username, $users)) {
                $users[$username][] = $submission;
            } else {
                $users[$username] = [$submission];
            }
        }

        return $users;
    };

    $bestForEachUser = function(array $submissions) use($partitionByUser) {
        $users = $partitionByUser($submissions);
        $result = [];

        foreach ($users as $username => $userSubmissions) {
            $passingSubmissions = array_filter($userSubmissions, function($submission) {
                return $submission['result'];
            });

            if (!empty($passingSubmissions)) {
                $result[$username] = array_reduce($passingSubmissions, functioN($min, $submission) {
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
            $hole = $collection->findOne(['_id' => new MongoID($id)]);
        } catch (Exception $e) {
        }

        if ($hole === null) {
            throw new Exception("Hole '{$id}' does not exist.");
        }

        $hole['scoreboard'] = $addScores($bestForEachUser($hole['submissions']));

        return $hole;
    };

    return [
        'find' => function(array $conditions = array()) use($collection) {
            return iterator_to_array($collection->find($conditions));
        },
        'findOne' => $findOne,
        'addSubmission' => function($id, $username, $submission)  use($collection, $findOne) {
            $hole = $findOne($id);
            $result = \Codegolf\judge(\Codegolf\loadHole($hole['fileName']), \Codegolf\createImage('php-5.5', $submission));
            $result['_id'] = new MongoID();
            $result['username'] = $username;

            $code = file_get_contents($submission);
            $result['code'] = utf8_encode($code);
            $result['length'] = strlen($code);

            $collection->update(['_id' => new MongoID($id)], ['$push' => ['submissions' => $result]]);
        },
    ];
};
