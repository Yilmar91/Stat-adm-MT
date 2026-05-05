<?php
/**
 * PECAS Bridge API — Metroterra Admin Stats
 * Ruta: /opt/STAT - ADM MT/api/pecas_bridge.php
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

define('BRIDGE_TOKEN', 'MT_STATS_2025');

$auth  = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
$token = trim(str_replace('Bearer ', '', $auth));
if ($token !== BRIDGE_TOKEN) {
    http_response_code(401);
    echo json_encode(array('error' => 'No autorizado'));
    exit();
}

$DSN = 'odbc:Driver={FreeTDS};Server=pecas-prod.spazios.com.ar;Port=1433;Database=PecasSpazios;UID=Sa;PWD=Pecas123;TDS_Version=7.4;charset=UTF-8;';

$queries = array(
    'tramites' => "SELECT c.InstanciaProceso, pu.DESCRIPCION AS Usuario, pr.nombre AS Proceso, tt.nombre AS NombreTarea, c.Fecha, c.NombreReferido, c.Vinculo, c.TlfReferido, c.PagoReferido, c.ObsReferido, c.Validar, c.Asesor, c.OpCaida, c.NumReserva, CASE WHEN w.Situacion = 1 THEN 'Mesa de Entrada' WHEN w.Situacion = 2 THEN 'Tareas Pendientes' WHEN w.Situacion = 3 THEN 'Tarea en Ejecucion' WHEN w.Situacion = 4 THEN 'Tarea Abortada' WHEN w.Situacion = 5 THEN 'Tarea Finalizada' END AS Estado, CONVERT(VARCHAR(20), w.FechaC, 120) AS FInicioTarea, CONVERT(VARCHAR(20), w.FechaH, 120) AS FFinalTarea FROM PecasSpazios..WFPecasV2InstTarea w INNER JOIN PecasSpazios..PERSONASUSUARIOS pu ON pu.COD_USUARIO = w.Ejecutor INNER JOIN PecasSpazios..WFPecasV2InstPrc ip ON ip.instancia = w.InstanciaProceso INNER JOIN PecasSpazios..TQSDV2Procesos pr ON pr.Codigo = ip.Proceso AND pr.Version = ip.Version INNER JOIN PecasSpazios..TQSDV2Tareas tt ON tt.Codigo = w.Tarea AND tt.Proceso = w.Proceso AND tt.Version = w.Version LEFT JOIN SpaziosDB.dbo.ComAmigMetro_datos c ON c.InstanciaProceso = w.InstanciaProceso WHERE w.Situacion IN (1,2,3,4,5) AND pu.COD_USUARIO = 1518 AND pr.nombre = 'Comisión Amigos Metroterra' AND (tt.nombre LIKE '%Verificar Amigo Metroterra%' OR tt.nombre LIKE '%Adherir Pago%') ORDER BY w.InstanciaProceso DESC, w.FechaC DESC",

    'rescisiones' => "SELECT c.InstanciaProceso, pu.DESCRIPCION AS Usuario, pr.nombre AS Proceso, tt.nombre AS NombreTarea, c.Fecha2A, c.Reserva, c.Cliente, c.Aprob_Ger_Ingreso, c.Obs_Ger_Ingreso, c.motivo, c.Aprob_CFO, c.Obs_CFO, CASE WHEN w.Situacion = 1 THEN \'Mesa de Entrada\' WHEN w.Situacion = 2 THEN \'Tareas Pendientes\' WHEN w.Situacion = 3 THEN \'Tarea en Ejecucion\' WHEN w.Situacion = 4 THEN \'Tarea Abortada\' WHEN w.Situacion = 5 THEN \'Tarea Finalizada\' END AS Estado, CONVERT(VARCHAR(20), w.FechaC, 120) AS FInicioTarea, CONVERT(VARCHAR(20), w.FechaH, 120) AS FFinalTarea FROM PecasSpazios..WFPecasV2InstTarea w INNER JOIN PecasSpazios..PERSONASUSUARIOS pu ON pu.COD_USUARIO = w.Ejecutor INNER JOIN PecasSpazios..WFPecasV2InstPrc ip ON ip.instancia = w.InstanciaProceso INNER JOIN PecasSpazios..TQSDV2Procesos pr ON pr.Codigo = ip.Proceso AND pr.Version = ip.Version INNER JOIN PecasSpazios..TQSDV2Tareas tt ON tt.Codigo = w.Tarea AND tt.Proceso = w.Proceso AND tt.Version = w.Version LEFT JOIN SpaziosDB.dbo.Res_Metroterra_Datos c ON c.InstanciaProceso = w.InstanciaProceso WHERE w.Situacion IN (1,2,3,4,5) AND pu.COD_USUARIO = 1518 AND pr.nombre = \'cambio de condición de metroterra\' AND tt.nombre LIKE \'%Generar Documentación%\' ORDER BY w.InstanciaProceso DESC, w.FechaC DESC"
);

$endpoint = isset($_GET['q']) ? $_GET['q'] : '';
if (!array_key_exists($endpoint, $queries)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Endpoint invalido. Usa ?q=tramites o ?q=rescisiones'));
    exit();
}

$data  = array();
$error = null;

try {
    $pdo  = new PDO($DSN, null, null, array(PDO::ATTR_TIMEOUT => 30));
    $stmt = $pdo->query($queries[$endpoint]);
    if ($stmt === false) {
        $info  = $pdo->errorInfo();
        $error = 'Query error: ' . (isset($info[2]) ? $info[2] : 'desconocido');
    } else {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($row as $k => $v) {
                if ($v !== null && is_string($v)) {
                    $row[$k] = trim($v);
                }
            }
            $data[] = $row;
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

if ($error) {
    http_response_code(500);
    echo json_encode(array('error' => $error, 'endpoint' => $endpoint), JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(array(
        'ok'        => true,
        'endpoint'  => $endpoint,
        'count'     => count($data),
        'timestamp' => date('c'),
        'data'      => $data,
    ), JSON_UNESCAPED_UNICODE);
}
