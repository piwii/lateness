<?php

namespace Services;

use Monolog\Logger;
use Silex\Application;

class LatenessService
{
     const authorizedActionType = ['late', 'push-up', 'canning', 'breakfast'];

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
     * @param \PDO $pdo
     * @param Logger $monolog
     */
    public function __construct(\PDO $pdo, Logger $monolog)
    {
        $this->pdo = $pdo;
        $this->logger = $monolog;
    }

    private function executeStatement($statement, $params = array())
    {
        if (($result = $statement->execute($params)) == false) {
            $message = sprintf('Query error code [%s] on %s', $statement->errorCode(), $statement->queryString);
            $this->logger->addError($message);
            $message = sprintf('Query error detail : %s', implode('|', $statement->errorInfo()));
            $this->logger->addError($message);
            throw new \Exception($message);
        }

        return $result;
    }

    private function getNextId($tableName)
    {
        $query = 'SELECT MAX(id) + 1 AS next_id FROM ' . $tableName;
        $statement = $this->pdo->prepare($query);
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

        $this->executeStatement($statement, $params);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row == null) {
            $message = sprintf('User name %s is not found in database', $userSlackName);
            $this->logger->addError($message);
            throw new \Exception($message);
        }

        return $row['id'];
    }

    private function isAutorizedActionType($actionType)
    {
        if (in_array($actionType, self::authorizedActionType)) {
            return true;
        }

        return false;
    }

    public function show(array $commandArgs)
    {
        $params = ['action_type' => 'late'];
        if (isset($commandArgs[1])) {
            if (!$this->isAutorizedActionType($commandArgs[1])) {
                return 'Authorized actions type are ' . implode(',', self::authorizedActionType);
            }
            $params['action_type'] = $commandArgs[1];
        }

        $query = 'SELECT l.day, l.nb_minutes, u.name
                  FROM actions l JOIN users u ON u.id = l.user_id
                  WHERE action_type = :action_type
                  ORDER BY l.day DESC';
        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);

        $list = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = sprintf('* %s : %s => %d minutes', $row['name'], $row['day'], $row['nb_minutes']);
        }

        $text = sprintf("*%s list* :\n %s", ucfirst($params['action_type']), implode("\n", $list));

        return $text;
    }

    public function count(array $commandArgs)
    {
        $params = ['action_type' => 'late'];
        if (isset($commandArgs[1])) {
            if (!$this->isAutorizedActionType($commandArgs[1])) {
                return 'Authorized actions tyre are ' . implode(',', self::authorizedActionType);
            }
            $params['action_type'] = $commandArgs[1];
        }

        $query = 'SELECT SUM(l.nb_minutes), u.name
                  FROM actions l JOIN users u ON u.id = l.user_id
                  WHERE action_type = :action_type
                  GROUP BY u.name
                  ORDER BY sum DESC';
        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);

        $list = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = sprintf('* %s : %d minutes', $row['name'], $row['sum']);
        }

        $text = sprintf("*%s counter* :\n %s", ucfirst($params['action_type']), implode("\n", $list));

        return $text;
    }

    public function add(array $commandArgs)
    {
        if (count($commandArgs) < 4 || !is_numeric($commandArgs[3])) {
            $message = "Not enough param for add command, right syntax is 'add [type] [slack_name] [number]' \n";
            $message .= sprintf('You provide : %s', implode(' ', $commandArgs));
            $message .= "*Exemple*:\n _add push-up @pierre-yves 10_\n_add late @pierre-yves 10_\n";
            $this->logger->addInfo($message);
            return $message;
        }

        $actionType = $commandArgs[1];
        $userSlackName = $commandArgs[2];
        $number = $commandArgs[3];
        $id = $this->getNextId('actions');
        $userId = $this->getUserId($userSlackName);
        $currentDate = date('Y/m/d');

        $query = "INSERT INTO actions VALUES (:id, :date, :nb_minute, :user_id, :action_type)";
        $statement = $this->pdo->prepare($query);

        $params = [
            ':id' => $id,
            ':date' => $currentDate,
            ':nb_minute' => $number,
            ':user_id' => $userId,
            ':action_type' => $actionType,
        ];

        $this->executeStatement($statement, $params);

        $text = "*Add $actionType* :\n";
        $text .= sprintf('%d %s has been added to %s', $number, $actionType, $userSlackName);

        return $text;
    }

    public function help()
    {
        $text = "*List of available command* :\n";
        $text .= "* *show* : lateness list\n";
        $text .= "* *count* : lateness counter\n";
        $text .= "* *add* _[action_type] [slack_name] [number]_: add action to someone\n";

        return $text;
    }
}
