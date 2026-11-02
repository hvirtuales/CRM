# DESIGN.md — HV CRM

Sistema de diseño de referencia: **design-system-builder** (interfaz neutra y ejecutiva, un solo acento,
modo oscuro automático, controles compactos tipo Fluent). Acento corporativo: **#D80023**; cada
distribuidor puede cambiarlo por token (marca blanca).

## Port al stack del proyecto

El sistema de referencia es React + Tailwind 4; la especificación prohíbe React/Node. Se reutiliza el
**diseño**, no el código:

| Referencia (React) | HV CRM (PHP + Tailwind 4 CLI autónomo) |
|---|---|
| `theme.css` (`@theme`, `--app-*`) | `resources/css/app.css` — mismos tokens, mismos valores claro/oscuro |
| `brand.ts` (`applyBrand`) | `App\Libraries\BrandService` — mismo cálculo WCAG (`--brand`, `--brand-ink`, `--brand-dark`, `--brand-ink-dark`) en servidor |
| `Button`, `Input`, `Select`, `Card`, `Kpi`, `Badge`, `Table`, `Empty`, `Pagination` | clases `.btn*`, `.input`, `.select`, `.card*`, `.kpi*`, `status_badge()`, `.table*`, `.empty`, `partials/pagination` |
| `Field`, `Toggle` | `partials/field.php`, `partials/toggle.php` (role="switch") |
| `Drawer`, `Modal`, `Confirm`, `Toast` | `<dialog>` nativo (`partials/drawer_start/_end`, `partials/confirm`, `partials/toasts`) |
| `PageHeader`, Sidebar, Header | `partials/page_header.php`, `layouts/app.php` |
| lucide-react | sprite `public/assets/img/icons.svg` (Lucide 0.468.0, ISC) + helper `icon()` |

## Extensiones registradas

## 2026-11-02 · Drawer/Modal sobre `<dialog>`
- Tipo: componente
- Qué: `dialog.drawer` (max-w 28 rem; `.drawer-wide` 42 rem) y `dialog.modal`, velo `rgb(0 0 0 / .3)`, sin animación de entrada.
- Por qué: sin React; `<dialog>` aporta foco atrapado, Esc y `aria-modal` nativos.
- Dónde se usa: todas las altas/ediciones. Abrir al cargar con `data-open-on-load` (p. ej. `?edit=ID` o errores de validación).

## 2026-11-02 · `.form-section`
- Tipo: patrón
- Qué: bloque con borde `rounded-xl border p-4`, `legend.form-section-title`, `.form-section-hint`.
- Por qué: exigido por el contrato de componentes (formularios agrupados por decisión).

## 2026-11-02 · Kanban de oportunidades
- Tipo: componente
- Qué: `.kanban` (columnas `minmax(16rem,1fr)`, scroll horizontal), `.kanban-col` (`bg-muted`), `.kanban-card` (`bg-surface border rounded-xl`), arrastre HTML5 + teclado `Alt+←/→`.
- Por qué: gestión comercial por etapas; no existía en la referencia.
- Dónde: `components/Crm/Views/opportunities/index.php`, `components/Crm/assets/js/kanban.js`.

## 2026-11-02 · Excepción: vistas públicas autosuficientes
- Tipo: excepción
- Qué: formulario público y cotización pública usan variables propias (`--hf-*`, `--hq-*`) con hex por defecto, estilos y SVG en línea.
- Por qué: contrato de componentes (deben renderizarse llevándose solo la carpeta) y embebido en sitios de terceros.

## Verificación
- `audit_ui.py` sobre las vistas (copiadas a .html): 0 errores en pantallas de la aplicación. Hallazgos
  restantes = excepciones anteriores, muestras de color de marca (datos) y páginas de error de CI4.
  Las advertencias W01 son falsos positivos: `.input`/`.select` ya fijan `h-9`.
- Capturas de los tres espacios en claro/oscuro y móvil revisadas con Playwright.
