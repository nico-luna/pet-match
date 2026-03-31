# Changelog

Este archivo debe registrar cambios funcionales, técnicos y documentales del proyecto.

## 2026-03-31

### Documentación

- Se reemplazó el `README` mínimo por documentación operativa completa.
- Se agregó `ROADMAP.md` para seguimiento de evolución y prioridades.
- Se agregó este `CHANGELOG.md` para registrar modificaciones futuras.

### Revisión técnica

- Se revisó la estructura general del plugin y sus assets principales.
- Se verificó que `pet-match.php` no tiene errores de sintaxis PHP.
- Se verificó sintaxis de:
  - `assets/js/pm-ui.js`
  - `assets/js/pm-map.js`
  - `assets/js/pm-search-map.js`

### Correcciones realizadas

- Se corrigió un bug en el buscador donde se usaba una variable no definida al construir filtros por estado.
- Se agregó el submenú admin `Refugios`, alineado con la pantalla y los redirects ya existentes.
- Se implementaron los callbacks de registro de refugios:
  - `render_register_shelter_field`
  - `validate_register_shelter_field`
  - `save_register_shelter_field`
- Se corrigió la inconsistencia entre `pm_case_status` y `_pm_status` en acciones admin de resolver/desresolver casos.

### Pendientes detectados

- Modularizar `pet-match.php`.
- Validar el plugin en un WordPress real con pruebas funcionales.
- Revisar opciones de configuración aún no conectadas completamente al flujo de negocio.

### Sprint de estabilización

- Se alineó la versión del header del plugin con `PM_Pet_Match::VERSION` en `0.5.3.21`.
- Se aplicó `require_login_create` al flujo real de publicación:
  - bloqueo visual del formulario para usuarios no autenticados,
  - bloqueo backend en `handle_create_case()`.
- Se centralizó la normalización de estados con helpers internos:
  - `open`
  - `resolved`
  - `closed`
  - `contact` queda tratado como alias legado de `open`.
- Se unificó el uso de `_pm_status` en creación, single, admin y acciones operativas.
- Se evitó notificar alertas al guardar casos no activos.
- Se corrigió la integración del mapa en single para usar `pm-map` y no `pm-search-map`.
- Se ajustó CSS para asegurar altura válida en `#pm-map-single`.
- Se corrigieron varios textos visibles con encoding roto en PHP y JS.
