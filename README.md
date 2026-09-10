# Laravel SQL Anywhere

SQL Anywhere 17 database integration for Laravel applications.

This package provides a Laravel database connection, query grammar, schema grammar, and query processor for working with **SAP SQL Anywhere 17** through PHP PDO/ODBC.

It is intended for Laravel applications that need to use SQL Anywhere while continuing to use Laravel's database layer, Query Builder, Eloquent ORM, migrations, and other standard database features.

> **Status:** Early release
> This package provides SQL Anywhere 17 database support for Laravel. It is currently tested with Laravel 12 and Laravel 13 and should be considered an early release while additional SQL Anywhere/Laravel compatibility testing is performed.

---

## Features

The package currently provides:

- SQL Anywhere 17 database connection for Laravel
- PDO/ODBC connection support
- SQL Anywhere query grammar
- SQL Anywhere schema grammar
- SQL Anywhere query processor
- SQL Anywhere-compatible `insertGetId()` handling
- SQL Anywhere-compatible `TOP` / `START AT` pagination syntax
- SQL Anywhere-compatible migration column types
- SQL Anywhere identity/autoincrement support
- SQL Anywhere schema introspection
- Laravel Eloquent and Query Builder integration
- Laravel migration support

The package is designed to minimize application-level changes. Developers should be able to continue using Laravel's normal database APIs instead of writing SQL Anywhere-specific code for every operation.

---

## Requirements

Before installing the package, make sure the environment has:

- PHP 8.2 or later
- Laravel 12.x
- SQL Anywhere 17
- SQL Anywhere 17 ODBC Driver
- PHP PDO
- PHP PDO ODBC extension
- A running SQL Anywhere database server

For Windows installations, the SQL Anywhere ODBC driver must be correctly installed and available to PHP.

You can verify PDO ODBC with:

```bash
php -m
```

Look for:

```text
PDO
pdo_odbc
```

---

## Installation

Install the package using Composer:

```bash
composer require your-vendor/laravel-sqlanywhere
```

Laravel package discovery should automatically register the package service provider.

If package discovery is disabled, register the service provider manually in your Laravel application.

---

## Configuration

Set the default database connection in your `.env` file:

```env
DB_CONNECTION=sqlanywhere
```

Then configure the SQL Anywhere connection in:

```text
config/database.php
```

Example:

```php
'sqlanywhere' => [
    'driver' => 'sqlanywhere',

    'dsn' => env('DB_DSN'),

    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', 2638),

    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),

    'prefix' => '',

    'options' => [],
],
```

Then configure the connection in `.env`:

```env
DB_DSN="odbc:driver=SQL Anywhere 17;eng=ropa17;dbn=ropa17;commlinks=tcpip(host=localhost);CharSet=UTF-8;"

DB_HOST=localhost
DB_PORT=2638
DB_DATABASE=ropa17
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

> The exact DSN depends on your SQL Anywhere server configuration. Replace the example database, engine, username, and password with your own values.

---

## Example SQL Anywhere Connection

A typical SQL Anywhere 17 connection may look like:

```text
ODBC
  ↓
SQL Anywhere 17 Driver
  ↓
Engine: ropa17
  ↓
Database: ROPA17
```

For example:

```env
DB_DSN="odbc:driver=SQL Anywhere 17;eng=ropa17;dbn=ropa17;commlinks=tcpip(host=localhost);CharSet=UTF-8;"
```

---

## Using Laravel's Database API

Once configured, applications can continue using Laravel's normal database APIs.

### Query Builder

```php
use Illuminate\Support\Facades\DB;

$users = DB::table('users')
    ->where('username', 'supadmin')
    ->get();
```

### Eloquent

```php
use App\Models\User;

$user = User::where('username', 'supadmin')->first();
```

### Insert

```php
DB::table('users')->insert([
    'name' => 'Administrator',
    'username' => 'admin',
    'password' => bcrypt('password'),
]);
```

### Insert and retrieve identity

The package provides SQL Anywhere-specific handling for Laravel's `insertGetId()`:

```php
$id = DB::table('users')->insertGetId([
    'name' => 'Administrator',
    'username' => 'admin',
    'password' => bcrypt('password'),
]);
```

Internally, SQL Anywhere's identity mechanism is used instead of relying on PDO's `lastInsertId()` behavior.

---

## Migrations

Laravel migrations can be used with the SQL Anywhere connection.

Example:

```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('username')->unique();
    $table->timestamps();
});
```

The package translates Laravel schema definitions into SQL Anywhere-compatible SQL.

For example, Laravel's:

```php
$table->id();
```

is mapped to an SQL Anywhere identity column.

---

## Pagination

SQL Anywhere uses different pagination syntax from MySQL.

For example, MySQL commonly uses:

```sql
LIMIT 10 OFFSET 20
```

The package's query grammar translates Laravel pagination operations into SQL Anywhere-compatible syntax using constructs such as:

```sql
TOP 10 START AT 21
```

This allows application code to continue using Laravel's normal query APIs.

Example:

```php
$users = User::query()
    ->offset(20)
    ->limit(10)
    ->get();
