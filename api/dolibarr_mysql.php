<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$path  = trim(isset($_GET['path']) ? $_GET['path'] : '', '/');
$limit = min((int)(isset($_GET['limit']) ? $_GET['limit'] : 100), 2000);

$host   = 'fidinmo.com.ar';
$dbname = 'fidinmo_fidv11';
$user   = 'fidinmo_uv11';
$pass   = 'Clavelarga1';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 15,
        ]
    );
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]);
    exit();
}

if ($path === 'thirdparties') {
    $stmt = $pdo->prepare("
        SELECT
            s.rowid AS id,
            s.nom   AS name,
            e.clasificacion_trazabilidad,
            e.documentacion_complementaria,
            e.pagos_trazabilidad_financiera,
            e.reserva_boleto_fechasclave,
            e.identificacion_contrato
        FROM llx_societe s
        LEFT JOIN llx_societe_extrafields e ON e.fk_object = s.rowid
        WHERE s.client = 1
        ORDER BY s.nom
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $result = array_map(function($r) {
        return [
            'id'   => $r['id'],
            'name' => $r['name'],
            'nom'  => $r['name'],
            'array_options' => [
                'options_clasificacion_trazabilidad'    => $r['clasificacion_trazabilidad']    ?? 0,
                'options_documentacion_complementaria'  => $r['documentacion_complementaria']  ?? 0,
                'options_pagos_trazabilidad_financiera' => $r['pagos_trazabilidad_financiera'] ?? 0,
                'options_reserva_boleto_fechasclave'    => $r['reserva_boleto_fechasclave']    ?? 0,
                'options_identificacion_contrato'       => $r['identificacion_contrato']       ?? 0,
            ],
        ];
    }, $rows);

    echo json_encode($result);
    exit();
}

// Ruta de diagnóstico
if ($path === 'status') {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM llx_societe WHERE client = 1");
    echo json_encode(['ok' => true, 'clientes' => $stmt->fetch()['total']]);
    exit();
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada: ' . $path]);
