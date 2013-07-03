<?php
return function($mongoUrl) {
    $dbName = str_replace('/', '', parse_url($mongoUrl)['path']);
    $db = (new Mongo($mongoUrl))->$dbName;

    $user = require 'models/user.php';
    $hole = require 'models/hole.php';

    return ['user' => $user($db), 'hole' => $hole($db)];
};
