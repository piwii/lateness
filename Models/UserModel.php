<?php

namespace Models;

use Herrera\Pdo\PdoStatement;

class UserModel
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

    public function getUserId($userSlackName)
    {
        $query = 'SELECT id FROM users WHERE slack_name = :slack_name';
        $statement = $this->pdo->prepare($query);
        $params = [':slack_name' => $userSlackName];

        $this->executeStatement($statement, $params);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row == null) {
            $message = sprintf('Slack name %s is not found in database', $userSlackName);
            throw new \Exception($message);
        }

        return $row['id'];
    }

}
