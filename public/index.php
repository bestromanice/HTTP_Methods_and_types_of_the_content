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


// ЗАДАНИЕ 2
// если в маршруте будет определен GET параметр с ключом format
// то нужно вернуть не html, а json или текстовое представление
$app->get('/users', function (Request $request, Response $response, $args) {
    // GET Query params
    // $query_params = $request->getQueryParams('Content Type : application/json');
    $query_params = $request->getQueryParams();
    // dump($query_params);
    // die;
    $format = $query_params["format"] ?? null;
    //header('Content-Type: text/plain');
    //header('Content-Type: application/json; charset=utf-8');

    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);
    // dump($users);
    // die;

    $view = Twig::fromRequest($request);

    if ($format === "json") {
        $payload = json_encode($users);
        $response->getBody()->write($payload);
        return $response->withHeader("Content-Type", "application/json");
    }

    if ($format === "text") {
        $printout = "";
        foreach($users as $user) {
            $printout .= $user->first_name." ".$user->last_name." ".$user->email.PHP_EOL;
            // dump($printout);
            // dd($response->getBody());
        }
        $response->getBody()->write($printout);
        return $response->withHeader("Content-Type", "text/plain");
    }

    return $view->render($response, 'users.html', [
        'users' => $users
    ]);
});

// ЗАДАНИЕ 8
$app->get('/users/report', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->AddPage();

    $user_report = 'user_report_' . date('Y-m-d');

    foreach ($users as $user) {
        $pdf->Ln();
        $pdf->writeHTML('<p>'.$user->id.' '.$user->first_name.' '.$user->last_name.' '.$user->email.'</p>');
    }

    // Output the PDF as a download
    $pdf_data = $pdf->Output($user_report . '.pdf', 'S');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $user_report . '.pdf"');
    echo $pdf_data;

    return $response->withStatus(201);
});


// ЗАДАНИЕ 3
// если клиент отправляет запрос с заголовком
// Accept = application/json то вернуть json.
// если с text/plain то вернуть текст.
// если у клиента тип контента не совпадает с text/plain или с application/json
// то вернуть ошибку 404
$app->get('/users-by-header', function (Request $request, Response $response, $args) {
    // dd($request->getHeaders()["Accept"][0]);
    $acceptHeader = $request->getHeader("Accept")[0];
    // dd($acceptHeader);
    // dump($query_params);
    // die;

    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);
    // dump($users);
    // die;

    if ($acceptHeader === "application/json") {
        $payload = json_encode($users);
        $response->getBody()->write($payload);
        return $response->withHeader("Content-Type", "application/json");
    }

    if ($acceptHeader === "text/plain") {
        $printout = "";
        foreach($users as $user) {
            $printout .= $user->first_name." ".$user->last_name." ".$user->email.PHP_EOL;
            // dump($printout);
            // dd($response->getBody());
        }
        $response->getBody()->write($printout);
        return $response->withHeader("Content-Type", "text/plain");
    }

    return $response->withStatus(404);
});

$app->get('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];

    $db = $this->get('db');
    $sth = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
    $sth->bindValue(':id', $id);
    $sth->execute();

    $user = $sth->fetch(\PDO::FETCH_OBJ);
    if ($user === false) {
        return $response->withStatus(404);
    }
    
    $view = Twig::fromRequest($request);
    return $view->render($response, 'user.html', [
        'user' => $user
    ]);
});


// ЗАДАНИЕ 4
// через postman создать пользователя
// тело отправляем в json
// нужно создать и вернуть созданного пользователя в json
// код ответа 201
$app->post('/users', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    // header("Content-Type: application/json");
    $parsedBody = $request->getParsedBody();
    // dd($parsedBody);
    // получаем тело запроса
    // dump($parsedBody);
    // die;
    $sth = $db->prepare("INSERT INTO users (first_name, last_name, email) VALUES (?,?,?)");

    $first_name = $parsedBody["first_name"];
    $last_name = $parsedBody["last_name"];
    $email = $parsedBody["email"];
    // dd($first_name, $last_name, $email);

    $sth->execute([$first_name, $last_name, $email]);

    return $response->withStatus(201);
});


// ЗАДАНИЕ 5
// через postman обновить пользователя
// вернуть json обновленного пользователя, статус 200
$app->patch('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();
    // dd($parsedBody);

    $past = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
    $past->bindValue(':id', $id);
    $past->execute();
    $user = $past->fetch(\PDO::FETCH_OBJ);

    $sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    $first_name = $parsedBody["first_name"] ?? $user->first_name;
    $last_name = $parsedBody["last_name"] ?? $user->last_name;
    $email = $parsedBody["email"] ?? $user->email;

    $sth->execute([$first_name, $last_name, $email, $id]);
    return $response->withStatus(201);
});


// пример ендпоинта для ЗАДАНИЯ 7
// после выполнения идёт редирект на страницу списка пользователей
$app->put('/users/{id}', function (Request $request, Response $response, $args) use ($app) {
    $id = $args['id'];
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();
    // dd($parsedBody);
    $sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    
    $first_name = $parsedBody["first_name"];
    $last_name = $parsedBody["last_name"];
    $email = $parsedBody["email"];
    // dd($first_name, $last_name, $email, $id);
    // dump($parsedBody);
    // die;

    $sth->execute([$first_name, $last_name, $email, $id]);
    return $response->withHeader('Location', '/users')->withStatus(302);
});


// ЗАДАНИЕ 6
// Удалить пользователя
// вернуть статус ответа 204, при последующих запросах также возвращается статус 204
// если удалили пользователя с id = 1
// то при переходе на страницу
// GET /users/1 бекенд должен вернуть 404 ошибку
$app->delete('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');

    $sth = $db->prepare("DELETE FROM users WHERE id=?");
    $sth->execute([$id]);

    return $response->withStatus(204);    
});

$methodOverrideMiddleware = new MethodOverrideMiddleware();
$app->add($methodOverrideMiddleware);

$app->add(TwigMiddleware::create($app, $twig));
$app->run();
