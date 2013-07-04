<?php
return function(MongoDB $db) {
    $collection = $db->users;

    return [
        'exists' => function(array $conditions = array()) use($collection) {
            return $collection->findOne($conditions) !== null;
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
