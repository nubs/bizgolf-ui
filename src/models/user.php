<?php
return function(MongoDB $db) {
    $collection = $db->users;

    return [
        'findOne' => function(array $conditions = array()) use($collection) {
            $user = null;
            try {
                $user = $collection->findOne($conditions);
            } catch (Exception $e) {
            }

            if ($user === null) {
                throw new Exception("User '{$id}' does not exist.");
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
