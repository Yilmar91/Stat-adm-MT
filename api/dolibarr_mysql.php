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
        SELECT DISTINCT
            s.nom,
            se.clasificacion_trazabilidad,
            se.documentacion_complementaria,
            se.pagos_trazabilidad_financiera,
            se.reserva_boleto_fechasclave,
            se.identificacion_contrato
        FROM llx_societe_extrafields AS se
        LEFT JOIN llx_metro_reserva     AS mr ON mr.idcliente1 = se.fk_object
        LEFT JOIN llx_societe           AS s  ON s.rowid = se.fk_object
        LEFT JOIN llx_categorie_societe AS cs ON cs.fk_soc = s.rowid
        LEFT JOIN llx_categorie         AS c  ON c.rowid = cs.fk_categorie
        WHERE c.rowid IN (22, 26)
        ORDER BY s.nom
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $result = array_map(function($r) {
        return [
            'id'   => null,
            'name' => $r['nom'],
            'nom'  => $r['nom'],
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
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT s.rowid) AS total
        FROM llx_societe_extrafields AS se
        LEFT JOIN llx_societe           AS s  ON s.rowid = se.fk_object
        LEFT JOIN llx_categorie_societe AS cs ON cs.fk_soc = s.rowid
        LEFT JOIN llx_categorie         AS c  ON c.rowid = cs.fk_categorie
        WHERE c.rowid IN (22, 26)
    ");
    echo json_encode(['ok' => true, 'clientes' => $stmt->fetch()['total']]);
    exit();
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada: ' . $path]);
