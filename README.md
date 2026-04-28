# STAT-ADM-MT — Panel de Administracion Metroterra
## Guia de instalacion — Servidor 72.60.245.72 (Caddy + PHP)

## Estructura de archivos

```
/opt/STAT - ADM MT/
├── Caddyfile               <- Configuracion Caddy
├── index.html              <- App principal
├── README.md               <- Este archivo
└── api/
    └── pecas_bridge.php    <- Puente PHP -> SQL Server PECAS
```

## Paso 1 — Subir archivos al servidor

```bash
# Desde tu maquina local:
scp -r ./proyecto-final/ root@72.60.245.72:"/opt/STAT - ADM MT/"

# O desde el servidor directamente con el ZIP descargado
```

## Paso 2 — Verificar PHP-FPM (ya instalado)

```bash
sudo systemctl status php8.1-fpm
sudo systemctl enable php8.1-fpm
sudo systemctl start php8.1-fpm
```

## Paso 3 — Configurar Caddy

```bash
# Crear directorio de logs
sudo mkdir -p /var/log/caddy
sudo chown caddy:caddy /var/log/caddy

# Copiar Caddyfile y recargar
sudo cp "/opt/STAT - ADM MT/Caddyfile" /etc/caddy/Caddyfile
sudo systemctl reload caddy
sudo systemctl status caddy
```

## Paso 4 — Verificar el puente PECAS

```bash
curl -H "Authorization: Bearer MT_STATS_2025" \
  "http://72.60.245.72/api/pecas_bridge.php?q=tramites"

# Respuesta esperada:
# {"ok":true,"count":2221,"data":[...]}

curl -H "Authorization: Bearer MT_STATS_2025" \
  "http://72.60.245.72/api/pecas_bridge.php?q=rescisiones"
```

## Paso 5 — Abrir la app

Abrí en el browser: http://72.60.245.72

La configuracion ya viene precargada:
- URL Dolibarr:   http://fidinmo.com.ar/fidinmo/htdocs
- API Key:        a6f2c89411a281d03fb5f105ec81707f
- URL Puente:     http://72.60.245.72/api/pecas_bridge.php
- Token:          MT_STATS_2025

Si algo no conecta, usa el boton Configuracion -> Probar conexiones.

## Conexiones activas

| Sistema    | Conexion                              | Estado        |
|------------|---------------------------------------|---------------|
| Dolibarr   | API REST fidinmo.com.ar               | Directo       |
| PECAS      | PHP -> FreeTDS -> pecas-prod:1433     | Via puente    |
| Pagos      | Upload Excel manual                   | Manual        |
| Reclamos   | localStorage navegador                | Persistente   |

## Puerto 1433 abierto en PECAS

El firewall de pecas-prod.spazios.com.ar tiene una regla que permite
conexiones TCP entrantes al puerto 1433 SOLO desde la IP 72.60.245.72.
No modificar ni eliminar esa regla.

## Diagnostico

```bash
# Logs de Caddy
sudo tail -f /var/log/caddy/stat-adm-mt.log

# Logs de PHP
sudo tail -f /var/log/php8.1-fpm.log

# Test conexion PECAS desde el servidor
tsql -H pecas-prod.spazios.com.ar -p 1433 -U Sa -P Pecas123 -D PecasSpazios
```
