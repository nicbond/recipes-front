# Recipes Front

Symfony 7.4 application providing the frontend interface for the Recipes project.

This project is separated from the backend and communicates with it through HTTP API requests. It focuses on rendering the user interface, handling user authentication, managing the frontend session, and providing Consumer account verification.

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
* Consumer email verification through a dedicated frontend page.
* Explicit user action required to activate a Consumer account.
* Forwarding of signed verification requests to the backend API.
* Centralized communication with the backend API through Symfony HttpClient.
* Handling of invalid credentials, expired verification links, and unavailable backend services.

## Tech Stack

* PHP 8.4
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

See [TECHNICAL.md](TECHNICAL.md) for a detailed description of the architecture and implementation choices.

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

The frontend uses the following environment variables to communicate with the backend:

```dotenv
# Internal backend API URL (Docker network)
API_BACK_URL=http://recettes_apache/api

# Backend public host used to validate signed Consumer verification URLs
API_BACK_PUBLIC_HOST=localhost:8088
```

`API_BACK_URL` must use an address reachable from inside the frontend PHP container. In the local Docker environment, the backend is reached through its Docker container hostname.

`API_BACK_PUBLIC_HOST` must match the backend host used to generate signed Consumer verification URLs. It is used specifically when forwarding the Consumer activation request to the backend.

The backend's `DEFAULT_URI` is configured separately in the backend project and must match the canonical URL used to generate signed verification links.

See the backend documentation for its own environment configuration.

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
```

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