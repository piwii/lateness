<?php

namespace Models;

class UserModel extends AbstractModel
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

    /**
     * Get user id function of his slack name
     *
     * @param $userSlackName
     *
     * @return mixed
     * @throws \Exception
     */
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
