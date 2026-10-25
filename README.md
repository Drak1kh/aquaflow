# AquaFlow

AquaFlow is a water management and billing system with separate Super Admin, water provider, and consumer workspaces. Accounts are created by administrators; public registration is disabled.

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

Seed local demo accounts using the command below, then sign in at the local application URL. The development command starts the PHP server, queue listener, log viewer, and Vite in one terminal. Stop it with `Ctrl+C`.

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

## Local demo accounts

Run `php artisan db:seed --no-interaction` after migrating. In local and testing environments, this creates the following verified accounts and two separate providers:

| Role | Email |
| --- | --- |
| Super Admin | `admin@aquaflow.test` |
| North provider manager | `provider.north@aquaflow.test` |
| South provider manager | `provider.south@aquaflow.test` |
| North consumer | `consumer.north@aquaflow.test` |
| South consumer | `consumer.south@aquaflow.test` |

The seeder prints one generated password for all five accounts. To choose a password, set `AQUAFLOW_DEMO_PASSWORD` in `.env` before seeding. Running the seeder again keeps the same demo records and resets their passwords to the newly printed or configured value. Demo accounts are never seeded in production.

For an additional sample meter reading and bill, run `php artisan db:seed --class=AquaFlowDemoSeeder --no-interaction`.

If the page reports a missing Vite manifest, run `npm run build` or start `composer dev`. If SQLite fails to connect, confirm that `database/database.sqlite` exists and that PHP has `pdo_sqlite` enabled.
