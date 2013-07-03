<?php
return function(MongoDB $db) {
    $collection = $db->holes;

    return [
        'find' => function(array $conditions = array()) use($collection) {
            return iterator_to_array($collection->find($conditions));
        },
    ];
};
