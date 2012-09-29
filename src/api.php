<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$api = $app['controllers_factory'];

$requireApiKey = function (Request $request) use ($app) {
    if ($user = $app['user_provider']->loadUserByApiKey($request->headers->get('ladder-api-key', $request->get('ladder-api-key')))) {
        $app['logged_in_user'] = $user;
    } else {
        return $app->json(array(
            'error' => true,
            'message' => sprintf('Invalid apikey'),
        ), 404);
    }
};

$api->get('/player/{name}', function (Application $app, $name) {
    if ($player = $app['player_manager']->findByUsername($name)) {
        unset($player['_id']);
        unset($player['email']);
        return $app->json($player);
    } else {
        return $app->json(array(
            'error' => true,
            'message' => sprintf('User %s not found', $name),
        ), 404);
    }
});

$api->post('/challenge/{challenged}', function (Application $app, $challenged) {
    $challenger = $app['player_manager']->findByUsername($app['logged_in_user']->getUsername());

    if ($challenged = $app['player_manager']->findByUsername($challenged)) {
        $challenge = $app['challenge_manager']->newChallenge($challenger, $challenged);
        $challenge['id'] = $challenge['_id'];
        unset($challenge['_id']);
        return $app->json($challenge);
    } else {
        return $app->json(array(
            'error' => true,
            'message' => sprintf('User %s not found', $challenged),
        ), 404);
    }
})->before($requireApiKey);

return $api;
