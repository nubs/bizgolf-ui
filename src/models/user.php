<?php
return function(MongoDB $db) {
    $collection = $db->users;

    $loadScoreboard = function($user, $holes) {
        $user['submissions'] = [];
        $user['scoreboard'] = [];
        $user['stats'] = ['score' => 0];
        foreach ($holes as $hole) {
            $isThisUsersSubmission = function($submission) use($user) {
                return $submission['user']['_id'] == $user['_id'];
            };

            $user['submissions'] = array_merge($user['submissions'], array_filter($hole['submissions'], $isThisUsersSubmission));

            $userScoreboard = array_values(array_filter($hole['scoreboard'], $isThisUsersSubmission));
            $userScoreboard = empty($userScoreboard) ? ['length' => null, 'score' => 0, 'hole' => $hole] : $userScoreboard[0];
            $user['scoreboard'][] = $userScoreboard;
            $user['stats']['score'] += $userScoreboard['score'];
        }

        usort($user['submissions'], function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $user;
    };

    return [
        'find' => function(array $conditions = [], array $holes) use($collection, $loadScoreboard) {
            $loadScoreboardForUser = function($user) use($holes, $loadScoreboard) {
                return $loadScoreboard($user, $holes);
            };

            $users = array_map($loadScoreboardForUser, iterator_to_array($collection->find($conditions)));
            usort($users, function($a, $b) {
                return $b['stats']['score'] - $a['stats']['score'];
            });

            return $users;
        },
        'auth' => function(array $conditions = []) use($collection) {
            return $collection->findOne($conditions);
        },
        'findOne' => function($id, array $holes) use($collection, $loadScoreboard) {
            $user = null;
            try {
                $user = $collection->findOne(['_id' => new MongoId($id)]);
            } catch (Exception $e) {
            }

            if ($user === null) {
                throw new Exception("User '{$id}' does not exist.");
            }

            return $loadScoreboard($user, $holes);
        },
        'create' => function(array $fields) use($collection) {
            try {
                $collection->insert($fields);
            } catch (Exception $e) {
                throw new Exception('Failed to create user.');
            }
        },
    ];
};
