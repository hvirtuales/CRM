# ADR 0001 — Monolito modular con tres espacios de autorización

- **Estado:** aceptada · 2026-11-02
- **Contexto:** la especificación (CRM_HV_MASTER §2, §5) exige SaaS multiempresa con espacios
  Plataforma / Distribuidor / Negocio, PHP + CodeIgniter 4, sin React/Node/Python, y un sistema de
  componentes instalables. El repositorio estaba vacío y el contrato `componentes-ci4` presupone un
  núcleo «cuentas» que no existía.

## Decisión

1. **Núcleo propio en este repositorio** que implementa la superficie del contrato `componentes-ci4`
   (`components/*/Manifest.php`, filtros `auth` y `permission:`, `apiauth`, tablas `users`,
   `companies`, `roles`, `plans`, sesión `active_company_id`) **y** el modelo de tres espacios.
2. **Negocio = `companies`**, Distribuidor = `tenants`. Negocio directo ⇒ `companies.tenant_id = NULL`
   (esquema único, §2.1).
3. **Membresías por ámbito** (`memberships.scope ∈ {platform, tenant, company}`): una persona puede
   tener varias sin mezclar datos. El ámbito activo se recalcula en cada petición desde la membresía;
   nunca se confía en ids del navegador (§2.5).
4. **Autorización en capas** (`Context::can`): permiso explícito del rol → componente habilitado →
   plan del negocio. Reglas de pertenencia/propiedad (ABAC) en los servicios. Denegar por defecto.
5. **El superadministrador no ve datos de negocio**: no tiene permisos de componentes. El soporte
   entra solo con un acceso **aprobado por el negocio**, temporal (≤ 8 h), de solo lectura y auditado.
6. **CodeIgniter Shield no se adopta** por ahora: sus grupos/permisos son globales y no modelan
   permisos por ámbito/organización. Autenticación propia con Argon2id, bloqueo por intentos, MFA
   TOTP, versión de sesión (revocación) y política NIST 800-63B. Revisable en un ADR posterior.

## Consecuencias

- Las rutas de negocio no aceptan `company_id` del cliente; servicios reciben el id del contexto.
- Cambiar de espacio regenera la sesión y queda auditado.
- La suscripción suspendida/cancelada deja el negocio en **solo lectura** (consulta y exportación).
