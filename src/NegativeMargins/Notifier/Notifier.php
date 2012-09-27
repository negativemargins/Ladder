<?php

namespace NegativeMargins\Notifier;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Notifier
{
    const ERROR_PATH   = 'notifier/error';
    const MESSAGE_PATH = 'notifier/message';

    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function addError($error)
    {
        $errors = $this->get(self::ERROR_PATH);

        $errors[] = $error;

        $this->session->set(self::ERROR_PATH, $errors);
    }

    public function getErrors()
    {
        $errors = $this->get(self::ERROR_PATH);
        $this->session->remove(self::ERROR_PATH);

        return $errors;
    }

    public function hasErrors()
    {
        $errors = $this->get(self::ERROR_PATH);

        return !empty($errors);
    }

    public function addMessage($message)
    {
        $messages = $this->get(self::MESSAGE_PATH);

        $messages[] = $message;

        $this->session->set(self::MESSAGE_PATH, $messages);
    }

    public function getMessages()
    {
        $messages = $this->get(self::MESSAGE_PATH);
        $this->session->remove(self::MESSAGE_PATH);

        return $messages;
    }

    public function hasMessages()
    {
        $messages = $this->get(self::MESSAGE_PATH);

        return !empty($messages);
    }

    private function get($name)
    {
        return $this->session->get($name, array());
    }
}