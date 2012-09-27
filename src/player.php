<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$player = $app['controllers_factory'];

$player->get('/', function (Application $app) {
    return $app->redirect($app['url_generator']->generate('player_view', array('name' => $app['logged_in_user']->getUsername())));
})->bind('player_logged_in');

$player->get('/{name}', function (Application $app, $name) {
    $player = $app['player_manager']->findByUsername($name);

    return $app['twig']->render('player/view.html.twig', array(
        'player'      => $player,
        'actives'     => $app['challenge_manager']->findActive($player),
        'completes'   => $app['challenge_manager']->findComplete($player),
        'unverifieds' => $app['challenge_manager']->findUnverified($player),
    ));
})->bind('player_view');

$player->post('/{name}/challenge', function (Application $app, $name) {
    $challengeManager = $app['challenge_manager'];
    $notifier = $app['notifier'];
    $playerManager = $app['player_manager'];

    try {
        $user = $app['user_provider']->loadUserByUsername($name);
    } catch (UsernameNotFoundException $e) {
        $notifier->addError(sprintf("Can't find the user %s to challenge", $name));
        return $app->redirect('/user/new');
    }

    $challenger = $playerManager->findByUsername($app['logged_in_user']->getUsername());
    $challenged = $playerManager->findByUsername($name);

    if ($challenge = $challengeManager->findOneActive($challenged, $challenger)) {
        $notifier->addMessage('Finish this challenge before starting a new one');
    } else {
        $challenge = $challengeManager->newChallenge($challenger, $challenged);
    }

    return $app->redirect($app['url_generator']->generate('challenge_view', array('id' => $challenge['_id'])));
})->bind('player_challenge');

return $player;