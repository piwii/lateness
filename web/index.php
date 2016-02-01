<?php

require_once __DIR__.'/../vendor/autoload.php';

require_once'../Services/LatenessService.php';
require_once '../Models/ActionModel.php';
require_once '../Models/UserModel.php';

use \Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => 'php://stderr',
));

$dbOpts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
    array(
        'pdo.dsn' => 'pgsql:dbname=' . ltrim($dbOpts["path"], '/') . ';host=' . $dbOpts["host"],
        'pdo.port' => $dbOpts["port"],
        'pdo.username' => $dbOpts["user"],
        'pdo.password' => $dbOpts["pass"]
    )
);

$app['userModel'] = function () use ($app) {
    return new \Models\UserModel($app['pdo']);
};

$app['actionModel'] = function () use ($app) {
    return new \Models\ActionModel($app['pdo']);
};

$app['lateness'] = function () use ($app) {
    return new \Services\LatenessService($app['userModel'], $app['actionModel'], $app['monolog']);
};


$app->post('/', function (Request $request) use ($app) {
    $app['monolog']->addDebug(sprintf('Command from user : %s', $request->get('text')));
    $commandArgs = explode(' ', $request->get('text'));
    $method = $commandArgs[0];
    if (method_exists($app['lateness'], $method)) {

        $text = $app['lateness']->$method($commandArgs);
    } else {
        $text = $app['lateness']->help();
    }

    $content = array(
        "text" => $text,
        "mrkdwn" => true,
    );

    $response = new \Symfony\Component\HttpFoundation\Response();
    $response->headers->set('Content-Type', 'application/json');
    $response->setStatusCode(200);
    $response->setContent(json_encode($content));

    return $response;
});

$app->run();
#878787
/*
  $attachments = new \stdClass;
  $attachments->text = "Partly cloudy today and tomorrow";
  $content = array(
      "text" =>  $app['request']->get('user_name'),
      "attachments" => [$attachments]
  );
*/
