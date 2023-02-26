<?php

use DI\Container;
use Slim\Views\Twig;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);

$container->set('db', function () {
    $db = new \PDO("sqlite:" . __DIR__ . '/../database/database.sqlite');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(\PDO::ATTR_TIMEOUT, 5000);
    $db->exec("PRAGMA journal_mode = WAL");
    return $db;
});

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$twig = Twig::create(__DIR__ . '/../twig', ['cache' => false]);

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->get('/users', function (Request $request, Response $response, $args) {
    // GET Query params
    // $query_params = $request->getQueryParams();
    // dump($query_params);
    // die;

    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);
    // dump($users);
    // die;

    $view = Twig::fromRequest($request);
    return $view->render($response, 'users.html', [
        'users' => $users
    ]);
});

$app->get('/users-by-header', function (Request $request, Response $response, $args) {
    return $response->withStatus(404);
});

$app->get('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];

    $db = $this->get('db');
    $sth = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
    $sth->bindValue(':id', $id);
    $sth->execute();

    $user = $sth->fetch(\PDO::FETCH_OBJ);

    $view = Twig::fromRequest($request);
    return $view->render($response, 'user.html', [
        'user' => $user
    ]);
});

$app->post('/users', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();
    // получаем тело запроса
    // dump($parsedBody);
    // die;
    // $sth = $db->prepare("INSERT INTO users (first_name, last_name, email) VALUES (?,?,?)");
    // $sth->execute([$first_name, $last_name, $email]);
});

$app->patch('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');

    //$sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    //$sth->execute([$first_name, $last_name, $email, $id]);
});

$app->put('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();
    dump($parsedBody);
    die;
});

$app->delete('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');
});

$methodOverrideMiddleware = new MethodOverrideMiddleware();
$app->add($methodOverrideMiddleware);

$app->add(TwigMiddleware::create($app, $twig));
$app->run();
