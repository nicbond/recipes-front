# TECHNICAL.md

## Stack

See `README.md` for the complete technology stack.

The frontend is a Symfony 7.4 application that renders pages with Twig
and communicates with the Recipes backend through HTTP API requests.

--------------------------------------------------------------------------------------------------------------

## Development Principles

-   Keep controllers focused on HTTP orchestration and page rendering.
-   Use dependency injection for application services.
-   Centralize backend API communication in a dedicated service.
-   Keep authentication responsibilities explicit and separate from APIbusiness logic.
-   Keep the backend authoritative for credential validation and JWT signature verification.
-   Avoid duplicating backend business rules in the frontend.
-   Handle authentication failures and unavailable API responses with user-friendly messages.
-   Protect state-changing frontend routes and forms with CSRF protection where appropriate.
-   Keep cross-cutting request behavior in event subscribers when it must apply consistently across pages.

--------------------------------------------------------------------------------------------------------------

## Architecture Overview

The frontend request flow follows Symfony's HTTP lifecycle:

``` text
HTTP Request
     │
     ▼
Symfony Kernel
     │
     ├── Request listeners
     │
     ├── RouterListener (priority 32)
     │       └── Resolves the route and request attributes
     │
     ├── Security firewall listener (priority 8)
     │       └── Restores the authenticated user from the session
     │
     ├── ExpiredJwtSubscriber (priority 7)
     │       └── Checks whether the authenticated user's JWT has expired
     │
     ▼
Controller
     │
     ├── Twig rendering
     │
     └── ApiService
              │
              ▼
         Backend HTTP API
```

The listener priorities shown above describe the configured `kernel.request` listener order observed in the 
application. Symfony dispatches listeners in descending priority order: higher prioritiesexecute first.

The `ExpiredJwtSubscriber` runs after routing and the security firewall listener so that the route is 
available and the firewall has had an opportunity to restore the authenticated token from the session.

--------------------------------------------------------------------------------------------------------------

## Frontend / Backend Responsibilities

The application is split into two Symfony applications.

### Frontend responsibilities

-   Render the user interface with Twig.
-   Validate the submitted login form at the form level.
-   Send credentials to the backend authentication endpoint.
-   Keep the authenticated frontend user and JWT in the server-side Symfony session.
-   Check the JWT expiration timestamp to provide a smooth user experience.
-   Redirect users to the login page when their JWT has expired.
-   Display a message explaining why the user must sign in again.

### Backend responsibilities

-   Validate the user's credentials.
-   Issue signed JWTs.
-   Verify JWT signatures and token validity on protected API endpoints.
-   Enforce API authorization and business rules.

The frontend decodes the JWT payload only to read its `exp` claim. It does **not** verify the cryptographic 
signature. The backend remains the authority for token verification, using LexikJWTAuthenticationBundle and
the configured RS256 signature algorithm.

--------------------------------------------------------------------------------------------------------------

## Authentication Flow

The frontend login route is:

``` text
GET|POST /connexion
Route name: app_login
```

The backend authentication endpoint is:

``` text
POST /api/login_check
```

The backend expects the user's email and password and returns a JWT when
authentication succeeds.

The flow is:

``` text
User
 │
 ▼
Login form (LoginType)
 │
 ▼
ApiFormAuthenticator
 │
 ├── Validates the submitted form
 │
 ├── Calls ApiService::postLogin(email, password)
 │
 ▼
Backend POST /api/login_check
 │
 ├── Invalid credentials → authentication failure
 │
 └── Valid credentials → JWT response
                          │
                          ▼
                  Validate response and exp
                          │
                          ▼
                  Create ApiUser
                          │
                          ▼
                  Symfony authenticated session
                          │
                          ▼
                  Redirect to app_home
```

### Login form and authenticator

`LoginType` defines the login form fields. The authenticator supports a POST request to the `app_login` route.

The authenticator:

1.  Handles and validates the submitted form.
2.  Sends the email and password to the backend through `ApiService`.
3.  Distinguishes invalid credentials from an unavailable authentication service.
4.  Checks that the response contains a non-empty JWT.
5.  Reads the token's `exp` claim and rejects a missing or already expired expiration timestamp.
6.  Creates an `ApiUser` and returns a `SelfValidatingPassport`, because credential validation has already been performed by the backend.
7.  On success, stores the JWT in the server-side session and redirects o the home page.
8.  On failure, stores the authentication error and redirects back to the login page.

The frontend must not log or expose the user's password or JWT.

### API error handling

The frontend maps authentication failures to clear user-facing messages. Invalid credentials should not be 
presented as an infrastructure failure. Network errors or unexpected backend responses should instead
produce a generic service-unavailable message.

The HTTP status code returned by the backend is interpreted by the authenticator; the frontend should follow 
the actual API contract rather than assuming that every authentication failure uses the same status code.

