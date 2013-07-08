<?php
return function(MongoDB $db, array $holeModel) {
    $collection = $db->users;

    $loadScoreboard = function($user) use($holeModel) {
        $user['scoreboard'] = [];
        $user['stats'] = ['score' => 0];
        foreach ($holeModel['find']() as $hole) {
            $userScoreboard = array_values(array_filter($hole['scoreboard'], function($submission) use($user) {
                return $submission['user']['_id'] == $user['_id'];
            }));

            if (empty($userScoreboard)) {
                $user['scoreboard'][] = ['submission' => ['length' => null, 'score' => 0], 'hole' => $hole];
            } else {
                $user['scoreboard'][] = ['submission' => $userScoreboard[0], 'hole' => $hole];
                $user['stats']['score'] += $userScoreboard[0]['score'];
            }
        }

        return $user;
    };

    return [
        'find' => function(array $conditions = []) use($collection, $loadScoreboard) {
            $users = iterator_to_array($collection->find($conditions));
            foreach ($users as &$user) {
                $user = $loadScoreboard($user);
            }

            usort($users, function($a, $b) {
                return $b['stats']['score'] - $a['stats']['score'];
            });

            return $users;
        },
        'auth' => function(array $conditions = []) use($collection) {
            return $collection->findOne($conditions);
        },
        'findOne' => function($id) use($collection, $loadScoreboard) {
            $user = null;
            try {
                $user = $collection->findOne(['_id' => new MongoId($id)]);
            } catch (Exception $e) {
            }

            if ($user === null) {
                throw new Exception("User '{$id}' does not exist.");
            }

            return $loadScoreboard($user);
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
