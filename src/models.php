<?php
return function($mongoUrl) {
    $dbName = str_replace('/', '', parse_url($mongoUrl)['path']);
    $db = (new Mongo($mongoUrl))->$dbName;

    $hole = require 'models/hole.php';
    $user = require 'models/user.php';

    return ['user' => $user($db), 'hole' => $hole($db)];
};
