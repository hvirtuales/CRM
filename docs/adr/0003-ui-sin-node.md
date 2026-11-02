# ADR 0003 — Interfaz con el sistema de diseño corporativo sin React ni Node

- **Estado:** aceptada · 2026-11-02
- **Decisión:** portar tokens y componentes del `design-system-builder` a vistas PHP + Tailwind 4
  compilado con el **CLI autónomo** (binario fijado v4.1.11 + SHA-256, `bin/build-css.sh`); JS nativo
  (`public/assets/js/app.js`, sin dependencias); `<dialog>` nativo para Drawer/Modal/Confirm; íconos
  Lucide como sprite SVG propio. CSP estricta `script-src 'self'` (sin scripts en línea).
- **Consecuencias:** la barra de depuración de CI4 queda desactivada (inyecta scripts en línea). El CSS
  compilado se versiona para desplegar sin compilar.
