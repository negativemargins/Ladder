<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;

$user = $app['controllers_factory'];

$user->get('/new', function(Application $app, Request $request) {
    return $app['twig']->render('security/new.html.twig', array(
    ));
})->bind('security_new');

$user->post('/register', function(Application $app, Request $request) {
    $userForm = $request->request->get('user');

    try {
        $user = $app['user_provider']->loadUserByUsername($userForm['username']);
        $app['notifier']->addError(sprintf('The username %s is already in use, plesae choose something different', $userForm['username']));
        return $app->redirect($app['url_generator']->generate('security_new'));
    } catch (UsernameNotFoundException $e) {
        // if not found, we can create it
    }

    if ($userForm['password'] != $userForm['password2']) {
        $app['notifier']->addError("Your passwords don't match.");
        return $app->redirect('/user/new');
    }

    $user = new User($userForm['username'], $userForm['password']);
    $encoder = $app['security.encoder_factory']->getEncoder($user);
    $userForm['password'] = $encoder->encodePassword($userForm['password'], $user->getSalt());

    unset($userForm['password2']);
    $userForm['createdAt'] = new \MongoDate();
    $userForm['updatedAt'] = new \MongoDate();
    $userForm['roles'] = array('ROLE_USER');

    $app['mongo']->user->insert($userForm);

    $app['mongo']->player->insert(array(
        'username'   => $userForm['username'],
        'rank'       => 1500,
        'challenges' => 0
    ));

    $app['notifier']->addMessage('Account created');

    return $app->redirect('/');
})->bind('security_register');

return $user;
