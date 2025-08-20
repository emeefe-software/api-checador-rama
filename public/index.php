<?php
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use App\Database;
use App\Middleware\IpMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Cargar settings
$settings = require __DIR__ . '/../config/settings.php';
// Crear instancia de base de datos
$db = new Database($settings['db']);
$pdo = $db->getConnection();

$app = AppFactory::create();

// Registrar middleware de IP (antes de las rutas)
$app->add(new IpMiddleware($db));

// Podemos compartir la conexión usando container o atributos del app 
(require __DIR__ . '/../src/Routes.php')($app, $pdo);

// Middleware para tratar las peticiones con rutas que no existen
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

//Handler para rutas que no existen
$errorMiddleware->setErrorHandler(
    HttpNotFoundException::class,
    function ($request, $exception, $displayErrorDetails) use ($app) {
        $response = $app->getResponseFactory()->createResponse();
        $response->getBody()->write(json_encode([
            'error' => 'Ruta no encontrada',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
);

$app->run();
