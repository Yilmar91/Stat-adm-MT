<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, DOLAPIKEY');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$base = rtrim(isset($_GET['base']) ? $_GET['base'] : '', '/');
if (!$base || !filter_var($base, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro base requerido']);
    exit();
}

$path    = isset($_GET['path']) ? $_GET['path'] : '';
$apikey  = isset($_GET['DOLAPIKEY']) ? $_GET['DOLAPIKEY'] : '';
$qs      = http_build_query(array_diff_key($_GET, array_flip(['base','path'])));
$target  = $base . '/api/index.php/' . ltrim($path, '/') . ($qs ? '?' . $qs : '');

$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_USERAGENT      => 'curl/7.81.0',
    CURLOPT_HTTPHEADER     => [
        'Accept: */*',
    ],
]);

$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502);
    echo json_encode(['error' => 'Proxy error: ' . $err]);
    exit();
}

http_response_code($status);
echo $body;
