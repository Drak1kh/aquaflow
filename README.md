# AquaFlow

AquaFlow is a water management system in early development. This repository currently includes a public preview page, account registration and sign-in, and an authenticated dashboard.

## Requirements

- PHP 8.3 or newer, with the extensions required by Composer and the selected database driver (`pdo_sqlite` for the default SQLite setup)
- Composer 2
- Node.js 22.13 or newer and npm

SQLite is the default database in `.env.example`, so a separate database server is not required.

## Set up locally

Run these commands from the project directory:

```bash
cp .env.example .env
touch database/database.sqlite
composer setup
```

`composer setup` installs PHP and JavaScript dependencies, generates `APP_KEY`, runs the database migrations, and builds the frontend assets. Create the SQLite file before this command so the migrations can connect to it.

Start the development processes:

```bash
composer dev
```

Open [http://localhost:8000](http://localhost:8000) and register an account. The development command starts the PHP server, queue listener, log viewer, and Vite in one terminal. Stop it with `Ctrl+C`.

## Use MySQL instead

Create an empty MySQL database, then set the corresponding values in `.env` **before** running `composer setup`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aquaflow
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Make sure PHP has `pdo_mysql` enabled. The SQLite `touch` command is unnecessary with MySQL. If you switch databases after setup, run `php artisan migrate --no-interaction` against the newly configured database.

## Useful commands

```bash
php artisan migrate --no-interaction  # Apply new database migrations
php artisan test --compact            # Run the test suite
npm run build                         # Rebuild production assets
```

To add the optional local demo account, run `php artisan db:seed --no-interaction`. The current seeder creates `test@example.com` with the password `password`; use that account only for local development.

If the page reports a missing Vite manifest, run `npm run build` or start `composer dev`. If SQLite fails to connect, confirm that `database/database.sqlite` exists and that PHP has `pdo_sqlite` enabled.
