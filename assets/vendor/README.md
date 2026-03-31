# Vendor Assets

Si queres operar Pet Match sin depender de CDNs, coloca estas librerias en las rutas esperadas:

- `assets/vendor/leaflet/leaflet.css`
- `assets/vendor/leaflet/leaflet.js`
- `assets/vendor/swiper/swiper-bundle.min.css`
- `assets/vendor/swiper/swiper-bundle.min.js`

Con `Ajustes > Entrega de librerias externas` en modo `Auto`, el plugin usa estas copias locales si existen y cae a CDN si faltan.
Con modo `Local`, intenta usarlas primero y registra un warning si no estan disponibles.
