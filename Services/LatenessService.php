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

    public function __construct()
    {
    }

    private function executeStatement(\PDOStatement $statement)
    {
        if ($statement->execute() == false) {
            $message = implode('|', $statement->errorCode());
            $this->logger->addError($message);
            throw new \Exception($message);
        }
    }

    private function getNextId($tableName)
    {
        $statement = $this->pdo->prepare('SELECT MAX(id) + 1 AS next_id FROM :table');
        $statement->bindParam(':table', $tableName, \PDO::PARAM_STR);
        $this->executeStatement($statement);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $nextId = $row == null ? 1 : $row['next_id'];

        return $nextId;
    }

    private function getUserId($userName)
    {
        $statement = $this->pdo->prepare('SELECT id FROM users WHERE name = :name');
        $statement->bindParam(':name', $userName, \PDO::PARAM_STR);
        $this->executeStatement($statement);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row == null) {
            $message = sprintf('User name %s is not fond in database', $userName);
            $this->logger->addError($message);
            throw new \Exception($message);
        }

        return $row['id'];
    }

    public function show()
    {
        $statement = $this->pdo->prepare('SELECT l.day, l.nb_minutes, u.name FROM lateness l JOIN users u ON u.id = l.user_id');
        $this->executeStatement($statement);

        $list = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = sprintf('* %s : %s => %d minutes', $row['name'], $row['day'], $row['nb_minutes']);
        }

        $text = implode("\n", $list);

        return $text;
    }

    public function count()
    {
        $statement = $this->pdo->prepare('SELECT SUM(l.nb_minutes), u.name FROM lateness l JOIN users u ON u.id = l.user_id GROUP BY u.name');
        $this->executeStatement($statement);

        $list = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = sprintf('* %s : %d minutes', $row['name'], $row['nb_minutes']);
        }

        $text = implode("\n", $list);

        return $text;
    }

    public function iAmHere($name)
    {
    }

    public function add(array $commandArgs)
    {
        if (count($commandArgs) < 3 || !is_int($commandArgs[2])) {
            return $this->help();
        }

        $userName = $commandArgs[1];
        $nbMinutes = $commandArgs[2];

        $id = $this->getNextId(self::TABLE_NAME_LATENESS);
        $userId = $this->getUserId($userName);
        $currentDate = date('Y/m/d');

        $statement = $this->pdo->prepare("INSERT INTO lateness VALUES (:id, :date, :nb_minute, :user_id)");
        $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        $statement->bindParam(':date', $currentDate, \PDO::PARAM_STR);
        $statement->bindParam(':nb_minute', $nbMinutes, \PDO::PARAM_INT);
        $statement->bindParam(':user_id', $userId, \PDO::PARAM_INT);
        $this->executeStatement($statement);
    }

    public function help()
    {
        $text="List des commandes disponible
            * show : liste des retards
            * count : compteur des retart
            * add {nom} {nombre de minutes}: ajouter un retard
        ";

        return $text;
    }
}
