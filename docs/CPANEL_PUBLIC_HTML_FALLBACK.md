# If cPanel Will Not Let You Point the Domain to `/public`

The preferred deployment is:
`app.example.com` -> `/home/USER/scratchgard/public`

If your host forces the main domain to `/home/USER/public_html`, do this instead:

1. Keep the Laravel application outside `public_html`:
   `/home/USER/scratchgard`
2. Copy the CONTENTS of `/home/USER/scratchgard/public/` into the selected public web root.
3. Edit that copied `index.php` and change the two `../` paths so they point to the real Laravel root, for example:

```php
if (file_exists($maintenance = __DIR__.'/../scratchgard/storage/framework/maintenance.php')) {
    require $maintenance;
}
require __DIR__.'/../scratchgard/vendor/autoload.php';
$app = require_once __DIR__.'/../scratchgard/bootstrap/app.php';
```

Actual relative paths depend on your cPanel folder structure.

Do not copy `.env`, `app/`, `config/`, `vendor/`, `storage/`, or database code into a publicly browsable directory.

If possible, ask the hosting provider to change the domain document root instead; it is cleaner and safer.
