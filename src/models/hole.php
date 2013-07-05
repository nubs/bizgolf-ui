<?php
return function(MongoDB $db) {
    $collection = $db->holes;

    $findOne = function($id) use($collection) {
        $hole = null;
        try {
            $hole = $collection->findOne(['_id' => new MongoID($id)]);
        } catch (Exception $e) {
        }

        if ($hole === null) {
            throw new Exception("Hole '{$id}' does not exist.");
        }

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
