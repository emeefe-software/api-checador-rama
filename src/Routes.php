<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app, PDO $pdo) {
    $app->get('/users', function (Request $request, Response $response) use ($pdo) {
        $stmt = $pdo->query("SELECT * FROM users");
        $items = $stmt->fetchAll();

        $response->getBody()->write(json_encode($items));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/createRegister', function (Request $request, Response $response) use ($pdo) {
        $input = (string) $request->getBody();
        $data = json_decode($input, true);
        $pin = $data['pin'] ?? null;

        // Validaciones
        //Valida que el pin venga en el cuerpo de la peticion
        if (!$pin) {
            $response->getBody()->write(json_encode(['error' => 'El pin es requerido']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        //Valida que el pin sea de 4 digitos y que sea numerico
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            $response->getBody()->write(json_encode(['error' => 'Error en la petición']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Buscar usuario activo con el pin
            $stmt = $pdo->prepare("SELECT * FROM users WHERE pin = :pin AND status = 'active' LIMIT 1");
            $stmt->execute(['pin' => $pin]);
            $user = $stmt->fetch();

            if (!$user) {
                $response->getBody()->write(json_encode(['error' => 'Usuario no encontrado o no activo']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $userId = $user['id'];
            $now = new DateTime('now', new DateTimeZone('America/Mexico_City')); // Ajusta a tu zona
            $startOfDay = $now->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $endOfDay = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare("SELECT * FROM registers WHERE user_id = :user_id AND start_at BETWEEN :startOfDay AND :endOfDay LIMIT 1");
            $stmt->execute([
                'user_id' => $userId,
                'startOfDay' => $startOfDay,
                'endOfDay' => $endOfDay,
            ]);
            $entry = $stmt->fetch();

            if (!$entry) {
                // No hay entrada para hoy, crear una nueva
                $stmt = $pdo->prepare("INSERT INTO registers (user_id, start_at, created_at, updated_at) VALUES (:user_id, NOW(), NOW(), NOW())");
                $stmt->execute(['user_id' => $userId]);

                $entryId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("SELECT start_at FROM registers WHERE id = :id");
                $stmt->execute(['id' => $entryId]);
                $newEntry = $stmt->fetch();

                $formattedDate = date('Y-m-d H:i:s', strtotime($newEntry['start_at']));

                $response->getBody()->write(json_encode([
                    'message' => 'Entrada registrada correctamente',
                    'entrada' => $formattedDate
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            } else {
                // Ya existe registro de entrada para hoy
                if ($entry['end_at'] !== null) {
                    // Ya tiene salida registrada
                    $response->getBody()->write(json_encode(['error' => 'Ya existe un registro de entrada y salida para este usuario hoy.']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }
                // Registrar salida actualizando el mismo registro
                $stmt = $pdo->prepare("UPDATE registers SET end_at = NOW(), updated_at = NOW() WHERE id = :id");
                $stmt->execute(['id' => $entry['id']]);

                $stmt = $pdo->prepare("SELECT end_at FROM registers WHERE id = :id");
                $stmt->execute(['id' => $entry['id']]);
                $newExit = $stmt->fetch();

                $formattedDate = date('Y-m-d H:i:s', strtotime($newExit['end_at']));

                $response->getBody()->write(json_encode([
                    'message' => "Salida registrada correctamente",
                    'salida' => $formattedDate
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            }
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
};