--------------------------------------------------------------------------------------------------------------

## ApiUser and User Provider

### ApiUser

`ApiUser` represents the authenticated user in the frontend security system. It holds the user's identifier, 
roles, JWT and its expiration timestamp.

The frontend does not need to persist a separate local copy of thebackend user's password. The JWT is kept 
server-side in the Symfony session.

The `isTokenExpired()` method compares the stored expiration timestamp with the current Unix timestamp:

``` php
public function isTokenExpired(): bool
{
    return null === $this->expiresAt || time() >= $this->expiresAt;
}
```

A missing expiration timestamp is treated as expired, following a fail-closed approach.

### ApiUserProvider

`ApiUserProvider` supports the frontend's `ApiUser` class and checks the expiration state when a user is 
refreshed by Symfony Security. Authentication itself is initiated by `ApiFormAuthenticator`, not by loading a 
user from a local database.

Token expiration is also checked centrally by `ExpiredJwtSubscriber`, so protected frontend requests do not 
rely solely on visiting the login page to detect an expired token.

--------------------------------------------------------------------------------------------------------------

## JWT Expiration and ExpiredJwtSubscriber

The frontend uses `ExpiredJwtSubscriber` to handle JWT expiration
consistently across incoming main HTTP requests.

File:

``` text
src/EventSubscriber/ExpiredJwtSubscriber.php
```

The subscriber listens to `KernelEvents::REQUEST`:

``` php
public static function getSubscribedEvents(): array
{
    return [
        KernelEvents::REQUEST => ['checkTokenExpiration', 7],
    ];
}
```

### Why is the priority `7`?

Symfony executes event listeners in **descending priority order**: a
listener with priority `32` runs before one with priority `8`, which
runs before one with priority `7`.

In this application, the observed `kernel.request` listener order
includes:

  -------------------------------------------------------------------------------------------------------------------------------------------
  Listener                                                                                                     Priority Purpose
  ---------------------------------------------------------------------------------------- ---------------------------- ---------------------
  `RouterListener::onKernelRequest()`                                                                              `32` Matches the request
                                                                                                                        to a route and
                                                                                                                        populates route
                                                                                                                        attributes

  `TraceableFirewallListener::onKernelRequest()`                                                                    `8` Runs the Security
                                                                                                                        firewall for the
                                                                                                                        request

  `Knp\Bundle\PaginatorBundle\Subscriber\SlidingPaginationSubscriber::onKernelRequest()`                            `0` Handles paginator
                                                                                                                        request state

  `ExpiredJwtSubscriber::checkTokenExpiration()`                                                                   `7` Checks the
                                                                                                                        authenticated user's
                                                                                                                        JWT expiration
  -------------------------------------------------------------------------------------------------------------------------------------------

The `7` priority is intentional: it places the expiration check **after routing and after the firewall listener**, while still running during
`kernel.request`, before controller execution.

This ordering matters for two reasons:

1.  **The route is already known.** The router normally runs at priority
    `32`, so the subscriber can read `_route` and skip the login and
    logout routes.
2.  **Security has had an opportunity to restore the authenticated
    token.** The firewall listener runs at priority `8`, before this
    subscriber at `7`. The subscriber can therefore inspect the current
    security token and its `ApiUser`.

The value `7` is not a special Symfony constant and does not mean
"before the controller" by itself. It is a relative ordering choice
based on the priorities of the other listeners. Any listeners with
priorities between `8` and `7` run before this subscriber; listeners
with priorities below `7` run after it. The actual order can be checked
in the project with:


``` bash
php bin/console debug:event-dispatcher kernel.request
```

The exact list may differ between environments because bundles and debug mode can register additional listeners.

### What the subscriber does

For each main request, the subscriber:

1.  Skips subrequests.
2.  Skips the `app_login` and `app_logout` routes to avoid redirect loops and allow authentication/logout handling.
3.  Reads the current Security token.
4.  Does nothing if there is no authenticated `ApiUser`.
5.  Checks the JWT expiration timestamp.
6.  If the JWT has expired, clears the Security token and invalidates the session.
7.  Adds a flash message explaining that the session expired.
8.  Redirects the user to `app_login`.

The flash message is added **after** session invalidation, because invalidating the session clears the previous 
session data. The login template or shared layout must render the `warning` flash messages for the user to see 
the notification.

Example message:

> Your session has expired for security reasons. Please sign in again.

### Scope and security boundary

This subscriber is a frontend user-experience mechanism. It decodes the
JWT payload to read `exp`; it does not validate the token's RS256
signature.

When the frontend calls a protected backend endpoint, the backend must
validate the JWT independently. A token that is expired, malformed,
tampered with, or incorrectly signed must be rejected by the backend.
The frontend must handle an HTTP `401 Unauthorized` response from the
backend as well, because a token can be rejected by the API even if the
frontend's local expiration check has not yet triggered.

