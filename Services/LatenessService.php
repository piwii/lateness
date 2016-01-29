<?php

namespace Services;

use Monolog\Logger;
use Silex\Application;

class LatenessService
{
    const TABLE_NAME_USERS = 'users';
    const TABLE_NAME_LATENESS = 'lateness';

    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * LatenessService constructor.
     * @param $pdo
     * @param $monolog
     */
    public function __construct($pdo, $monolog)
    {
        $this->pdo = $pdo;
        $this->logger = $monolog;
    }

    private function executeStatement($statement, $params = array())
    {
        if ($statement->execute($params) == false) {
            $message = sprintf('Query error code [%s] on %s', $statement->errorCode(), $statement->queryString);
            $this->logger->addError($message);
            $message = sprintf('Query error detail : %s', implode('|', $statement->errorInfo()));
            $this->logger->addError($message);
            throw new \Exception($message);
        }
    }

    private function getNextId($tableName)
    {
        $query = 'SELECT MAX(id) + 1 AS next_id FROM ' . $tableName;
        $statement = $this->pdo->prepare($query);
        $this->logger->addInfo(sprintf('Execute query : %s', $query));
        $this->executeStatement($statement);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $nextId = $row == null ? 1 : $row['next_id'];

        $this->logger->addInfo(sprintf('Next id of table %s is %d', $tableName, $nextId));

        return $nextId;
    }

    private function getUserId($userSlackName)
    {
        $query = 'SELECT id FROM users WHERE slack_name = :slack_name';
        $statement = $this->pdo->prepare($query);
        $params = [':slack_name' => $userSlackName];

        $this->logger->addInfo(sprintf('Execute query : %s with param slack_name : %s', $query, $userSlackName));
        $this->executeStatement($statement, $params);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row == null) {
            $message = sprintf('User name %s is not fond in database', $userSlackName);
            $this->logger->addError($message);
            throw new \Exception($message);
        }

        return $row['id'];
    }

    public function show()
    {
        $query = 'SELECT l.day, l.nb_minutes, u.name FROM lateness l JOIN users u ON u.id = l.user_id ORDER BY l.day DESC';
        $statement = $this->pdo->prepare($query);
        $this->logger->addInfo(sprintf('Execute query : %s', $query));
        $this->executeStatement($statement);

        $list = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = sprintf('* %s : %s => %d minutes', $row['name'], $row['day'], $row['nb_minutes']);
        }

        $text = "*Listes des retards* :\n" . implode("\n", $list);

        return $text;
    }

    public function count()
    {
        $query = 'SELECT SUM(l.nb_minutes), u.name FROM lateness l JOIN users u ON u.id = l.user_id GROUP BY u.name ORDER BY sum DESC';
        $statement = $this->pdo->prepare($query);
        $this->logger->addInfo(sprintf('Execute query : %s', $query));
        $this->executeStatement($statement);

        $list = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = sprintf('* %s : %d minutes', $row['name'], $row['sum']);
        }

        $text = "*Compteur des retards* :\n" . implode("\n", $list);

        return $text;
    }

    public function iAmHere($name)
    {
    }

    public function add(array $commandArgs)
    {
        if (count($commandArgs) < 3 || !is_numeric($commandArgs[2])) {
            $this->logger->addInfo(sprintf('Not enough param for add command : %s', implode(',', $commandArgs)));
            return $this->help();
        }

        $userSlackName = $commandArgs[1];
        $nbMinutes = $commandArgs[2];
        $id = $this->getNextId(self::TABLE_NAME_LATENESS);
        $userId = $this->getUserId($userSlackName);
        $currentDate = date('Y/m/d');

        $query = "INSERT INTO lateness VALUES (:id, :date, :nb_minute, :user_id)";
        $statement = $this->pdo->prepare($query);

        $params = [
            ':id' => $id,
            ':date' => $currentDate,
            ':nb_minute' => $nbMinutes,
            ':user_id' => $userId,
        ];

        $this->logger->addInfo(
            sprintf(
                'Execute query : %s with params id : %d | data : %s | nb_minute : %d | user_id %d',
                $query, $id, $currentDate, $nbMinutes, $userId
            )
        );
        $this->executeStatement($statement, $params);

        $text = "*Ajout d'un retard* :\n";
        $text .= sprintf('%d minutes ont été ajoutées à %s', $nbMinutes, $userSlackName);

        return $text;
    }

    public function help()
    {
        $text = "*List des commandes disponible* :\n";
        $text .= "* *show* : liste des retards\n";
        $text .= "* *count* : compteur des retart\n";
        $text .= "* *add* _{nom} {nombre de minutes}_: ajouter un retard\n";

        return $text;
    }
}
