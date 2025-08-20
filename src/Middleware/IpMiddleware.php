<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Database;
use Slim\Psr7\Response as SlimResponse;

class IpMiddleware implements MiddlewareInterface
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    private function getClientIp()
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CF_CONNECTING_IP',
            'REMOTE_ADDR'
        ];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }
        return '0.0.0.0';
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'ip' LIMIT 1");
        $stmt->execute();
        $allowedIp = $stmt->fetchColumn();

        $clientIp = $this->getClientIp();
        error_log("IP detectada: $clientIp");

        if ($clientIp !== $allowedIp) {
            // Crear una nueva respuesta directamente (NO usar handle)
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'error' => 'Acceso denegado, no te encuentras en las instalaciones'
            ]));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
