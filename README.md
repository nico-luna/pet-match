# Pet Match

Plugin de WordPress para publicar, buscar y administrar casos de mascotas perdidas, encontradas y en adopcion.

## Que hace

`Pet Match` implementa un MVP funcional con estas piezas principales:

- Publicacion de casos desde frontend mediante shortcode.
- Busqueda de casos por texto, tipo, estado y cercania.
- Fichas individuales con galeria, metadatos y mapa aproximado.
- Reporte de avistajes y contacto por WhatsApp.
- Alertas por email para nuevos casos coincidentes.
- Panel administrativo propio para casos, avistajes, alertas, refugios, ajustes y logs.

## Estructura del proyecto

- [`pet-match.php`](/C:/Users/Nico/Desktop/pet-match/pet-match.php): bootstrap principal del plugin.
- [`includes/class-pm-pet-match.php`](/C:/Users/Nico/Desktop/pet-match/includes/class-pm-pet-match.php): clase principal.
- [`includes/trait-pm-pet-match-core.php`](/C:/Users/Nico/Desktop/pet-match/includes/trait-pm-pet-match-core.php): helpers, settings, estados, validaciones y versionado.
- [`includes/trait-pm-pet-match-frontend.php`](/C:/Users/Nico/Desktop/pet-match/includes/trait-pm-pet-match-frontend.php): shortcodes y vistas frontend.
- [`includes/trait-pm-pet-match-forms.php`](/C:/Users/Nico/Desktop/pet-match/includes/trait-pm-pet-match-forms.php): handlers de formularios, single y alertas.
- [`includes/trait-pm-pet-match-admin.php`](/C:/Users/Nico/Desktop/pet-match/includes/trait-pm-pet-match-admin.php): panel administrativo.
- [`assets/css/pm-style.css`](/C:/Users/Nico/Desktop/pet-match/assets/css/pm-style.css): estilos frontend.
- [`assets/css/pm-admin.css`](/C:/Users/Nico/Desktop/pet-match/assets/css/pm-admin.css): estilos admin.
- [`assets/js/pm-map.js`](/C:/Users/Nico/Desktop/pet-match/assets/js/pm-map.js): mapa Leaflet para alta y single.
- [`assets/js/pm-search-map.js`](/C:/Users/Nico/Desktop/pet-match/assets/js/pm-search-map.js): mapa de busqueda.
- [`assets/js/pm-ui.js`](/C:/Users/Nico/Desktop/pet-match/assets/js/pm-ui.js): interacciones de frontend.

## Requisitos

- PHP 7.4 o superior.
- WordPress 6.x.
- Permisos de escritura en `wp-content/uploads` para logs y medios.
- Acceso saliente si se usan assets externos:
  - `unpkg.com` para Leaflet.
  - `cdn.jsdelivr.net` para Swiper.
  - `fonts.googleapis.com` para Montserrat.

## Instalacion

1. Copiar la carpeta `pet-match` dentro de `wp-content/plugins/`.
2. Activar el plugin desde `Plugins` en WordPress.
3. Verificar que WordPress pueda crear:
   - medios subidos por usuarios,
   - logs en `wp-content/uploads/pet-match/pet-match.log`.
4. Crear o validar terminos de taxonomias:
   - `pm_case_type`
   - `pm_species`
   - `pm_zone`
5. Crear las paginas de frontend y asignarles shortcodes.

## Shortcodes disponibles

### Inicio

```text
[pm_home_cards]
```

Parametros utiles:

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

Parametros:

```text
[pm_search show_types="1" limit="24"]
```

### Slider de casos

```text
[pm_cases_slider type="lost" title="Ultimos perdidos" limit="12"]
[pm_cases_slider type="adoption" title="En adopcion" limit="12"]
```

### Metricas

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

1. Crear pagina `Inicio` con `[pm_home_cards]`.
2. Crear pagina `Buscar` con `[pm_search]`.
3. Crear pagina `Publicar` con `[pm_create_case]` o versiones por modo.
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
- Taxonomias:
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

## Estados de casos

Modelo canonico actual:

