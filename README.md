# Pet Match

Plugin de WordPress para publicar, buscar y administrar casos de mascotas perdidas, encontradas y en adopciÃ³n.

## QuÃ© hace

`Pet Match` implementa un MVP con estas piezas principales:

- PublicaciÃ³n de casos desde frontend mediante shortcode.
- BÃºsqueda de casos por texto, tipo y estado.
- Fichas individuales con galerÃ­a, metadatos y mapa aproximado.
- Reporte de avistajes y contacto por WhatsApp.
- Alertas por email para nuevos casos coincidentes.
- Panel administrativo propio para casos, avistajes, alertas, refugios, ajustes y logs.

## Estructura del proyecto

- [`pet-match.php`](/C:/Users/Nico/Desktop/pet-match/pet-match.php): plugin principal, shortcodes, CPTs, taxonomÃ­as, admin y lÃ³gica de negocio.
- [`assets/css/pm-style.css`](/C:/Users/Nico/Desktop/pet-match/assets/css/pm-style.css): estilos frontend.
- [`assets/css/pm-admin.css`](/C:/Users/Nico/Desktop/pet-match/assets/css/pm-admin.css): estilos de administraciÃ³n.
- [`assets/js/pm-map.js`](/C:/Users/Nico/Desktop/pet-match/assets/js/pm-map.js): mapa Leaflet para alta y vista individual.
- [`assets/js/pm-search-map.js`](/C:/Users/Nico/Desktop/pet-match/assets/js/pm-search-map.js): mapa para resultados de bÃºsqueda.
- [`assets/js/pm-ui.js`](/C:/Users/Nico/Desktop/pet-match/assets/js/pm-ui.js): toggles, slider y CTA de WhatsApp.

## Requisitos

- PHP 7.4 o superior.
- WordPress 6.x.
- Permisos de escritura en `wp-content/uploads` para logs y medios.
- Acceso saliente a:
  - `unpkg.com` para Leaflet.
  - `cdn.jsdelivr.net` para Swiper.
  - `fonts.googleapis.com` para Montserrat.

## InstalaciÃ³n

1. Copiar la carpeta `pet-match` dentro de `wp-content/plugins/`.
2. Activar el plugin desde `Plugins` en WordPress.
3. Verificar que WordPress pueda crear:
   - medios subidos por usuarios,
   - logs en `wp-content/uploads/pet-match/pet-match.log`.
4. Crear o validar tÃ©rminos de taxonomÃ­as:
   - `pm_case_type`
   - `pm_species`
   - `pm_zone`
5. Crear las pÃ¡ginas de frontend y asignarles shortcodes.

## Shortcodes disponibles

### Inicio

```text
[pm_home_cards]
```

ParÃ¡metros Ãºtiles:

```text
[pm_home_cards search_url="/buscar" publish_url="/publicar"]
```

### Alta de caso

```text
[pm_create_case]
```

Modo fijo:

```text
[pm_create_case mode="lost" hide_type="1"]
[pm_create_case mode="found" hide_type="1"]
[pm_create_case mode="adoption" hide_type="1"]
```

### Buscador

```text
[pm_search]
```

ParÃ¡metros:

```text
[pm_search show_types="1" limit="24"]
```

### Slider de casos

```text
[pm_cases_slider type="lost" title="Ãšltimos perdidos" limit="12"]
[pm_cases_slider type="adoption" title="En adopciÃ³n" limit="12"]
```

### MÃ©tricas

```text
[pm_metrics]
```

### Alertas

```text
[pm_create_alert type="lost"]
[pm_create_alert type="adoption"]
```

### Reporte de avistaje

Normalmente se usa embebido dentro de la ficha de un caso:

```text
[pm_report_sighting case_id="123"]
```

## Flujo de uso recomendado

1. Crear pÃ¡gina `Inicio` con `[pm_home_cards]`.
2. Crear pÃ¡gina `Buscar` con `[pm_search]`.
3. Crear pÃ¡gina `Publicar` con `[pm_create_case]` o versiones por modo.
4. Publicar algunos casos de prueba.
5. Revisar el panel `Pet Match` en admin para validar:
   - casos,
   - avistajes,
   - alertas,
   - refugios,
   - logs.

## Modelo funcional resumido

- CPTs:
  - `pet_case`
  - `pm_alert`
  - `pm_sighting`
- TaxonomÃ­as:
  - `pm_case_type`
  - `pm_species`
  - `pm_zone`
- Metas frecuentes:
  - `_pm_status`
  - `_pm_lat`
  - `_pm_lng`
  - `_pm_date`
  - `_pm_images`
  - `_pm_whatsapp`

## Panel administrativo

El menÃº `Pet Match` expone:

- Dashboard
- Casos
- Avistajes
- Alertas
- Refugios
- Ajustes
- Logs

## Logs

El logger escribe por defecto en:

```text
wp-content/uploads/pet-match/pet-match.log
```

Sirve para revisar errores fatales y eventos operativos basicos.

## Observaciones actuales

Estado de la revision realizada sobre este repositorio:

- `pet-match.php` no presenta errores de sintaxis PHP.
- Los archivos JS revisados no presentan errores de sintaxis.
- Se corrigieron inconsistencias puntuales en:
  - filtros del buscador,
  - callbacks de registro de refugios,
  - consistencia del meta `_pm_status` en acciones admin,
  - registro del submenu `Refugios`.

Persisten puntos a validar en un entorno WordPress real:

- cobertura de pruebas funcionales end-to-end,
- comportamiento de notificaciones por email,
- flujo real de publicacion con usuarios no autenticados,
- consistencia visual de algunos bloques en temas/constructores distintos.

## Mantenimiento documental

Estos archivos deben mantenerse actualizados en cada cambio relevante:

- [`README.md`](/C:/Users/Nico/Desktop/pet-match/README.md)
- [`ROADMAP.md`](/C:/Users/Nico/Desktop/pet-match/ROADMAP.md)
- [`CHANGELOG.md`](/C:/Users/Nico/Desktop/pet-match/CHANGELOG.md)

## Checklist manual de QA

- Publicacion de caso:
  - crear un caso con foto, tipo, especie, zona, fecha y mapa,
  - confirmar que redirige al single del caso,
  - confirmar que guarda `_pm_status` en `open`.
- Login requerido:
  - activar `Requerir login para publicar casos`,
  - abrir la pagina publica sin login y validar que no muestra el formulario,
  - intentar enviar un POST manual sin login y validar que el backend lo rechaza.
- Mapa:
  - validar que el mapa del formulario carga,
  - mover el pin y confirmar actualizacion de `pm_lat` y `pm_lng`,
  - abrir un single con coordenadas y validar que `#pm-map-single` renderiza.
- Estados:
  - crear un caso y confirmar estado inicial `open`,
  - marcarlo como `in_contact` y validar badge + filtros,
  - editarlo en admin a `closed` y validar consistencia visual.
- Admin:
  - editar un caso desde `Pet Match > Casos`,
  - guardar cambios y confirmar que estado, fecha y coordenadas persisten,
  - revisar que `Refugios` siga visible y funcional.
- Busqueda:
  - filtrar por `Activos`, `Resueltos` y `Cerrados`,
  - validar que un caso legado con estado `contact` aparezca como activo,
  - validar combinacion de busqueda por texto + tipo + estado.

