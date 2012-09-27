<?php

namespace NegativeMargins\Ladder;

use NegativeMargins\Security\UserProvider;

class ChallengeManager
{
    private $challengeCollection;
    private $playerManager;
    private $sailthru;
    private $userProvider;

    public function __construct(\MongoCollection $challengeCollection, PlayerManager $playerManager, \Sailthru_Client $sailthru, UserProvider $userProvider)
    {
        $this->challengeCollection = $challengeCollection;
        $this->playerManager = $playerManager;
        $this->sailthru = $sailthru;
        $this->userProvider = $userProvider;
    }

    public function findOneActive($player1, $player2)
    {
        return $this->challengeCollection->findOne(array(
            'winner' => array('$exists' => false),
            '$or' => array(
                array(
                    'challenger.username' => $player1['username'],
                    'challenged.username' => $player2['username'],
                ),
                array(
                    'challenged.username' => $player1['username'],
                    'challenger.username' => $player2['username'],
                )
            ),
        ));
    }

    public function find($id)
    {
        return $this->challengeCollection->findOne(array('_id' => new \MongoId($id)));;
    }

    public function newChallenge($challenger, $challenged)
    {
        $challenge = array(
            'challenger' => array(
                'username'   => $challenger['username']
            ),
            'challenged' => array(
                'username'   => $challenged['username']
            ),
            'challengeDate' => new \MongoDate()
        );
        $this->challengeCollection->insert($challenge);

        $this->sailthru->send(
            'challenged',
            $challenged['email'],
            array(
                'challenged' => $challenged,
                'challenger' => $challenger,
                'challenge'  => $challenge
            )
        );

        return $challenge;
    }

    public function reportChallenge($challenge, $challengerScore, $challengedScore, $reporter)
    {
        $winner = $challengerScore > $challengedScore ? 'challenger' : 'challenged';
        $loser = $challengerScore < $challengedScore ? 'challenger' : 'challenged';


        $notReporter = ($reporter != $challenge['challenged']['username']) ? $challenge['challenged']['username'] : $challenge['challenger']['username'];
        $set = array(
            'challenger.score' => $challengerScore,
            'challenged.score' => $challengedScore,
            'winner' => $challenge[$winner]['username'],
            'loser' => $challenge[$loser]['username'],
            'reporter' => $reporter,
            'verifier' => $notReporter,
            'reportDate' => new \MongoDate()
        );

        $this->challengeCollection->update(array('_id' => $challenge['_id']), array('$set' => $set));

        $notReporter = $this->playerManager->findByUsername($notReporter);
        $this->sailthru->send(
            'reported',
            $notReporter['email'],
            array(
                'challenge' => $this->find($challenge['_id'])
            )
        );
    }

    public function verifyChallenge($challenge, $verifier)
    {
        $winner = $this->playerManager->findByUsername($challenge['winner']);
        $loser  = $this->playerManager->findByUsername($challenge['loser']);

        $newRanks = $this->calculateNewRanks($winner['rank'], $loser['rank']);

        list($winnerRank, $loserRank) = $newRanks;

        $set = array(
            'verifier' => $verifier,
            'verifyDate' => new \MongoDate()
        );

        if ($challenge['winner'] == $challenge['challenger']['username']) {
            $set['challenger.beforeRank'] = $winner['rank'];
            $set['challenger.afterRank'] = $winnerRank;
            $set['challenged.beforeRank'] = $loser['rank'];
            $set['challenged.afterRank'] = $loserRank;
        } else {
            $set['challenged.beforeRank'] = $winner['rank'];
            $set['challenged.afterRank'] = $winnerRank;
            $set['challenger.beforeRank'] = $loser['rank'];
            $set['challenger.afterRank'] = $loserRank;
        }

        $this->challengeCollection->update(array('_id' => $challenge['_id']), array('$set' => $set));

        // update users
        $this->playerManager->finishGame($winner['username'], $winnerRank);
        $this->playerManager->finishGame($loser['username'], $loserRank);

        $this->sailthru->send(
            'verified',
            ($verifier == $winner['username']) ? $loser['email'] : $winner['email'],
            array(
                'challenge' => $this->find($challenge['_id'])
            )
        );
    }

    public function calculateNewRanks($winnerRank, $loserRank)
    {
        $winnerChance = 1 / (1 + pow(10, ($loserRank - $winnerRank) / 400));
        $delta = round(32 * (1 - $winnerChance));

        $newWinnerRank = $winnerRank + $delta;
        $newLoserRank = $loserRank - $delta;

        return array($newWinnerRank, $newLoserRank);
    }

    public function findActive($player, $limit = 10)
    {
        $cursor = $this->challengeCollection->find(array(
            'winner' => array('$exists' => false),
            '$or' => array(
                array(
                    'challenger.username' => $player['username'],
                ),
                array(
                    'challenged.username' => $player['username'],
                )
            ),
        ))
        ->sort(array('challengeDate' => -1))
        ->limit($limit);

        return iterator_to_array($cursor);
    }

    public function findUnverified($player, $limit = 10)
    {
        $cursor = $this->challengeCollection->find(array(
            'winner' => array('$exists' => true),
            'verifier' => array('$exists' => false),
            '$or' => array(
                array(
                    'challenger.username' => $player['username'],
                ),
                array(
                    'challenged.username' => $player['username'],
                )
            ),
        ))
        ->sort(array('reportDate' => -1))
        ->limit($limit);

        return iterator_to_array($cursor);
    }

    public function findComplete($player, $limit = 10)
    {
        $cursor = $this->challengeCollection->find(array(
            'winner' => array('$exists' => true),
            'verifier' => array('$exists' => true),
            '$or' => array(
                array(
                    'challenger.username' => $player['username'],
                ),
                array(
                    'challenged.username' => $player['username'],
                )
            ),
        ))
        ->sort(array('verifyDate' => -1))
        ->limit($limit);

        return iterator_to_array($cursor);
    }
}