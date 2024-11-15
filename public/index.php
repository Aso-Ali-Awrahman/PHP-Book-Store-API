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

$app->get("/book/{isbn}", function (Request $request, Response $response, array $args) {
    $isbn = $args['isbn'];
    $db = getConnection();
    $book = $db->query('SELECT * FROM books WHERE isbn = $isbn')->fetch(PDO::FETCH_OBJ);
    if (!$book) {
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode($book));
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

    $body = json_decode($request->getBody());
    $isbn = $body->isbn;
    $title = $body->title;
    $author = $body->author;
    $genre = $body->genre;
    $pages = $body->pages;
    $language = $body->language;
    $publisher = $body->publisher;
    $format = $body->format;
    $quantity = $body->quantity;
    $price = $body->price;
    $year = $body->year;


    if (!checkISBN($isbn)) {
        $response->getBody()->write(json_encode(["error" => "ISBN already exists!"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    elseif ($price < 0.0) {
        $response->getBody()->write(json_encode(["error" => "Price cannot be less than zero!"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    elseif ($quantity < 1) {
        $response->getBody()->write(json_encode(["error" => "Quantity cannot be less than 1!"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = getConnection();
    $stmt = $db->prepare("INSERT INTO books (isbn, title, author, genre, pages, language, publisher, format, price, quantity, year)
                                VALUES ('$isbn', '$title', '$author', '$genre', $pages, '$language', '$publisher', '$format', $price, $quantity, $year)");

    if ($stmt->execute()) {
        $response->getBody()->write(json_encode(["success" => "Book added!"]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(["error" => "Book not added!"]));
    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');

});

$app->put("update-book/{isbn}", function (Request $request, Response $response, array $args) {
    $body = json_decode($request->getBody());
    $isbn = $args['isbn'];
    $title = $body->title;
    $author = $body->author;
    $genre = $body->genre;
    $pages = $body->pages;
    $language = $body->language;
    $publisher = $body->publisher;
    $format = $body->format;
    $quantity = $body->quantity;
    $price = $body->price;
    $year = $body->year;

    $db = getConnection();
    $query = "UPDATE books SET title = '$title', author = '$author', genre = '$genre', pages = $pages, 
                 language = '$language', publisher = '$publisher', format = '$format', quantity = $quantity, 
                 price = $price, year = $year
             WHERE isbn = $isbn";
    $stmt = $db->prepare($query);

    if (!$stmt->execute() && $stmt->rowCount() === 0) {
        $response->getBody()->write(json_encode(["error" => "Book not updated! wrong ISBN number"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode(["success" => "Book updated!"]));
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});

$app->post('/transaction/{isbn}', function (Request $request, Response $response, array $args) {

    $body  = json_decode($request->getBody());
    $price = $body->price;
    $quantity = $body->quantity;
    $isbn = $args['isbn'];

    if ($quantity < 1 || $price < 0.0) {
        $response->getBody()->write(json_encode(["error" => "Quantity or Price cannot be less than 1!"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    $db = getConnection();
    if (checkISBN($isbn)) {
        $response->getBody()->write(json_encode(["error" => "Book does not exists!"]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    if (checkBookQuantity($isbn, $quantity, $db)) {
        $response->getBody()->write(json_encode(["error" => "Quantity exceeded! for that book"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }


    $stmt = $db->prepare("INSERT INTO transactions (book_id, price, quantity)
                                VALUE ($isbn, $price, $quantity)");

    if ($stmt->execute()) {
        $updateBook = $db->prepare("UPDATE books SET quantity = quantity - $quantity WHERE isbn = $isbn");
        $updateBook->execute();
        $response->getBody()->write(json_encode(["success" => "Transaction Successful!"]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }


    $response->getBody()->write(json_encode($body));
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});

$app->run();


function checkISBN(int $isbn): bool
{
    $db = getConnection();
    $book = $db->query("SELECT * FROM books WHERE isbn = $isbn")->fetch(PDO::FETCH_OBJ);
    if ($book)
        return false;
    return true;
}

function checkBookQuantity(int $isbn, int $quantity, PDO $db): bool
{
    $book = $db->query("SELECT * FROM books WHERE isbn = $isbn")->fetch(PDO::FETCH_OBJ);
    if ($book->quantity < $quantity)
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