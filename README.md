<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Database and file storage

The application selects its database with `DB_CONNECTION` and its file disk with
`FILESYSTEM_DISK`. File uploads, deletes, URLs, and downloads use the configured
default disk; changing providers does not require application-code changes.

### File storage

Storage is independent of the Supabase database. The application uses Laravel's
Flysystem abstraction and `FILESYSTEM_DISK`, so changing between local storage
and an S3-compatible provider does not require code changes.

- Local: `FILESYSTEM_DISK=local`; files are written under `storage/app/public`.
- Amazon S3 or another S3-compatible service: `FILESYSTEM_DISK=s3` and set
  `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, and
  `AWS_BUCKET`. Set `AWS_ENDPOINT` for a custom provider, and
  `AWS_USE_PATH_STYLE_ENDPOINT=true` for providers such as MinIO when required.
  `AWS_URL` is optional and can provide a public/CDN URL.

The S3 Flysystem adapter is already included in Composer dependencies.

### PostgreSQL / Supabase Database

Set `DB_CONNECTION=pgsql`, then take the host and username directly from
Supabase Dashboard > Connect > Session pooler. Supabase's shared Session Pooler
uses port `5432`; port `6543` is Transaction Pooler, not Session Pooler. For a
persistent Laravel backend, use Session Pooler when that is the intended mode.
Set `DB_PORT=5432`, `DB_DATABASE=postgres`, `DB_PASSWORD`, and
`DB_SSLMODE=require`. Alternatively, set `DB_URL` using the connection string
from the dashboard. Do not set `DB_URL` together with individual `DB_*` values.
When using individual variables, quote a password containing `#`, spaces, or
other dotenv syntax characters:

```dotenv
DB_PASSWORD="password-containing-special-characters"
```

When using a PostgreSQL connection URI in `DB_URL`, percent-encode reserved
characters in the username or password. Shared pooler usernames include the
project reference (for example, `postgres.<project-ref>`); use the exact values
shown in the dashboard.

After changing environment settings, run these commands from `backend`:

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate --force
php artisan migrate:status
php artisan test --filter=UploadApiTest
```

`migrate` creates/applies the schema on the configured Supabase database; it
does not copy existing user records from another database. Back up existing
data and plan a separate export/import if records also need to be transferred.
Do not use `migrate:fresh` on a database containing data.

To check that the selected disk can upload, read, and delete an object without
printing its contents:

```powershell
php artisan tinker --execute='$disk = Illuminate\Support\Facades\Storage::disk(config("filesystems.default")); $path = "health-check.txt"; $stored = $disk->put($path, "storage-ok"); if (! $stored || $disk->get($path) !== "storage-ok") { throw new RuntimeException("Storage check failed."); } $disk->delete($path); echo "Storage check passed."'
```

`migrate:status` and `migrate --pretend` verify that Laravel can connect to the
selected database; run `php artisan migrate` only when you are ready to apply
pending schema changes.

## Email and phone verification

Registration requires a unique email address and an international phone number.
Registration and every login create separate, expiring six-digit codes for
email and SMS; the API issues no Sanctum token until both codes are confirmed.
Contact changes are staged and applied only after the new destination's code is
confirmed. Codes are stored as hashes, expire after 10 minutes, and allow at
most five verification attempts.

Configure a production mail transport with `MAIL_MAILER`, `MAIL_HOST`,
`MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS`. Configure
the SMS gateway using:

```dotenv
SMS_API_ENDPOINT=https://your-sms-gateway.example/messages
SMS_API_TOKEN=your-server-side-token
SMS_SENDER=Asatezah
```

The configured SMS endpoint must accept the configured token in its
Authorization header and a JSON body with `to`, `message`, and `sender` fields,
then return a successful HTTP status when the message is accepted. Keep
credentials only in the deployment environment.
The development mailer defaults to Laravel's `log` driver; no SMS is sent until
the gateway settings are present.

Before deploying, inspect and apply the pending migrations against the intended
database:

```powershell
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate --force
```

Do not use `migrate:fresh` on a database with user data.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
