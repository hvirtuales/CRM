# Informe de pruebas de seguridad — HV CRM v1.0

**Fecha:** 2026-11-02 · **Alcance:** núcleo (tres espacios), componentes `crm`, `form` y `quote`, API v1,
superficies públicas (formulario y cotización), autenticación y facturación SaaS.
**Referencias:** OWASP ASVS 4.0 (nivel 2), OWASP API Security Top 10 (2023), OWASP Top 10 (2021),
NIST SP 800-63B (contraseñas y MFA), ISO/IEC 27001:2022 (A.5.15 control de acceso, A.8.15 registro,
A.8.24 criptografía, A.8.28 codificación segura), Ley 1581 de 2012 (consentimiento y trazabilidad).

## 1. Metodología

| Técnica | Herramienta | Resultado |
|---|---|---|
| Pruebas automatizadas de seguridad y aislamiento | PHPUnit 10 sobre PostgreSQL 16 **con RLS** | **80 pruebas / 778 aserciones — OK** |
| Portabilidad del esquema | Misma suite sobre SQLite 3 | 80 pruebas OK (1 omitida: RLS solo PostgreSQL) |
| Revisión de código independiente | Revisor de seguridad dedicado (sin acceso a mis conclusiones) | 1 alta, 4 medias, 4 bajas, 5 informativas — **todas corregidas con prueba de regresión** |
| Análisis de dependencias | `composer audit` | Sin avisos de vulnerabilidad |
| Análisis estático | `php -l`, búsqueda de `eval/exec/unserialize/include dinámico`, `spark hv:contract` | Sin hallazgos; los 3 componentes cumplen el contrato |
| Pruebas de navegador | Playwright (Chromium) en los 3 espacios, claro/oscuro, móvil | Sin errores de página ni violaciones de CSP |

Ejecutar: `vendor/bin/phpunit --no-coverage` (la base `hvcrm_test` se recrea en cada ejecución).

## 2. Controles verificados por pruebas automatizadas

### Aislamiento multiempresa (criterio de salida Fase 0: cero accesos cruzados)
- Un negocio no ve ni modifica contactos, oportunidades, actividades ni exportaciones de otro negocio
  del **mismo** distribuidor (IDOR en editar, mover, archivar, registrar actividad).
- No se puede mover una oportunidad a una etapa de otro negocio ni vincularla a un contacto ajeno.
- La deduplicación de contactos nunca cruza negocios.
- Un `active_company_id` falsificado en sesión se ignora: el ámbito se recalcula desde la membresía.
- Un usuario con dos ámbitos (distribuidor y consulta en un negocio) no mezcla datos.
- No se puede cambiar a una membresía ajena.
- El distribuidor solo gestiona su cartera; el `tenant_id` inyectado en formularios se ignora.
- La plataforma **no** lee datos de negocio sin un acceso de soporte aprobado por el negocio.
- **RLS PostgreSQL** como segunda barrera: sin negocio fijado no se ve ninguna fila; una consulta que
  «olvida» el filtro solo ve el negocio activo; se rechaza escribir filas de otro negocio.

### Autorización (RBAC + ABAC)
- Asesor sin acceso a usuarios, configuración, tokens, catálogo ni exportación.
- Un asesor no edita, mueve ni reasigna oportunidades de otro asesor; el gerente (`crm.manage`) sí.
- No se puede conceder un rol de otro ámbito ni modificar la propia membresía (anti-escalada).
- Componente deshabilitado ⇒ rutas 403 y menú oculto; no se deshabilita uno con dependientes.
- Plan sin el servicio ⇒ bloqueado en la ruta **y** en el servicio; cupos aplicados en el servicio.
- Suscripción suspendida ⇒ solo lectura (consulta y exportación permitidas).
- Soporte: solicitud → aprobación solo por el negocio dueño → acceso de solo lectura → revocación inmediata.

### Autenticación y sesión
- Rutas protegidas exigen sesión; mensajes de error idénticos con y sin cuenta.
- Bloqueo tras 5 fallos en 15 min (incluso con la clave correcta); límite por IP.
- MFA TOTP obligatorio para plataforma; secreto cifrado; **códigos no reutilizables**; 10 fallos ⇒
  cierre de todas las sesiones.
- Cambio de contraseña o suspensión invalidan sesiones existentes (`session_version`).
- CSRF obligatorio en formularios autenticados; cabeceras CSP (`script-src 'self'`), XFO, nosniff.
- Sin redirección abierta en el cambio de idioma (mismo origen exacto, POST + CSRF).

### Superficie pública y API
- Formulario: token HMAC (subclave HKDF) ligado al formulario, edad mínima y vencimiento, honeypot,
  límite por IP, idempotencia (sin duplicados por reenvío), validación contra la versión publicada,
  consentimiento obligatorio y trazado (`form:<id>:v<n>`), IP solo como HMAC, salida escapada.
- Formularios y cotizaciones públicos respetan el interruptor de componentes, el plan y el estado de
  la suscripción; identificadores opacos (los ids internos no sirven).
- API: Bearer obligatorio; objetos de otro negocio ⇒ 404 (BOLA); habilidades mínimas filtradas en
  servidor; `company_id` inyectado se ignora; tokens revocados, vencidos, de membresías suspendidas
  o anteriores a un cambio de contraseña dejan de funcionar; solo se guarda el SHA-256.

### Integridad comercial e inyección
- Pagos idempotentes por referencia (y la referencia no se reutiliza entre suscripciones); una sola
  comisión por pago (20 % verificado); entradas de pago malformadas rechazadas.
- Totales de cotización calculados en servidor en centavos; el precio de catálogo no se puede alterar.
- XSS almacenado escapado; búsquedas con SQLi inertes; exportación CSV neutraliza fórmulas.

