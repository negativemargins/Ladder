<?php

namespace NegativeMargins\Ladder;

class ChallengeManager
{
    private $challengeCollection;
    private $playerCollection;

    public function __construct(\MongoCollection $challengeCollection, \MongoCollection $playerCollection)
    {
        $this->challengeCollection = $challengeCollection;
        $this->playerCollection = $playerCollection;
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

        return $challenge;
    }

    public function reportChallenge($challenge, $challengerScore, $challengedScore, $reporter)
    {
        $winner = $challengerScore > $challengedScore ? 'challenger' : 'challenged';
        $loser = $challengerScore < $challengedScore ? 'challenger' : 'challenged';

        $set = array(
            'challenger.score' => $challengerScore,
            'challenged.score' => $challengedScore,
            'winner' => $challenge[$winner]['username'],
            'loser' => $challenge[$loser]['username'],
            'reporter' => $reporter,
            'reportDate' => new \MongoDate()
        );

        $this->challengeCollection->update(array('_id' => $challenge['_id']), array('$set' => $set));
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
            $set['challenger.afterRank'] = $newWinnerRank;
            $set['challenged.beforeRank'] = $loser['rank'];
            $set['challenged.afterRank'] = $newLoserRank;
        } else {
            $set['challenged.beforeRank'] = $winner['rank'];
            $set['challenged.afterRank'] = $newWinnerRank;
            $set['challenger.beforeRank'] = $loser['rank'];
            $set['challenger.afterRank'] = $newLoserRank;
        }

        $this->challengeCollection->update(array('_id' => $challenge['_id']), array('$set' => $set));

        // update users
        $this->playerCollection->update(array('username' => $winner['username']), array('$inc' => array('challenges' => 1), '$set' => array('lastGameDate' => new \MongoDate(), 'rank' => $winnerRank)));
        $this->playerCollection->update(array('username' => $loser['username']), array('$inc' => array('challenges' => 1), '$set' => array('lastGameDate' => new \MongoDate(), 'rank' => $loserRank)));
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