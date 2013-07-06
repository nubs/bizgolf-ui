<?php
return function(MongoDB $db, array $holeModel) {
    $collection = $db->users;

    return [
        'auth' => function(array $conditions = []) use($collection) {
            return $collection->findOne($conditions);
        },
        'findOne' => function($id) use($collection, $holeModel) {
            $user = null;
            try {
                $user = $collection->findOne(['_id' => new MongoId($id)]);
            } catch (Exception $e) {
            }

            if ($user === null) {
                throw new Exception("User '{$id}' does not exist.");
            }

            $user['submissions'] = [];
            $user['stats'] = ['score' => 0];
            foreach ($holeModel['find']() as $hole) {
                $userSubmission = array_filter($hole['scoreboard'], function($submission) use($user) {
                    return $submission['user']['_id'] == $user['_id'];
                });

                if (empty($userSubmission)) {
                    $user['submissions'][] = ['submission' => ['length' => null, 'score' => 0], 'hole' => $hole];
                } else {
                    $user['submissions'][] = ['submission' => $userSubmission[0], 'hole' => $hole];
                    $user['stats']['score'] += $userSubmission[0]['score'];
                }
            }

            return $user;
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
