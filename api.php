<?php

header('Content-Type: application/json');
include 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

match ($method) {
    'GET' => getUsers($pdo),
    default => json_encode(['error' => 'Método no soportado']),
};

function getUsers($pdo)
{
    $query = 'SELECT * FROM users';

    $stmt = $pdo->prepare($query);

    $stmt->execute();

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($result);
}
