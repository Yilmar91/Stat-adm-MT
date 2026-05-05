<?php
/**
 * SharePoint Excel Bridge — Metroterra Admin Stats
 *
 * Descarga el archivo "Archivo conciliación MT_2024.xlsx" desde SharePoint
 * usando Microsoft Graph API y sirve el binario al frontend.
 * El frontend (SheetJS) procesa el binario directamente.
 *
 * Soluciona el bloqueo CORS/auth que impide el fetch() directo desde el navegador.
 *
 * CREDENCIALES AZURE:
 *   - Si tapiolax.sharepoint.com está en el MISMO Azure AD tenant que spazioscomar,
 *     las credenciales de abajo funcionan sin cambios.
 *   - Si es un tenant distinto: registrar una nueva App en portal.azure.com
 *     con permisos Application: Sites.Read.All y Files.Read.All,
 *     y actualizar SP_TENANT_ID, SP_CLIENT_ID y SP_CLIENT_SECRET.
 *
 * Respuesta exitosa:  binario .xlsx  (Content-Type: application/octet-stream)
 * Respuesta de error: JSON           (Content-Type: application/json)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// ── Credenciales Azure App Registration ───────────────────────────────────────
// Cargadas desde config_private.php (excluido del repositorio git)
// En el servidor: /opt/STAT - ADM MT/api/config_private.php
// App debe tener Sites.Read.All o Files.Read.All (Application permissions)
$_cfg = __DIR__ . '/config_private.php';
if (!file_exists($_cfg)) {
    json_error(500, 'Falta api/config_private.php con las credenciales Azure. Ver README o pedir al administrador.');
}
require $_cfg;
// Ahora disponibles: SP_TENANT_ID, SP_CLIENT_ID, SP_CLIENT_SECRET

// ── SharePoint — archivo de conciliación MT ────────────────────────────────────
define('SP_HOSTNAME',  'tapiolax.sharepoint.com');
define('SP_SITE_PATH', '/sites/met-division3');

// Item ID del archivo (valor "sourcedoc" del link de SharePoint)
// URL original: sourcedoc=%7B65F15C31-9E82-4C3C-8BED-1C954595817A%7D
define('SP_ITEM_GUID', '65F15C31-9E82-4C3C-8BED-1C954595817A');

// Nombre alternativo para búsqueda si el item ID no coincide directamente
define('SP_FILE_SEARCH', 'Archivo conciliaci');

// ── Helpers ────────────────────────────────────────────────────────────────────

function json_error($code, $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit();
}

function graph_get($token, $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Accept: application/json",
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($cerr) throw new Exception("cURL error: $cerr | URL: $url");
    if ($code !== 200) throw new Exception("Graph API HTTP $code en $url: " . substr($resp, 0, 300));
    return json_decode($resp, true);
}

// ── 1. Token OAuth2 ────────────────────────────────────────────────────────────

try {

    $token_url  = 'https://login.microsoftonline.com/' . SP_TENANT_ID . '/oauth2/v2.0/token';
    $token_body = http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => SP_CLIENT_ID,
        'client_secret' => SP_CLIENT_SECRET,
        'scope'         => 'https://graph.microsoft.com/.default',
    ]);
    $ch = curl_init($token_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $token_body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $tok_resp = curl_exec($ch);
    $tok_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tok_cerr = curl_error($ch);
    curl_close($ch);

    if ($tok_cerr) json_error(502, "cURL error autenticando con Azure: $tok_cerr");
    if ($tok_code !== 200) json_error(502, "Error OAuth2 (HTTP $tok_code): " . substr($tok_resp, 0, 300));

    $tok_data = json_decode($tok_resp, true);
    if (empty($tok_data['access_token'])) json_error(502, "Token vacío en respuesta OAuth2: $tok_resp");
    $token = $tok_data['access_token'];

    // ── 2. Site ID ─────────────────────────────────────────────────────────────

    $site_url = 'https://graph.microsoft.com/v1.0/sites/' . SP_HOSTNAME . ':' . SP_SITE_PATH;
    $site_data = graph_get($token, $site_url);
    if (empty($site_data['id'])) json_error(502, "No se obtuvo site ID de SharePoint: " . json_encode($site_data));
    $site_id = $site_data['id'];

    // ── 3. Buscar el archivo ────────────────────────────────────────────────────
    // Estrategia A: buscar por nombre en la raíz del drive principal (Documentos)
    // Estrategia B (fallback): listar drives y buscar en todos

    $download_url = null;

    // A: search en el drive raíz del sitio
    $search_q   = rawurlencode(SP_FILE_SEARCH);
    $search_url = "https://graph.microsoft.com/v1.0/sites/{$site_id}/drive/root/search(q='{$search_q}')";
    try {
        $search_data = graph_get($token, $search_url);
        if (!empty($search_data['value'])) {
            foreach ($search_data['value'] as $item) {
                if (!empty($item['file']) && stripos($item['name'], SP_FILE_SEARCH) !== false) {
                    $download_url = $item['@microsoft.graph.downloadUrl'] ?? null;
                    if (!$download_url && !empty($item['id'])) {
                        // Obtener download URL explícita
                        $item_data    = graph_get($token, "https://graph.microsoft.com/v1.0/sites/{$site_id}/drive/items/{$item['id']}");
                        $download_url = $item_data['@microsoft.graph.downloadUrl'] ?? null;
                    }
                    if ($download_url) break;
                }
            }
        }
    } catch (Exception $e) {
        // Continuar con fallback
    }

    // B: fallback — buscar en todos los drives del sitio
    if (!$download_url) {
        $drives_data = graph_get($token, "https://graph.microsoft.com/v1.0/sites/{$site_id}/drives");
        foreach ($drives_data['value'] ?? [] as $drive) {
            try {
                $drive_id   = $drive['id'];
                $list_url   = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root/search(q='" . rawurlencode(SP_FILE_SEARCH) . "')";
                $list_data  = graph_get($token, $list_url);
                foreach ($list_data['value'] ?? [] as $item) {
                    if (!empty($item['file']) && stripos($item['name'], SP_FILE_SEARCH) !== false) {
                        $download_url = $item['@microsoft.graph.downloadUrl'] ?? null;
                        if (!$download_url && !empty($item['id'])) {
                            $item_data    = graph_get($token, "https://graph.microsoft.com/v1.0/drives/{$drive_id}/items/{$item['id']}");
                            $download_url = $item_data['@microsoft.graph.downloadUrl'] ?? null;
                        }
                        if ($download_url) break 2;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }

    if (!$download_url) {
        json_error(404, "Archivo '" . SP_FILE_SEARCH . "*.xlsx' no encontrado en el sitio " . SP_HOSTNAME . SP_SITE_PATH . ". Verificá que la App Registration tenga acceso al sitio.");
    }

    // ── 4. Descargar binario y servir al frontend ──────────────────────────────

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Cache-Control: no-store');

    $ch = curl_init($download_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $xlsx_bin = curl_exec($ch);
    $dl_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $dl_cerr  = curl_error($ch);
    curl_close($ch);

    if ($dl_cerr) json_error(502, "Error descargando archivo: $dl_cerr");
    if ($dl_code !== 200) json_error(502, "Error descargando archivo (HTTP $dl_code)");

    echo $xlsx_bin;

} catch (Exception $e) {
    json_error(500, $e->getMessage());
}
