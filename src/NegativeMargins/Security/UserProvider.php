<?php
namespace NegativeMargins\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserProvider implements UserProviderInterface
{
    private $mongo;

    public function __construct(\MongoDB $mongo)
    {
        $this->mongo = $mongo;
    }

    public function loadUserByUsername($username)
    {
        $user = $this->mongo->user->findOne(array('username' => $username));

        if ($user === null) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        return $this->getUser($user);
    }

    public function loadUserByApiKey($apikey)
    {
        if ($user = $this->mongo->user->findOne(array('apikey' => $apikey))) {
            return $this->getUser($user);
        }
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'NegativeMargins\Security\User';
    }

    protected function getUser($user)
    {
        $object = new User($user['username'], $user['password'], (isset($user['roles']) ? $user['roles'] : array()));

        $object->setApikey($user['apikey']);

        return $object;
    }
}