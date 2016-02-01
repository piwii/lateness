<?php

require_once __DIR__.'/../vendor/autoload.php';
require('../Services/LatenessService.php');

use \Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => 'php://stderr',
));

$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
    array(
        'pdo.dsn' => 'pgsql:dbname=' . ltrim($dbopts["path"], '/') . ';host=' . $dbopts["host"],
        'pdo.port' => $dbopts["port"],
        'pdo.username' => $dbopts["user"],
        'pdo.password' => $dbopts["pass"]
    )
);


$app['lateness'] = function () use ($app) {
    return new \Services\LatenessService($app['pdo'], $app['monolog']);
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
