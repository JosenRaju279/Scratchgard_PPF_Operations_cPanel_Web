# Scratchgard Plugins

Plugins are Super-Admin installed server-side extensions. Each ZIP must contain:

- `plugin.json` with `name`, `slug`, `version`
- `plugin.php` returning a callable: `function ($app, $plugin) { ... }`

Plugins execute PHP code with application privileges. Install only trusted code. See `docs/PLUGIN_DEVELOPER_GUIDE.md`.