## 3. Hallazgos de la revisión independiente y corrección

| # | Severidad | Hallazgo | Corrección | Prueba de regresión |
|---|---|---|---|---|
| 1 | **Alta** | Un administrador podía crear de antemano la cuenta de un tercero (conociendo su contraseña) y heredar accesos que luego se le dieran a ese correo; además permitía enumerar correos. | Alta por **invitación de un solo uso enviada al correo** (72 h, SHA-256, consumo atómico). Cuenta nueva: el titular define su contraseña. Cuenta existente: debe autenticarse con ella. El administrador nunca ve ni fija contraseñas; respuesta idéntica exista o no la cuenta. | `testInvitingNeverCreatesAccounts…`, `testSameResponseForExistingAndNewEmails`, `testInvitationAcceptanceRequiresOwnership…` |
| 2 | Media | Distribuidor podía dar servicio gratuito: negocios sin plan ilimitados, pruebas reiniciables, planes sin pago activos, levantar suspensión cambiando de plan. | Plan obligatorio al vender; negocio de distribuidor sin suscripción ⇒ solo lectura; prueba única por negocio; cambio de plan hereda estado y corte; primera suscripción paga nace **pendiente de pago**; distribuidor no cambia planes en mora/suspensión. | `testDistributorMustSellAPlan…`, `testFirstPaidSubscriptionStartsPendingPayment` |
| 3 | Media | El tiempo de login revelaba si un correo existe (hash Argon2 generado por petición). | Hash ficticio precalculado: ambos caminos hacen exactamente un `password_verify`. | `testLoginTimingDoesNotDependOnAccountExistence` |
| 4 | Media | Archivar una suscripción devolvía acceso de escritura completo. | La suscripción más reciente se evalúa en cualquier estado; archivada ⇒ solo lectura y se retira el plan. | `testArchivedSubscriptionIsReadOnlyAndLosesPlan` |
| 5 | Baja-Media | Redirección abierta en el cambio de idioma (`Referer` con prefijo del host). | Comparación estricta de esquema/host/puerto; ruta POST + CSRF. | `testLanguageSwitchDoesNotOpenRedirect` |
| 6 | Baja-Media | Formulario y cotización públicos ignoraban el interruptor de componentes y la suspensión. | Regla única `SubscriptionService::companyOperational()` + componentes/plan habilitados. | `testPublicFormHonorsKillSwitchAndBillingState` |
| 7 | Baja | Cupo de usuarios evitable reactivando miembros. | Reactivar consume cupo (y las invitaciones pendientes cuentan). | `testReactivatingMembersRespectsUserQuota` |
| 8 | Baja | MFA: reutilización del código en ±30 s; fuerza bruta lenta; sin límite al desactivar/cambiar clave. | Último paso aceptado persistido; 10 fallos ⇒ cierre de sesiones; límites en desactivar MFA, activar MFA y cambio de contraseña. | `testMfaCodesCannotBeReplayed…` |
| 9 | Baja | Cookies `secure` y HTTPS forzado no dependían del entorno. | En producción se fuerzan `cookie.secure` y `forceGlobalSecureRequests` (HSTS). | revisión de configuración |
| 10 | Info | Habilidades de token sin lista blanca en servidor; tokens sobrevivían cambio de clave; clave de cifrado usada como HMAC; `role_grants` sin validar; cotizaciones sobre oportunidades ajenas; referencia de pago reutilizable entre suscripciones. | Lista blanca `ApiTokenService::isApiAbility`; invalidación por `password_changed_at`; subclaves HKDF por propósito; `role_grants` restringidos a permisos propios; regla ABAC del CRM aplicada a cotizaciones; idempotencia por suscripción. | `testApiTokensOnlyCarryApiAbilities…`, `testManifestRoleGrants…`, `testQuoteRespectsOpportunityOwnership`, `testPaymentReferenceCannotBeReused…` |

Además, las propias pruebas detectaron y se corrigieron durante el desarrollo: redirección de no
autenticados a una ruta inexistente, pérdida del origen del consentimiento en contactos captados,
`X-Frame-Options` no retirado al permitir inserción, y riesgo de arrastre de contexto entre peticiones
(modo *worker*), resuelto reiniciando contexto y cachés al inicio de cada petición.

## 4. Riesgos residuales y pendientes (fuera del alcance de esta entrega)

| Riesgo | Mitigación actual | Acción recomendada |
|---|---|---|
| Correo saliente no configurado | Invitaciones quedan creadas; en desarrollo el enlace se muestra/registra; en producción se informa que no se envió | Configurar SMTP/Microsoft Graph (`email.*`) antes de producción; SPF/DKIM/DMARC |
| MySQL y SQL Server no probados | Esquema con Forge/Query Builder; verificado en PostgreSQL y SQLite | Añadir ambos motores al pipeline de CI |
| Sin pruebas de carga ni DAST externo | — | Ejecutar OWASP ZAP (DAST) y pruebas de carga contra staging antes de salir a producción |
| Pasarelas de pago (Wompi/PayPal) | Pagos se registran manualmente con referencia idempotente | Implementar `BillingProviderInterface` con webhooks firmados (Fase 5) |
| Bloqueo por IP detrás de proxy | `proxyIPs` vacío ⇒ se usa REMOTE_ADDR (no falsificable) | Al desplegar tras Cloudflare/Nginx, declarar `app.proxyIPs` con los rangos del proxy |
| Retención y derechos del titular (Ley 1581) | Consentimiento trazado; exportación CSV | Procedimiento de supresión/rectificación y política de retención (revisión jurídica) |
| Gestión de secretos | `encryption.key` en `.env` fuera del repositorio | Gestor de secretos y rotación de claves |
