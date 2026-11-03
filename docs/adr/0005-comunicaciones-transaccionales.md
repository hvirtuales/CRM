# ADR 0005 — Comunicaciones transaccionales y autoservicio (Bloque 2)

- **Estado:** aceptada · 2026-11-03

## Decisión

Una **bandeja de salida** del núcleo (`outbox`, `NotificationService`) centraliza todo correo:

- Idempotente por `dedupe_key` (un reintento del proceso nunca duplica un envío); reintentos con
  espera exponencial (máx. 6) y estado visible en *Plataforma → Correos enviados*.
- Sin correo configurado los mensajes **esperan en cola** y salen al configurarlo (`php spark hv:outbox`
  cada 5 minutos; la tarea diaria también procesa la cola).
- Plantilla única con estilos en línea, sin recursos externos ni rastreadores; todo dato se escapa.
- Los mensajes con enlaces de un solo uso (invitación, verificación) se borran del almacenamiento al
  enviarse y se retiran de la cola si el enlace se usó antes.
- Solo mensajes **transaccionales**: no requieren autorización de marketing (Ley 1581). Las campañas
  siguen fuera de alcance (Fase 3).

| # | Automatización | Regla |
|---|---|---|
| 6 | Acuse inmediato al lead (`form`) | Activable por formulario; máximo **uno por formulario, destinatario y día** (evita usar el formulario para inundar correos de terceros). |
| 7 | Cobranza escalonada (núcleo, tarea diaria) | Renovación (7 días antes), mora, último aviso (≤ 1 día de gracia) y suspensión; una vez por etapa y periodo; al negocio y a su distribuidor. Sin datos de pago sensibles. |
| 8 | Cotización por correo (`quote`) | Al marcarla enviada, correo al contacto con el enlace público; recordatorio 3 días antes de vencer. |
| 9 | Autoservicio «Pruébelo gratis» (`/registro`) | Solo planes públicos con prueba; verificación de correo (24 h, un uso); una prueba por correo; honeypot, 3 registros/hora por IP y máx. 3 correos de verificación por destinatario/día; al verificar crea el negocio directo en prueba, registra el prospecto en el CRM de HV y continúa a definir la contraseña. Desactivable con `HV_SELF_SIGNUP=false`. |

## Pendiente

WhatsApp Business (plantillas aprobadas por Meta) como segundo canal de los mismos avisos.