- `open`: caso activo.
- `in_contact`: hubo contacto o seguimiento.
- `resolved`: caso resuelto.
- `closed`: caso cerrado operativamente.

Compatibilidad legacy:

- `contact` se interpreta como `in_contact`.
- un estado vacio o invalido se trata como `open`.

## Panel administrativo

El menu `Pet Match` expone:

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

## Mantenimiento documental

Estos archivos deben mantenerse actualizados en cada cambio relevante:

- [`README.md`](/C:/Users/Nico/Desktop/pet-match/README.md)
- [`ROADMAP.md`](/C:/Users/Nico/Desktop/pet-match/ROADMAP.md)
- [`CHANGELOG.md`](/C:/Users/Nico/Desktop/pet-match/CHANGELOG.md)

## Checklist de QA end-to-end

### 1. Publicacion anonima vs login requerido

Objetivo:
Validar que el ajuste `require_login_create` impacte de verdad en frontend y backend.

Precondiciones:
- Existe una pagina publica con `[pm_create_case]`.
- Hay al menos un usuario administrador y un usuario comun.

Pasos:
1. Desactivar `Requerir login para publicar casos`.
2. Abrir la pagina publica sin sesion iniciada.
3. Confirmar que el formulario aparece.
4. Activar `Requerir login para publicar casos`.
5. Recargar la pagina sin sesion.
6. Intentar enviar un POST manual al handler sin login.
7. Iniciar sesion y repetir el acceso.

Resultado esperado:
- Con el ajuste apagado, el formulario se ve y el envio funciona sin login.
- Con el ajuste encendido, el formulario queda bloqueado con CTA de login/registro.
- El POST manual sin login es rechazado del lado del servidor.
- Con sesion iniciada, el flujo vuelve a funcionar.

Que revisar si falla:
- `shortcode_create_case()` no consulta el setting.
- `handle_create_case()` no bloquea el POST.
- No se registra el evento `case.create.blocked_login` en logs.

### 2. Creacion de caso desde frontend

Objetivo:
Validar el alta completa de un caso con datos validos e invalidos.

Precondiciones:
- Existen terminos validos en tipo, especie y zona.
- La pagina publica usa `[pm_create_case]`.

Pasos:
1. Crear un caso valido con tipo, especie, zona, fecha, descripcion, mapa e imagen.
2. Repetir con fecha invalida.
3. Repetir con taxonomias manipuladas o inexistentes.
4. Repetir con WhatsApp invalido.
5. Repetir sin imagen valida.

Resultado esperado:
- El caso valido se publica y redirige al single.
- Los casos invalidos muestran mensajes claros y no dejan posts basura.
- `_pm_status` queda en `open` para el caso valido.

Que revisar si falla:
- nonce invalido,
- validacion de taxonomias,
- guardado parcial de metadatos,
- uploads fallidos sin rollback.

### 3. Render e interaccion de mapas en navegador

Objetivo:
Validar mapa de alta, mapa de busqueda y mapa del single.

Precondiciones:
- Leaflet carga correctamente en el sitio.
- Existe al menos un caso con coordenadas validas.

Pasos:
1. Abrir el formulario de alta y mover el pin.
2. Crear un caso con coordenadas validas.
3. Abrir el single del caso.
4. Abrir la pagina de busqueda con `show_map="1"`.
5. Elegir un punto en el mapa de busqueda y cambiar el radio.

Resultado esperado:
- El mapa de alta inicializa una sola vez y guarda `pm_lat` y `pm_lng`.
- El single renderiza `#pm-map-single` solo si hay coordenadas validas.
- El mapa de busqueda permite elegir el centro y refleja el radio activo.

Que revisar si falla:
- `wp_localize_script()` sobreescribe datos entre contextos,
- faltan coordenadas validas,
- se cargan scripts de mapa cuando no corresponden,
- hay errores JS en consola.

### 4. Matching de alertas y envio de emails

Objetivo:
Validar que las alertas se guarden bien y que el matching por tipo, especie y zona sea util.

Precondiciones:
- Existe una pagina con `[pm_create_alert]`.
- WordPress puede enviar emails o hay un SMTP de prueba.