```

---

## Identity / Auto-Increment Columns

SQL Anywhere uses identity columns for automatically generated numeric IDs.

The package maps Laravel's auto-incrementing primary key definitions to SQL Anywhere identity syntax.

Example:

```php
$table->id();
```

This allows Laravel models to continue using automatically generated primary keys.

---

## `insertGetId()`

One of the database-specific differences between SQL Anywhere and some databases supported by Laravel is retrieving the ID generated by an insert.

Instead of relying on:

```php
$pdo->lastInsertId();
```

the SQL Anywhere processor retrieves the generated identity value using SQL Anywhere's identity mechanism:

```sql
SELECT @@identity AS id
```

This allows code such as:

```php
$id = DB::table('permissions')->insertGetId([
    'name' => 'view_reports',
    'guard_name' => 'web',
]);
```

to work with SQL Anywhere.

---

## SQL Syntax Compatibility

This package handles SQL generated by Laravel's database abstraction layer.

However, applications may contain **raw SQL** that was written specifically for MySQL.

For example, MySQL-specific SQL such as:

```sql
SUM(status = 'Completed')
```

may not work in SQL Anywhere.

The SQL Anywhere-compatible form is:

```sql
SUM(
    CASE
        WHEN status = 'Completed' THEN 1
        ELSE 0
    END
)
```

Therefore, applications using:

```php
selectRaw()
```

or:

```php
DB::raw()
```

should still review their SQL for database-specific syntax.

### Important

This package does **not** automatically translate arbitrary MySQL SQL into SQL Anywhere SQL.

It provides compatibility for the Laravel database integration layer.

Application-specific raw SQL remains the responsibility of the application developer.

---

## Architecture

The package integrates with Laravel at the database abstraction layer:

```text
Laravel Application
        │
        ▼
Laravel Eloquent / Query Builder
        │
        ▼
SQL Anywhere Connection
        │
        ├── Query Grammar
        │
        ├── Schema Grammar
        │
        └── Query Processor
        │
        ▼
PDO
        │
        ▼
ODBC
        │
        ▼
SQL Anywhere 17
        │
        ▼
SQL Anywhere Database
```

This approach allows applications to continue using Laravel's database APIs while database-specific behavior is handled by the package.

---

## Why This Package?

Laravel provides database integrations for several major relational database systems. SQL Anywhere applications may require database-specific connection, grammar, schema, and processor behavior.

This package aims to provide those missing integration components for SQL Anywhere 17.

The objective is not to modify SQL Anywhere to behave like MySQL.

Instead, the package adapts Laravel's database layer so that Laravel can communicate with SQL Anywhere using SQL Anywhere-compatible behavior.

---

## Testing

Testing is currently focused on SQL Anywhere 17.

The test suite is intended to cover:

- Database connection
- Query Builder
- Eloquent queries
- Inserts
- Identity retrieval
- Updates
- Deletes
- Pagination
- Migrations
- Schema inspection
- Indexes
- Foreign keys
- Transactions
- Aggregates
- Date/time handling

A SQL Anywhere 17 database server is required for integration tests.

---

## Known Limitations

This project is currently under development.

The following areas may require additional testing or implementation:

- Complex database-specific queries
- MySQL-specific raw SQL
- JSON-specific operations
- Advanced upsert behavior
- Full-text search
- Database-specific locking behavior
- Advanced index operations
- Stored procedures
- SQL Anywhere-specific data types
- Differences between SQL Anywhere versions
- PHP PDO ODBC driver behavior
- Laravel features that generate database-specific SQL

If you encounter a compatibility problem, please open an issue with:

1. Laravel version
2. PHP version
3. SQL Anywhere version
4. Operating system
5. Relevant Laravel code
6. Generated SQL
7. SQL Anywhere error message

Please remove passwords, connection credentials, and other sensitive information before submitting an issue.

---

## Contributing

Contributions are welcome.

Before submitting a pull request:

1. Reproduce the issue with SQL Anywhere 17.
2. Add or update an automated test where appropriate.
3. Keep database-specific behavior inside the package rather than application-specific code.
4. Follow Laravel and PSR coding conventions.
5. Document compatibility changes.

Pull requests should explain:

- What problem is being solved
- Why the existing implementation does not work with SQL Anywhere
- How the change works
- Which SQL Anywhere version was tested

---

## Development

Clone the repository:

```bash
git clone https://github.com/your-vendor/laravel-sqlanywhere.git
```

Install dependencies:

```bash
composer install
```

Run static checks:

```bash
composer validate
```

Run the test suite:

```bash
composer test
```

> Test commands may change as the project's automated test infrastructure is established.

---

## Versioning

This package follows semantic versioning where practical:

```text
MAJOR.MINOR.PATCH
```

Example:

```text
0.1.0
```

During early development, breaking changes may occur between minor releases.

Once the API and database integration stabilize, the package will move toward a stable `1.0.0` release.

---

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).

---

## Credits

Developed for Laravel applications requiring integration with SAP SQL Anywhere 17.

Special thanks to the Laravel, PHP, SQL Anywhere, PDO, and ODBC communities.

---

## Disclaimer

Laravel is a trademark of Taylor Otwell.

SAP and SQL Anywhere are trademarks or registered trademarks of SAP SE or its affiliates.

This project is an independent community package and is not affiliated with, endorsed by, or sponsored by Laravel or SAP.
