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

    public function finishGame($username, $newRank, $winner)
    {
        $this->playerCollection->update(array('username' => $username), array('$inc' => array('challenges' => 1, ($winner ? 'wins' : 'losses') => 1), '$set' => array('lastGameDate' => new \MongoDate(), 'rank' => $newRank)));
    }
}