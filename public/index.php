<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\BasePath\BasePathMiddleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Add Slim routing middleware
$app->addRoutingMiddleware();

//   http://localhost:8888/

$app->add(new BasePathMiddleware($app));

$app->addErrorMiddleware(true, true, true);


$app->get('/books', function (Request $request, Response $response, array $args) {

    $db = getConnection();
    $books = $db->query('SELECT * FROM books')->fetchAll(PDO::FETCH_OBJ);
    $response->getBody()->write(json_encode($books));

    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});

$app->get('/books-by-title/{title}', function (Request $request, Response $response, array $args) {

    $title = $args['title'];
    $db = getConnection();
    $books = $db->query("SELECT * FROM books WHERE title LIKE '$title%'")->fetchAll(PDO::FETCH_OBJ);
    if (count($books) === 0) {
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode($books));
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});

$app->get('/books-by-author/{author}', function (Request $request, Response $response, array $args) {

    $author = $args['author'];
    $db = getConnection();
    $books = $db->query("SELECT * FROM books WHERE author LIKE '$author%'")->fetchAll(PDO::FETCH_OBJ);
    if (count($books) === 0) {
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode($books));
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});

$app->post('/add-book', function (Request $request, Response $response, array $args) {

    $body = json_encode($request->getBody());
    $isbn = $body['isbn'];
    $title = $body['title'];
    $author = $body['author'];
    $genre = $body['genre'];
    $pages = $body['pages'];
    $language = $body['language'];
    $publisher = $body['publisher'];
    $format = $body['format'];
    $quantity = $body['quantity'];
    $price = $body['price'];
    $year = $body['year'];

    if (checkISBN($isbn)) {
        $response->withBody(json_encode(["error" => "ISBN already exists!"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    elseif ($price < 0.0) {
        $response->withBody(json_encode(["error" => "Price cannot be less than zero!"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    elseif ($quantity < 1) {
        $response->withBody(json_encode(["error" => "Quantity cannot be less than 1!"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = getConnection();
    $stmt = $db->prepare("INSERT INTO books (isbn, title, author, genre, pages, language, publisher, format, price, quantity)
                                VALUES ('$isbn', '$title', '$author', '$genre', $pages, '$language', '$publisher', '$format', $price, $quantity)");

    if ($stmt->execute()) {
        $response->getBody()->write(json_encode(["success" => "Book added!"]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(["error" => "Book not added!"]));
    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');

});


$app->run();


function checkISBN(string $isbn): bool
{
    $db = getConnection();
    $book = $db->query("SELECT * FROM books WHERE isbn = '$isbn'")->fetch(PDO::FETCH_OBJ);
    if ($book)
        return true;
    return false;
}

function getConnection(): PDO
{
    $dbhost = "localhost";
    $dbuser = "root";
    $dbpass = 'app1';
    $dbname = "Book Store";

    return new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
}