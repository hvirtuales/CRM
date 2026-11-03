# ADR 0004 — Automatización comercial (Bloque 1)

- **Estado:** aceptada · 2026-11-03
- **Contexto:** análisis comercial sobre la v1.0. Procesos que dependían de la memoria del equipo:
  todos los leads caían en el propietario, nadie avisaba de seguimientos vencidos, la aceptación de una
  cotización no cerraba la oportunidad, el ciclo de suscripciones no generaba gestión comercial y las
  comisiones se aprobaban una por una.

## Decisión

| # | Automatización | Dónde | Regla |
|---|---|---|---|
| 1 | Reparto de leads **por proyecto** | `crm` · `AssignmentService` | Equipo por proyecto; recibe quien lleva más turnos sin recibir (turno monotónico). Sin equipo ⇒ rotativo entre asesores y gerentes comerciales; sin ellos ⇒ propietario. Integrantes suspendidos se omiten. |
| 2 | Seguimiento con escalamiento | `crm` · `FollowUpService`, `/crm/seguimiento` | Acciones vencidas y oportunidades sin gestión humana en N días (por defecto 7) para su responsable; escaladas a quien tiene `crm.manage` tras M días (14). Calculado en línea, siempre actual. |
| 3 | Cierre y vencimiento de cotizaciones | `quote` · `QuoteService` | Aceptada ⇒ oportunidad ganada con el total cotizado. Tarea diaria: recordatorio en la oportunidad 3 días antes de vencer (una vez) y estado `expired` al vencer. |
| 4 | Renovación, fin de prueba y ampliación | núcleo · `CommercialAutomation` | Negocios **directos**: oportunidades en el CRM de Herramientas Virtuales (proyecto «Suscripciones HV CRM»), una por señal (`commercial_signals`). Negocios de **distribuidor**: las señales aparecen en su panel de cartera y no entran al CRM de HV (la relación comercial es del distribuidor). |
| 5 | Comisiones | núcleo · `CommissionService` | Pago registrado ⇒ comisión aprobada. Al cerrar el mes, extracto por distribuidor y moneda; la plataforma lo marca pagado con su referencia; el distribuidor descarga su extracto CSV. |

La tarea diaria `php spark hv:lifecycle` ejecuta 4 y 5 y emite el evento `hv.daily`, al que se suscriben
los componentes (3), sin que el núcleo conozca sus tablas.

## Consecuencias

- Los datos del propietario de un negocio directo (nombre y correo) se registran en el CRM de HV como
  contacto de su relación contractual; no se usan para marketing (sin consentimiento marcado).
- Un aviso del sistema no reinicia el reloj de inactividad; solo la gestión humana.
- Se detectó y corrigió que la caché de columnas del driver podía omitir columnas de plan en una
  instalación nueva: las migraciones limpian la caché y `hv:install` verifica las columnas declaradas.
