<?php

namespace Services;

use Models\ActionModel;
use Models\UserModel;
use Monolog\Logger;
use Silex\Application;


class LatenessService
{
     const authorizedActionType = ['late', 'push-up', 'canning', 'breakfast'];

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ActionModel
     */
    private $actionModel;

    /**
     * @var UserModel
     */
    private $userModel;

    /**
     * LatenessService constructor.
     * @param UserModel $userModel
     * @param ActionModel $actionModel
     * @param Logger $monolog
     */
    public function __construct(UserModel $userModel, ActionModel $actionModel, Logger $monolog)
    {
        $this->userModel = $userModel;
        $this->actionModel = $actionModel;
        $this->logger = $monolog;
    }

    private function isAuthorizedActionType($actionType)
    {
        if (in_array($actionType, self::authorizedActionType)) {
            return true;
        }

        return false;
    }

    private function getActionTypeFromCommand($commandArgs)
    {
        $actionType = self::authorizedActionType[0];
        if (isset($commandArgs[1]) && $this->isAuthorizedActionType($commandArgs[1])) {
            $actionType = $commandArgs[1];
        }

        return $actionType;
    }

    public function show(array $commandArgs)
    {
        $params = [
            ':sprint_number' => getenv('SPRINT_NUMBER'),
            'action_type' => $this->getActionTypeFromCommand($commandArgs)
        ];

        $dbResult = $this->actionModel->getList($params);
        $list = [];
        foreach ($dbResult as $row) {
            $list[] = sprintf('* %s : %s => %d', $row['name'], $row['day'], $row['nb_minutes']);
        }

        $text = sprintf("*%s list* :\n %s", ucfirst($params['action_type']), implode("\n", $list));

        return $text;
    }

    public function counter(array $commandArgs)
    {
        $params = [
            ':sprint_number' => getenv('SPRINT_NUMBER'),
            'action_type' => $this->getActionTypeFromCommand($commandArgs)
        ];

        $dbResult = $this->actionModel->getCounter($params);
        $list = [];
        foreach ($dbResult as $row) {
            $list[] = sprintf('* %s : %d', $row['name'], $row['sum']);
        }

        $text = sprintf("*%s counter* :\n %s", ucfirst($params['action_type']), implode("\n", $list));

        return $text;
    }

    public function sum()
    {
        $params = [':sprint_number' => getenv('SPRINT_NUMBER')];

        $dbResult = $this->actionModel->getSummary($params);
        $summary = array();
        foreach ($dbResult as $row) {
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
            $list[] = sprintf('* %s : %d points', $userName, $point);
        }

        $text = sprintf("*Summary points* :\n%s", implode("\n", $list));

        return $text;
    }

    public function add(array $commandArgs)
    {
        if (count($commandArgs) < 4 || !is_numeric($commandArgs[3]) || !$this->isAuthorizedActionType($commandArgs[2])) {
            return $this->help(['help', 'add']);
        }

        $userSlackName = $commandArgs[1];
        $number = $commandArgs[3];
        $actionType = $commandArgs[2];

        $params = [
            ':id' => $this->actionModel->getNextId(),
            ':date' => date('Y/m/d'),
            ':nb_minute' => $number,
            ':user_id' => $this->userModel->getUserId($userSlackName),
            ':action_type' => $actionType,
            ':sprint_number' => getenv('SPRINT_NUMBER')
        ];

        $this->actionModel->insert($params);

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
