<?php

namespace Models;

use Herrera\Pdo\PdoStatement;

class AbstractModel
{
    /**
     * Execute request
     *
     * @param PdoStatement $statement
     * @param array $params
     *
     * @return bool
     * @throws \Exception
     */
    protected function executeStatement(PDOStatement $statement, $params = array())
    {
        if (($result = $statement->execute($params)) == false) {
            $message = sprintf('Query error on %s [error code : %s]',
                $statement->getPdoStatement()->queryString,
                $statement->getPdoStatement()->errorCode()
            );
            throw new \Exception($message);
        }

        return $result;
    }
}
