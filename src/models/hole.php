<?php
return function(MongoDB $db) {
    $collection = $db->holes;

    return [
        'find' => function(array $conditions = array()) use($collection) {
            return iterator_to_array($collection->find($conditions));
        },
        'findOne' => function($id) use($collection) {
            $hole = null;
            try {
                $hole = $collection->findOne(['_id' => new MongoID($id)]);
            } catch (Exception $e) {
            }

            if ($hole === null) {
                throw new Exception("Hole '{$id}' does not exist.");
            }

            return $hole;
        },
    ];
};
