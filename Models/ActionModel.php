<?php

namespace Models;

use Herrera\Pdo\PdoStatement;

class ActionModel
{
    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * LatenessService constructor.
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function executeStatement(PDOStatement $statement, $params = array())
    {
        if (($result = $statement->execute($params)) == false) {
            $message = sprintf('Query error on %s [error code : %s]', $statement->queryString, $statement->errorCode());
            throw new \Exception($message);
        }

        return $result;
    }

    public function getNextId()
    {
        $query = 'SELECT MAX(id) + 1 AS next_id FROM actions';
        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $nextId = $row == null ? 1 : $row['next_id'];

        return $nextId;
    }


    public function getList($params)
    {
        $query = 'SELECT l.day, l.nb_minutes, u.name
                  FROM actions l JOIN users u ON u.id = l.user_id
                  WHERE action_type = :action_type AND sprint_number = :sprint_number
                  ORDER BY l.day DESC';

        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);
        $dbResult = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $dbResult;
    }

    public function getCounter($params)
    {
        $query = 'SELECT SUM(l.nb_minutes), u.name
                  FROM actions l JOIN users u ON u.id = l.user_id
                  WHERE action_type = :action_type AND sprint_number = :sprint_number
                  GROUP BY u.name
                  ORDER BY sum DESC';

        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);
        $dbResult = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $dbResult;
    }

    public function getSummary($params)
    {
        $query = 'SELECT SUM(l.nb_minutes), u.name, l.action_type
                  FROM actions l JOIN users u ON u.id = l.user_id
                  WHERE sprint_number = :sprint_number
                  GROUP BY l.action_type, u.name
                  ORDER BY sum DESC';

        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);
        $dbResult = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $dbResult;
    }

    public function insert($params)
    {
        $query = "INSERT INTO actions VALUES (:id, :date, :nb_minute, :user_id, :action_type, :sprint_number)";
        $statement = $this->pdo->prepare($query);
        $this->executeStatement($statement, $params);
    }
}
