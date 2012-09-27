<?php

namespace NegativeMargins\Ladder;

class PlayerManager
{
    private $playerCollection;

    public function __construct(\MongoCollection $playerCollection)
    {
        $this->playerCollection = $playerCollection;
    }

    public function findByUsername($username)
    {
        return $this->playerCollection->findOne(array('username' => $username));
    }
}