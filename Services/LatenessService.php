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

    private function isAuthorizedActionType($actionType)
    {
        if (in_array($actionType, self::authorizedActionType)) {
            return true;
        }

        return false;
    }

    public function show(array $commandArgs)
    {
        $params = [':sprint_number' => getenv('SPRINT_NUMBER'), 'action_type' => 'late'];
        if (isset($commandArgs[1])) {
            if (!$this->isAuthorizedActionType($commandArgs[1])) {
                $this->help(['help', 'counter']);
            }
            $params['action_type'] = $commandArgs[1];
        }

        $query = 'SELECT l.day, l.nb_minutes, u.name
                  FROM actions l JOIN users u ON u.id = l.user_id
                  WHERE action_type = :action_type AND sprint_number = :sprint_number
                  ORDER BY l.day DESC';
        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);

        $list = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = sprintf('* %s : %s => %d', $row['name'], $row['day'], $row['nb_minutes']);
        }

        $text = sprintf("*%s list* :\n %s", ucfirst($params['action_type']), implode("\n", $list));

        return $text;
    }

    public function counter(array $commandArgs)
    {
        $params = [':sprint_number' => getenv('SPRINT_NUMBER'), 'action_type' => 'late'];
        if (isset($commandArgs[1])) {
            if (!$this->isAuthorizedActionType($commandArgs[1])) {
                // todo: manage error with catchable exception
                $this->help(['help', 'counter']);
            }
            $params['action_type'] = $commandArgs[1];
        }

        $query = 'SELECT SUM(l.nb_minutes), u.name
                  FROM actions l JOIN users u ON u.id = l.user_id
                  WHERE action_type = :action_type AND sprint_number = :sprint_number
                  GROUP BY u.name
                  ORDER BY sum DESC';
        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);

        $list = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = sprintf('* %s : %d', $row['name'], $row['sum']);
        }

        $text = sprintf("*%s counter* :\n %s", ucfirst($params['action_type']), implode("\n", $list));

        return $text;
    }

    public function sum()
    {
        $params = [':sprint_number' => getenv('SPRINT_NUMBER')];
        $query = 'SELECT SUM(l.nb_minutes), u.name, l.action_type
                  FROM actions l JOIN users u ON u.id = l.user_id
                  WHERE sprint_number = :sprint_number
                  GROUP BY l.action_type, u.name
                  ORDER BY sum DESC';
        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);

        $summary = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($summary[$row['name']])) {
                $summary[$row['name']] = array();
            }
            $summary[$row['name']][$row['action_type']] = $row['sum'];
        }

        // todo: get values from configuration
        $weightLate = 10;
        $weightPushUp = 1;
        $weightCanning = 1;
        $weightBreakfast = 10;

        $list = [];
        foreach ($summary as $userName => $userSummary) {
            // Initialize user action type value to 0 if it does not exist
            foreach (self::authorizedActionType as $actionType) {
                $userSummary[$actionType] = isset($userSummary[$actionType]) ? $userSummary[$actionType] : 0;
            }
            $point = $userSummary['late'] * $weightLate
                - $userSummary['push-up'] * $weightPushUp
                - $userSummary['canning'] * $weightCanning
                - $userSummary['breakfast'] * $weightBreakfast;
            $list[] = sprintf('* %s : %d points left', $userName, $point);
        }

        $text = sprintf("*Summary points* :%s\n", implode("\n", $list));

        return $text;
    }

    public function add(array $commandArgs)
    {
        if (count($commandArgs) < 4 || !is_numeric($commandArgs[3]) || !$this->isAuthorizedActionType($commandArgs[2])) {
            return $this->help(['help', 'add']);
        }

        $userSlackName = $commandArgs[1];
        $id = $this->getNextId('actions');
        $currentDate = date('Y/m/d');
        $number = $commandArgs[3];
        $userId = $this->getUserId($userSlackName);
        $actionType = $commandArgs[2];

        $query = "INSERT INTO actions VALUES (:id, :date, :nb_minute, :user_id, :action_type, :sprint_number)";
        $statement = $this->pdo->prepare($query);

        $params = [
            ':id' => $id,
            ':date' => $currentDate,
            ':nb_minute' => $number,
            ':user_id' => $userId,
            ':action_type' => $actionType,
            ':sprint_number' => getenv('SPRINT_NUMBER')
        ];

        $this->executeStatement($statement, $params);

        $text = "*Add $actionType* :\n";
        $text .= sprintf('%d %s has been added to %s', $number, $actionType, $userSlackName);

        return $text;
    }

    /**
     * Display slack bot help
     * @param array $commandArgs
     * @return string $text
     */
    public function help(array $commandArgs = [])
    {
        $text = "*List of available command* :\n";
        $text .= "* *show* : actions list\n";
        $text .= "* *counter* : actions counter\n";
        $text .= "* *sum* : summary points\n";
        $text .= "* *add* : add action to someone\n";
        $text .= "* *help* _[command]_ : display command detail\n";

        if (isset($commandArgs[1])) {
            if ($commandArgs[1] == 'show') {
                $text = "*Detail for show command* :\n";
                $text .= "show [slack_name] [action_type]\n";
                $text .= "Example 1 : show\n";
                $text .= "Example 3 : show late\n";
            } else if ($commandArgs[1] == 'counter') {
                $text = "*Detail for counter command* :\n";
                $text .= "counter [slack_name] [action_type]\n";
                $text .= "Example 1 : counter\n";
                $text .= "Example 3 : counter late\n";
            } else if ($commandArgs[1] == 'sum') {
                $text = "*Detail for sum command* :\n";
                $text .= "sum [slack_name]\n";
                $text .= "Example 1 : sum\n";
            } else if ($commandArgs[1] == 'add') {
                $text = "*Detail for add command* :\n";
                $text .= "add [slack_name] [action_type] [number]\n";
                $text .= "Example 1 : add @pierre-yves push-up 20\n";
                $text .= "Example 2 : add @pierre-yves late 10\n";
                $text .= "Example 3 : add @pierre-yves canning 60\n";
                $text .= "Example 3 : add @pierre-yves breakfast 10\n";
            }
        }

        $text .= sprintf("\nAuthorized actions type are : %s", implode(', ', self::authorizedActionType));

        return $text;
    }
}
