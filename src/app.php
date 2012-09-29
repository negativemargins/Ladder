<?php

use NegativeMargins\Ladder\ChallengeManager;
use NegativeMargins\Ladder\PlayerManager;
use NegativeMargins\Ladder\RankManager;
use NegativeMargins\Notifier\Notifier;
use NegativeMargins\Security\UserProvider;
use Silex\Application;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;

require_once __DIR__.'/../vendor/autoload.php';

$app = new Application();
$app['debug'] = true;

$app['mongo'] = $app->share(function () {
    $mongo = new Mongo();
    return $mongo->ladder;
});
$app['challenge_manager'] = $app->share(function () use ($app) {
    return new ChallengeManager($app['mongo']->challenge, $app['player_manager'], $app['sailthru'], $app['user_provider']);
});
$app['player_manager'] = $app->share(function () use ($app) {
    return new PlayerManager($app['mongo']->player);
});

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
    'twig.options' => array(
        'strict_variables' => false
    )
));

$app['notifier'] = $app->share(function () use ($app) {
    return new Notifier($app['session']);
});

$app['sailthru'] = $app->share(function () use ($app) {
    $api_key = '';
    $secret = '';

    return new \Sailthru_Client($api_key, $secret);
});

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addExtension(new NegativeMargins\Twig\MongoDateExtension());
    return $twig;
}));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\SecurityServiceProvider());

$app['user_provider'] = $app->share(function () use ($app) {
    return new UserProvider($app['mongo']);
});
$app['security.firewalls'] = array(
    'login' => array(
        'pattern' => '^/login$',
        'anonymous' => true
    ),
    'secured' => array(
        'pattern' => '^.*$',
        'anonymous' => true,
        'form'   => array('login_path' => '/login', 'check_path' => '/login_check'),
        'logout' => array('logout_path' => '/user/logout'),
        'users'  => $app['user_provider'],
    ),
);
$app['security.access_rules'] = array(
    array('^/player/[^/]*/challenge/', 'ROLE_USER'),
);

$app['logged_in_user'] = $app->share(function(Application $app) {
    $token = $app['security']->getToken();
    if (null !== $token) {
        $user = $token->getUser();

        if ($user instanceof Symfony\Component\Security\Core\User\User) {
            return $user;
        }
    }
});

// Actions
$app->get('/login', function(Application $app, Symfony\Component\HttpFoundation\Request $request) {
    $error = $app['security.last_error']($request);

    if ($error !== null) {
        $app['notifier']->addError($error);
    }

    return $app['twig']->render('security/login.html.twig', array(
        'error'         => $error,
        'last_username' => $app['session']->get('_security.last_username'),
    ));
})->bind('user_login');

$app->get('/', function (Application $app) {
    return $app['twig']->render('index.html.twig', array(
        'activeChallenges' => $app['challenge_manager']->findActive(),
        'completeChallenges' => $app['challenge_manager']->findComplete(),
    ));
})->bind('home');

// ladder
$app->get('/ladder', function (Application $app) {
    $players = $app['mongo']->player->find()->sort(array('rank' => -1));

    return $app['twig']->render('ladder/list.html.twig', array(
            'players' => $players,
    ));
})->bind('ladder');

foreach(array('player', 'challenge', 'user', 'api') as $mount) {
    $app->mount("/$mount", require __DIR__."/$mount.php");
}

return $app;