# Operación

## Despliegue (Nginx + PHP-FPM)
1. `git pull` de la versión etiquetada; `composer install --no-dev --optimize-autoloader`.
2. `.env`: `CI_ENVIRONMENT=production`, `app.forceGlobalSecureRequests=true`, `cookie.secure=true`,
   `encryption.key` (gestor de secretos), `HV_RLS=true`, `app.proxyIPs` con los proxies de confianza
   (Cloudflare/Nginx) para que el bloqueo por IP use la IP real.
3. `php spark hv:install` (idempotente). 4. Raíz web = `public/`; `writable/` sin acceso web.
5. Usuario de BD **sin** superusuario ni `BYPASSRLS` (RLS usa FORCE; el dueño también queda sujeto).

## Tareas programadas
- `php spark hv:lifecycle` diario (lock de archivo; idempotente): ciclo de suscripciones, oportunidades de
  renovación/prueba/ampliación en el CRM de HV, extractos mensuales de comisiones y tareas de componentes
  (vencimiento y recordatorios de cotizaciones). Negocio «casa»: `HV_HOUSE_COMPANY` (por defecto `herramientas-virtuales`).

## Copias de seguridad (RPO 24 h / RTO 8 h iniciales)
- `pg_dump -Fc hvcrm` diario cifrado fuera del servidor; probar restauración mensual en staging.
- Respaldar `.env`/secretos por separado (gestor de secretos).

## Incidentes
- Revocar sesiones de un usuario: suspender la membresía o cambiar su contraseña (incrementa `session_version`).
- Revocar integraciones: Negocio → Tokens de API → Revocar.
- Bloquear un componente: Plataforma → Componentes → Deshabilitar.
- Rastreo: cada respuesta lleva `X-Request-Id`, también guardado en `audit_events.request_id`.
