# Internacionalizacion

Pet Match usa el text domain `pet-match`.

Para generar el archivo POT con WP-CLI:

```bash
wp i18n make-pot . languages/pet-match.pot --slug=pet-match --domain=pet-match --exclude=node_modules,vendor,.git
```

Pautas:

- Mantener `Text Domain: pet-match` en el header del plugin.
- Mantener `Domain Path: /languages`.
- Usar funciones nativas de WordPress como `__()`, `_e()`, `esc_html__()` y `esc_attr__()`.
- Evitar concatenar strings traducibles cuando se pueda resolver con placeholders.
