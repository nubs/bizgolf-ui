<?php
return function(\Slim\Slim $app, array $userModel) {
    return function() use($app, $userModel) {
        $credentials = json_decode($app->getEncryptedCookie('auth'), true);
        if (isset($credentials['username'], $credentials['password'])) {
            $user = null;
            try {
                $user = $userModel['findOne']($credentials);
            } catch (Exception $e) {
            }

            if ($user !== null) {
                $app->config('codegolf.username', $user['username']);
                $app->config('codegolf.isAdmin', array_key_exists('isAdmin', $user) && $user['isAdmin']);
            }
        }
    };
};
