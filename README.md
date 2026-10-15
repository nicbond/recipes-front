# Recipes

Symfony 7 application for managing cooking recipes.

This personal project was created to experiment with modern Symfony best practices and explore architectural patterns used in professional projects.

## Features

* Recipe management (CRUD, online/offline status, tags, ingredients, quantities)
* Category and tag management
* Ingredient creation on-the-fly via Tom Select autocomplete
* Recipe image upload, preview and WebP conversion
* Turbo-powered modal forms (edit without page reload)
* Pagination and sortable listings
* Email and SMS notifications
* Responsive administration back-office
* Business rules enforced at application level (e.g. protected deletions)
* Async PDF generation via Symfony Messenger
* Authentication (login, registration) with role-based access control
* Email verification on registration via signed URL (symfonycasts/verify-email-bundle)
* Password reset via secure token with expiration (symfonycasts/reset-password-bundle)
* Asynchronous email delivery via Symfony Messenger (contact, account verification, password reset)
* Password visibility toggle on all password fields
* Google Authenticator two-factor authentication (2FA) via scheb/2fa-bundle
* Granular permissions with PHP enums, Symfony Security Voters, and administrator permission management


## Tech Stack

* PHP 8.4
* Symfony 7
* Doctrine ORM
* MySQL
* Docker / Docker Compose
* Twig
* Symfony UX Autocomplete
* Symfony UX Turbo & Stimulus
* SortableJS
* Bootstrap 5
* Font Awesome
* symfonycasts/verify-email-bundle
* symfonycasts/reset-password-bundle
* scheb/2fa-bundle + scheb/2fa-google-authenticator
* Custom Security Voters & PHP Enums for granular permissions
* PHPUnit
* PHPStan
* PHP-CS-Fixer
* Twig CS Fixer
* GrumPHP
* Mailpit


> See [TECHNICAL.md](TECHNICAL.md) for a detailed description of the architecture and implementation choices.

## Requirements

* Docker
* Docker Compose
* Make

## Installation

* Replace all occurrences of `symfony_docker` with your project name
* Edit the `PROJECT_PORT` variable in `.env` to set the application port
* `make .env.local`
* Edit your `.env.local`
* `make install` or `make reset`

## Environment variables

```dotenv
# Application URL (used for signed URLs in emails)
DEFAULT_URI=http://localhost:8089
```

## Useful commands

### Find a free port on your machine

```bash
python3 -c "import socket; s=socket.socket(); s.bind(('',0)); print(s.getsockname()[1]); s.close()"
```

### Initialize all components for a web application

```bash
composer require webapp
```

### Compile assets

```bash
php bin/console asset-map:compile
```

## Testing

```bash
php bin/phpunit
php bin/phpunit --filter testIndex
php bin/phpunit --testdox


## Code Quality

```bash
vendor/bin/phpstan analyse src --level=9
vendor/bin/php-cs-fixer fix
vendor/bin/twig-cs-fixer lint templates/
vendor/bin/twig-cs-fixer fix templates/
vendor/bin/grumphp run
```


## Docker

The development environment includes:

* PHP 8.4 / PHP-FPM
* Apache
