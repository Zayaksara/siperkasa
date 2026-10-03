# SiPerkasa

Laravel + Inertia/Vue.

## Deploy notes

- PHP >= 8.4.
- PHP upload limits must allow the 20 MB BMN import: `upload_max_filesize >= 20M`, `post_max_size >= 25M`. `public/.user.ini` sets 25M/30M for PHP-FPM/CGI; for mod_php or nginx set the same in `php.ini` / `client_max_body_size`.
- Asset import runs on the queue. Keep a worker running: `php artisan queue:work --tries=1 --timeout=900`.
- `DB_QUEUE_RETRY_AFTER` (default 960) must be greater than the job timeout (900).
- User import is synchronous and limited to 100 rows per file.
