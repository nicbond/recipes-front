# Recipes Front

Symfony 7.4 application providing the frontend interface for the Recipes
project.

This project is separated from the backend and communicates with it
through HTTP API requests. It focuses on rendering the user interface,
handling user authentication, and managing the frontend session.

## Features

* Login form using email and password
* Authentication through the backend API (`/api/login_check`)
* JWT-based authentication
* Server-side session management
* Redirection to the home page when an authenticated user visits the login page
* JWT expiration handling with automatic redirection to the login page
* Flash notification explaining when the session has expired
* Role-based access control for protected frontend pages
* CSRF protection for logout
* Centralized communication with the backend API through Symfony HttpClient
* Handling of invalid credentials and unavailable authentication services

## Tech Stack

* PHP 8.3
* Symfony 7.4
* Twig
* Symfony Security
* Symfony Forms
* Symfony HttpClient
* JWT authentication
* Docker / Docker Compose
* Apache
* PHPUnit
* PHPStan
* PHP-CS-Fixer
* Twig CS Fixer
* GrumPHP

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
