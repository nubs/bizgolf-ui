<?php
return function(\Slim\Slim $app, array $userModel) {
    return function() use($app, $userModel) {
        $credentials = json_decode($app->getEncryptedCookie('auth'), true);
        if (isset($credentials['username'], $credentials['password'])) {
            $user = $userModel['auth']($credentials);
            if ($user !== null) {
                $app->config('codegolf.user', $user);
            }
        }
    };
};
