# ADR 0002 — Esquema portable + PostgreSQL RLS opcional

- **Estado:** aceptada · 2026-11-02
- **Contexto:** contradicción entre la especificación (PostgreSQL, JSONB, RLS) y el contrato de
  componentes (portabilidad MySQL/PostgreSQL/SQL Server, sin JSON nativo ni SQL crudo). Decisión
  del cliente: **portable + RLS opcional**.

## Decisión

- Esquema con Forge/Query Builder: sin ENUM ni JSON nativo; JSON en `TEXT` validado en PHP; dinero
  `DECIMAL(15,2)`, tasas `DECIMAL(7,4)`; PK `INT` + `public_id` opaco (130 bits) para URLs públicas.
- **RLS** (`HV_RLS=true` y driver Postgre): `App\Libraries\Rls` crea la política
  `hv_company_isolation` en toda tabla `cmp_*` con `company_id` (ENABLE + FORCE). La conexión fija
  `app.company_id` al resolver el contexto; sin negocio fijado no se ve ninguna fila. Procesos de
  plataforma/CLI activan `app.rls_bypass` explícitamente. Única excepción documentada al «sin SQL crudo».
- Las tablas del núcleo no llevan RLS (acceso por servicios con filtros explícitos).

## Consecuencias

- Pruebas en PostgreSQL (con RLS) y SQLite; MySQL/SQL Server pendientes de validar en CI (§ riesgos).
- Si una consulta de componente olvida el filtro de negocio, RLS lo contiene (verificado en pruebas).