--------------------------------------------------------------------------------------------------------------

## Security Configuration

The frontend firewall uses the configured `ApiUserProvider` and `ApiFormAuthenticator`.

The login page is publicly accessible. Protected pages can require `ROLE_CONSUMER`, while public routes remain 
accessible to anonymous visitors according to the application's `access_control` rules.

The logout endpoint is handled by Symfony Security:

``` text
POST /deconnexion
Route name: app_logout
```

Logout is submitted as a POST request with a CSRF token. The controller action itself is not responsible for 
performing the logout; the firewall intercepts the configured logout route.

CSRF protection should remain enabled for logout and any other state-changing browser form.

--------------------------------------------------------------------------------------------------------------

## Backend API Communication

Backend HTTP calls are centralized in `ApiService`, which uses Symfony HttpClient.

This keeps transport details out of controllers and security classes that do not need to manage raw HTTP 
requests themselves.

The base backend URL is configured through the project's environment configuration. The configured URL must be 
reachable **from inside the PHP container**, not merely from the developer's host machine.

For authentication, the frontend calls `POST /api/login_check` with the email and password. Other protected API 
requests should send the JWT in the `Authorization: Bearer <token>` header.

Handle at least these situations distinctly:

-   successful API response;
-   invalid credentials;
-   expired or rejected JWT (`401 Unauthorized`);
-   forbidden access (`403 Forbidden`);
-   network error or unavailable backend;
-   unexpected response body or status code.

Do not expose internal exception messages, stack traces, credentials or tokens to the user.

--------------------------------------------------------------------------------------------------------------

## Session Management

The authenticated frontend state is stored in the server-side Symfony session. The JWT is not intended to be 
placed in a browser-readable cookie or exposed to frontend JavaScript.

On successful authentication, the JWT is stored in the session. On local expiration, the subscriber clears the 
Security token and invalidates the session before redirecting to the login page.

A session is a frontend mechanism for remembering the authenticated state; it does not replace backend JWT validation.

--------------------------------------------------------------------------------------------------------------

## Error Handling and User Feedback

Authentication errors should explain what the user can do without revealing sensitive implementation details.

Recommended distinction:

-   Invalid email or password: ask the user to check their credentials.
-   Expired session: explain that the session has expired and ask the user to sign in again.
-   Backend unavailable: explain that authentication is temporarily unavailable and invite the user to retry later.
-   Invalid backend response: show a generic authentication error and log technical details safely on the server if required.

Flash messages are short-lived and should be consumed by the template when displayed. Ensure the shared layout or 
login page renders the relevant flash type, such as `warning`.

--------------------------------------------------------------------------------------------------------------

## Docker Development Environment

The application runs in Docker Compose with separate Apache and PHP-FPM services.

Useful commands:

``` bash
# Start the containers
docker compose up -d

# Display service status
docker compose ps

# Follow PHP logs
docker compose logs -f php

# Open a shell in the PHP container
docker compose exec php sh

# Inspect kernel.request listener order
docker compose exec php php bin/console debug:event-dispatcher kernel.request
```

The Compose service name is `php`; `recipes-front_php` is the container name in the current local environment. 
Commands using `docker compose exec` must target the service name.

--------------------------------------------------------------------------------------------------------------

## Troubleshooting

### JWT expiration redirects to login

Check:

-   the JWT contains a numeric `exp` claim;
-   `ApiUser` receives the expiration timestamp;
-   `ExpiredJwtSubscriber` is registered as a service;
-   the subscriber runs after the router and Security firewall listeners;
-   the login template renders the `warning` flash message.

Use a short backend `token_ttl` only for local testing, then restore the intended lifetime.

### Backend authentication is unavailable

Check:

-   the backend container or service is running;
-   the frontend's configured backend base URL is reachable from the PHP container;
-   the endpoint `/api/login_check` is correct;
-   the backend's actual HTTP status codes match the handling implemented by the authenticator.

### Listener execution order

Run:

``` bash
php bin/console debug:event-dispatcher kernel.request
```

Confirm that `RouterListener` and the Security firewall listener run before `ExpiredJwtSubscriber`. 
Do not assume listener priorities are unchanged after adding or updating bundles.

--------------------------------------------------------------------------------------------------------------

## Design Decisions

-   **Backend as the security authority:** credentials and JWT signatures are validated by the backend.
-   **Server-side token storage:** the JWT is kept in the Symfony session instead of being exposed to browser JavaScript.
-   **Centralized expiration handling:** one subscriber applies the same expiry behavior to main frontend requests.
-   **Explicit event priority:** priority `-1` lets routing and the Security firewall listener run first, so 
    the subscriber can inspect the resolved route and restored user token.
-   **Separation of concerns:** controllers handle page flow, `ApiService` handles API transport, the authenticator 
    handles login, and the subscriber handles cross-cutting JWT expiration behavior.
