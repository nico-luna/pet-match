# Roadmap

Última actualización: 2026-03-31

Este archivo debe reflejar el punto de partida del proyecto, el estado actual y las próximas mejoras previstas.

## Estado actual

- Plugin WordPress MVP funcional para casos de mascotas perdidas, encontradas y en adopción.
- Publicación frontend con carga de imágenes, ubicación aproximada y datos básicos.
- Buscador frontend con filtro por tipo, estado y texto.
- Vista individual con metadatos, galería, mapa y CTA de WhatsApp.
- Admin propio para casos, avistajes, alertas, ajustes, refugios y logs.
- Alertas por email implementadas de forma básica.

## Punto de partida confirmado

- Base del proyecto centralizada en un único archivo PHP grande.
- Sin suite de tests automatizados.
- Sin proceso formal de release/versionado más allá del header del plugin.
- Dependencia de CDNs externas para Leaflet, Swiper y Google Fonts.
- Documentación inicial incompleta al momento de esta revisión.

## Próximas prioridades

### Alta prioridad

- Separar lógica de frontend, admin y dominio en archivos/clases independientes.
- Agregar validaciones funcionales más estrictas para formularios y uploads.
- Probar en WordPress real el flujo completo:
  - alta de caso,
  - búsqueda,
  - alertas,
  - avistajes,
  - resolución de casos.
- Unificar criterios de estado y metadatos para evitar regresiones futuras.

### Prioridad media

- Agregar tests automáticos para la lógica más sensible.
- Mejorar sanitización y validación de datos en todos los formularios frontend.
- Incorporar búsqueda geográfica real por radio usando lat/lng.
- Mejorar el panel de refugios con filtros y acciones más completas.
- Hacer configurable el uso de activos remotos o autoalojados.

### Prioridad baja

- Internacionalización completa.
- Mejoras visuales para galería, tarjetas y estados vacíos.
- Métricas más detalladas en dashboard.
- Exportación de alertas, avistajes y casos.

## Hallazgos abiertos

- Existen señales de crecimiento orgánico del archivo principal y conviene modularizar.
- Hay opciones configurables aún no integradas del todo al flujo operativo.
- Falta una validación integral en entorno WordPress con datos reales.

## Criterio de actualización

Actualizar este archivo cuando cambie alguno de estos puntos:

- alcance funcional,
- prioridades,
- deuda técnica principal,
- hitos ya completados,
- riesgos detectados.
