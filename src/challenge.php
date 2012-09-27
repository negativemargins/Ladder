<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$challenge = $app['controllers_factory'];

$challenge->get('/list', function (Application $app) {
    return $app['twig']->render('challenge/list.html.twig', array(
        'challenge' => $app['mongo']->challenges->find(array())->sort(array('challengeDate' => -1)),
    ));
})->bind('challenge_list');

$challenge->get('/{id}', function (Application $app, $id) {
    return $app['twig']->render('challenge/view.html.twig', array(
        'challenge' => $app['challenge_manager']->find($id)
    ));
})->bind('challenge_view');

$challenge->post('/{id}/report', function (Application $app, $id, Request $request) {
	$challengeManager = $app['challenge_manager'];
	$notifier = $app['notifier'];
    $challenge = $challengeManager->find($id);
    $username = $app['logged_in_user']->getUsername();

    if ($challenge['challenger']['username'] != $username && $challenge['challenged']['username'] != $username) {
        $notifier->addError(sprintf('Only %s or %s can report a score', $challenge['challenger']['username'], $challenge['challenged']['username']));
    } else {
        $score = $request->request->get('score');

        if ($challenge['challenger']['username'] == $username) {
            $challengeManager->reportChallenge($challenge, $score['mine'], $score['theirs'], $username);
        } else {
            $challengeManager->reportChallenge($challenge, $score['theirs'], $score['mine'], $username);
        }

        $notifier->addMessage('Score reported');
    }

    return $app->redirect($app['url_generator']->generate('challenge_view', array('id' => $challenge['_id'])));
})->bind('challenge_report');

$challenge->post('/{id}/verify', function (Application $app, $id) {
	$challengeManager = $app['challenge_manager'];
	$notifier = $app['notifier'];
	$challenge = $challengeManager->find($id);
	$username = $app['logged_in_user']->getUsername();

    if ($challenge['challenger']['username'] != $username && $challenge['challenged']['username'] != $username) {
        $notifier->addError(sprintf('Only %s or %s can verify a score', $challenge['challenger']['username'], $challenge['challenged']['username']));
    } else {
        $challengeManager->verifyChallenge($challenge, $username);
        $notifier->addMessage('Score verified');
    }

    return $app->redirect($app['url_generator']->generate('challenge_view', array('id' => $challenge['_id'])));
})->bind('challenge_verify');

return $challenge;