Pasos:
1. Crear una alerta solo por tipo.
2. Crear otra alerta por tipo + especie.
3. Crear otra alerta por tipo + zona.
4. Publicar casos que matcheen y casos que no matcheen.
5. Revisar mails recibidos y logs.

Resultado esperado:
- Solo reciben email las alertas que realmente coinciden.
- El asunto y el cuerpo incluyen resumen util y link al caso.
- Los logs muestran cuantas alertas se evaluaron, matchearon y se descartaron.

Que revisar si falla:
- `notify_alerts()` no usa el contexto correcto,
- el tipo no se normaliza,
- la infraestructura de correo falla aunque el matching sea correcto,
- faltan logs `alert.notify.batch`.

### 5. Edicion admin con datos legacy

Objetivo:
Confirmar que admin siga funcionando con casos viejos o incompletos.

Precondiciones:
- Existe al menos un caso legacy con alguno de estos problemas:
  - `_pm_status = contact`,
  - sin coordenadas,
  - sin taxonomias,
  - sin titulo,
  - sin imagenes.

Pasos:
1. Abrir el caso legacy desde `Pet Match > Casos`.
2. Editar estado, fecha y coordenadas.
3. Guardar sin completar taxonomias faltantes.
4. Revisar listado admin, single y busqueda.

Resultado esperado:
- Admin no rompe ni muestra notices por metadatos faltantes.
- El estado legacy `contact` se ve como `in_contact`.
- Si faltan taxonomias o titulo, aparecen fallbacks legibles.

Que revisar si falla:
- acceso directo a indices o metas inexistentes,
- `get_the_terms()` sin fallback,
- titulos vacios o links rotos,
- coordenadas invalidas persistidas tras guardar.

### 6. Busqueda y visualizacion de casos

Objetivo:
Validar consistencia entre filtros, cards y single.

Precondiciones:
- Existen casos en `open`, `in_contact`, `resolved` y `closed`.

Pasos:
1. Buscar por texto.
2. Filtrar por tipo.
3. Filtrar por estado.
4. Combinar texto + tipo + estado.
5. Probar un caso legacy con estado `contact`.

Resultado esperado:
- Los resultados coinciden con los filtros elegidos.
- Los badges del listado y del single muestran el mismo estado.
- Un caso legacy `contact` aparece como `En contacto`.

Que revisar si falla:
- `meta_query` inconsistente,
- labels distintos entre busqueda y single,
- normalizacion legacy incompleta.

### 7. Validacion de mensajes visibles corregidos

Objetivo:
Confirmar que ya no queden textos rotos ni mensajes pobres en los flujos principales.

Precondiciones:
- Plugin activo en un WordPress real.

Pasos:
1. Recorrer home cards, formulario de alta, busqueda, single y admin.
2. Forzar validaciones fallidas en formularios.
3. Revisar estados vacios en casos, alertas, avistajes y refugios.

Resultado esperado:
- Los textos se ven en espanol legible.
- No hay mojibake ni caracteres rotos.
- Los mensajes de error y exito son claros y operativos.

Que revisar si falla:
- archivos guardados con encoding incorrecto,
- strings heredados sin limpiar,
- copy duplicado con variantes inconsistentes.

### 8. Revision rapida de logs

Objetivo:
Comprobar que los logs operativos sirvan para soporte real.

Precondiciones:
- Logging habilitado.
- Acceso a `Pet Match > Logs` o al archivo en uploads.

Pasos:
1. Crear un caso valido.
2. Intentar un alta bloqueada por login requerido.
3. Forzar un upload invalido.
4. Generar una alerta y un avistaje.
5. Revisar el tail de logs.

Resultado esperado:
- Se registran eventos clave sin exponer datos sensibles innecesarios.
- El contexto incluye `event`, `user_id`, `request_method` y `request_path` cuando aplica.
- El archivo de logs sigue siendo legible.

Que revisar si falla:
- ausencia de logs esperados,
- spam excesivo,
- datos sensibles completos en el contexto,
- problemas de permisos sobre `uploads/pet-match/`.