# TECHNICAL.md

## Stack

See `README.md` for the complete technology stack.

The frontend is a Symfony 7.4 application that renders pages with Twig
and communicates with the Recipes backend through HTTP API requests.

--------------------------------------------------------------------------------------------------------------

## Development Principles

- Keep controllers focused on HTTP orchestration and page rendering.
- Use dependency injection for application services.
- Centralize backend API communication in a dedicated service.
- Keep authentication responsibilities explicit and separate from API business logic.
- Keep the backend authoritative for credential validation and JWT signature verification.
- Avoid duplicating backend business rules in the frontend.
- Handle authentication failures and unavailable API responses with user-friendly messages.
- Protect state-changing frontend routes and forms with CSRF protection where appropriate.
- Keep cross-cutting request behavior in event subscribers when it must apply consistently across pages.

--------------------------------------------------------------------------------------------------------------

## Architecture Overview

The frontend request flow follows Symfony's HTTP lifecycle:

```text
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

The listener priorities shown above describe the configured `kernel.request` listener order observed in the application. Symfony dispatches listeners in descending priority order: higher priorities execute first.

The `ExpiredJwtSubscriber` runs after routing and the security firewall listener so that the route is available and the firewall has had an opportunity to restore the authenticated token from the session.

--------------------------------------------------------------------------------------------------------------

## Frontend / Backend Responsibilities

The application is split into two Symfony applications.

### Frontend responsibilities

- Render the user interface with Twig.
- Validate the submitted login form at the form level.
- Send credentials to the backend authentication endpoint.
- Keep the authenticated frontend user and JWT in the server-side Symfony session.
- Check the JWT expiration timestamp to provide a smooth user experience.
- Redirect users to the login page when their JWT has expired.
- Display a message explaining why the user must sign in again.

### Backend responsibilities

- Validate the user's credentials.
- Issue signed JWTs.
- Verify JWT signatures and token validity on protected API endpoints.
- Enforce API authorization and business rules.

The frontend decodes the JWT payload only to read its `exp` claim. It does **not** verify the cryptographic signature. The backend remains the authority for token verification, using LexikJWTAuthenticationBundle and the configured RS256 signature algorithm.

--------------------------------------------------------------------------------------------------------------

## Authentication Flow

The frontend login route is:

```text
GET|POST /connexion
Route name: app_login
```

The backend authentication endpoint is:

```text
POST /api/login_check
```

The backend expects the user's email and password and returns a JWT when authentication succeeds.

The flow is:

```text

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
                   GET /api/me
                   Authorization: Bearer JWT
                           │
                           ▼
                   Backend returns consumer profile
                           │
                           ▼
                   Hydrate User and Identity
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

1. Handles and validates the submitted form.
2. Sends the email and password to the backend through `ApiService`.
3. Distinguishes invalid credentials from an unavailable authentication service.
4. Checks that the response contains a non-empty JWT.
5. Reads the token's `exp` claim and rejects a missing or already expired expiration timestamp.
6. Calls the backend `GET /api/me` endpoint through `ApiService`, sending the JWT in the `Authorization: Bearer` header.
7. Validates the consumer profile response and hydrates the `User` and `Identity` models.
8. Creates an `ApiUser` using the hydrated user profile, JWT and expiration timestamp, then returns a `SelfValidatingPassport`, 
because credential validation has already been performed by the backend.
9. On success, stores the authenticated user and JWT in the server-side session and redirects to the home page.
10. On failure, stores the authentication error and redirects back to the login page.

The frontend must not log or expose the user's password or JWT.

### API error handling

The frontend maps authentication failures to clear user-facing messages. Invalid credentials should not be presented as an infrastructure failure. Network errors or unexpected backend responses should instead produce a generic service-unavailable message.

The HTTP status code returned by the backend is interpreted by the authenticator; the frontend should follow the actual API contract rather than assuming that every authentication failure uses the same status code.

--------------------------------------------------------------------------------------------------------------


## User Model

The frontend defines its own user models under:

```text
src/Model/User/
├── User.php
└── Identity.php
```

These models represent the consumer profile returned by the backend API. They are frontend models and are independent from the backend Doctrine entities.

## User

The `User` model represents the authenticated consumer's profile.

It contains:

- The user's identifier.
- The user's email address.
- The user's roles.
- The account creation date (`createdAt`).
- The user's identity (`Identity`).

The model is populated using the backend `GET /api/me` endpoint after successful authentication.

It does not contain persistence logic or duplicate backend business rules.

## Identity

The `Identity` model contains the consumer's personal identity information:

- Gender.
- First name.
- Last name.
- Birth date.

The gender is kept as a string in the frontend to avoid coupling the frontend model to the backend enum implementation.

The birth date is hydrated as a `DateTimeImmutable` instance. Formatting and presentation remain the responsibility of Twig.

**### Model hydration**

After receiving a valid JWT from the backend, the frontend retrieves the authenticated consumer's profile through `GET /api/me`.

The JWT is sent using the `Authorization: Bearer <token>` header.

The frontend validates the API response and hydrates the `User` and `Identity` models.

These models are then used by `ApiUser` to expose the authenticated consumer to Symfony Security.


## ApiUser and User Provider

### ApiUser

`ApiUser` represents the authenticated user in the frontend Symfony Security system.

It wraps the frontend `App\Model\User\User` model, which contains the consumer profile returned by the backend `GET /api/me` endpoint.

Its responsibilities are to adapt this user profile to Symfony Security and manage the authentication-related information:

- The authenticated user's identifier.
- The user's effective roles.
- The JWT.
- The JWT expiration timestamp.

The user's profile information is provided by the `User` model. `ApiUser` adds the `ROLE_CONSUMER` role to the effective roles 
exposed to Symfony Security.

`ApiUser` implements Symfony's `EquatableInterface`. Its `isEqualTo()` method compares the user identifier and roles when 
Symfony Security compares the authenticated user restored from the session.

The frontend does not need to persist a separate local copy of the backend user's password. The JWT is kept server-side in the 
Symfony session.

### ApiUserProvider

`ApiUserProvider` supports the frontend's `ApiUser` class.

Authentication is initiated by `ApiFormAuthenticator`, which retrieves the consumer profile from the backend and creates the authenticated user. The provider does not load users from a local database.

Token expiration is handled centrally by `ExpiredJwtSubscriber`, ensuring that protected frontend requests do not rely solely 
on visiting the login page to detect an expired token.

--------------------------------------------------------------------------------------------------------------

## JWT Expiration and ExpiredJwtSubscriber

The frontend uses `ExpiredJwtSubscriber` to handle JWT expiration consistently across incoming main HTTP requests.

File:

```text
src/EventSubscriber/ExpiredJwtSubscriber.php
```

The subscriber listens to `KernelEvents::REQUEST`:

```php
public static function getSubscribedEvents(): array
{
    return [
        KernelEvents::REQUEST => ['checkTokenExpiration', 7],
    ];
}
```

### Why is the priority `7`?

Symfony executes event listeners in **descending priority order**: a listener with priority `32` runs before one with priority `8`, which runs before one with priority `7`.

In this application, the observed `kernel.request` listener order includes:

| Listener | Priority | Purpose |
|---|---:|---|
| `RouterListener::onKernelRequest()` | `32` | Matches the request to a route and populates route attributes |
| `TraceableFirewallListener::onKernelRequest()` | `8` | Runs the Security firewall |
| `ExpiredJwtSubscriber::checkTokenExpiration()` | `7` | Checks the authenticated user's JWT expiration |
| `Knp\Bundle\PaginatorBundle\Subscriber\SlidingPaginationSubscriber::onKernelRequest()` | `0` | Handles paginator request state |

The `7` priority is intentional: it places the expiration check **after routing and after the firewall listener**, while still running during `kernel.request`, before controller execution.

This ordering matters for two reasons:

1. **The route is already known.** The router normally runs at priority `32`, so the subscriber can read `_route` and skip the login and logout routes.
2. **Security has had an opportunity to restore the authenticated token.** The firewall listener runs at priority `8`, before this subscriber at `7`. The subscriber can therefore inspect the current security token and its `ApiUser`.

The value `7` is not a special Symfony constant and does not mean "before the controller" by itself. It is a relative ordering choice based on the priorities of the other listeners. Any listeners with priorities between `8` and `7` run before this subscriber; listeners with priorities below `7` run after it. The actual order can be checked in the project with:

```bash
php bin/console debug:event-dispatcher kernel.request
```

The exact list may differ between environments because bundles and debug mode can register additional listeners.

### What the subscriber does

For each main request, the subscriber:

1. Skips subrequests.
2. Skips the `app_login` and `app_logout` routes to avoid redirect loops and allow authentication/logout handling.
3. Reads the current Security token.
4. Does nothing if there is no authenticated `ApiUser`.
5. Checks the JWT expiration timestamp.
6. If the JWT has expired, clears the Security token and invalidates the session.
7. Adds a flash message explaining that the session expired.
8. Redirects the user to `app_login`.

The flash message is added **after** session invalidation, because invalidating the session clears the previous session data. The login template or shared layout must render the `warning` flash messages for the user to see the notification.

Example message:

> Your session has expired for security reasons. Please sign in again.

### Scope and security boundary

This subscriber is a frontend user-experience mechanism. It decodes the JWT payload to read `exp`; it does not validate the token's RS256 signature.

When the frontend calls a protected backend endpoint, the backend must validate the JWT independently. A token that is expired, malformed, tampered with, or incorrectly signed must be rejected by the backend. The frontend must handle an HTTP `401 Unauthorized` response from the backend as well, because a token can be rejected by the API even if the frontend's local expiration check has not yet triggered.

--------------------------------------------------------------------------------------------------------------

## Security Configuration

The frontend firewall uses the configured `ApiUserProvider` and `ApiFormAuthenticator`.

The login page is publicly accessible. Protected pages can require `ROLE_CONSUMER`, while public routes remain accessible to anonymous visitors according to the application's `access_control` rules.

The logout endpoint is handled by Symfony Security:

```text
POST /deconnexion
Route name: app_logout
```

Logout is submitted as a POST request with a CSRF token. The controller action itself is not responsible for performing the logout; the firewall intercepts the configured logout route.

CSRF protection should remain enabled for logout and any other state-changing browser form.

--------------------------------------------------------------------------------------------------------------

## Backend API Communication

Backend HTTP calls are centralized in `ApiService`, which uses Symfony HttpClient.

This keeps transport details out of controllers and security classes that do not need to manage raw HTTP requests themselves.

The base backend URL is configured through the project's environment configuration. The configured URL must be reachable **from inside the PHP container**, not merely from the developer's host machine.

For authentication, the frontend calls `POST /api/login_check` with the email and password. Other protected API requests should send the JWT in the `Authorization: Bearer <token>` header.

Handle at least these situations distinctly:

- Successful API response.
- Invalid credentials.
- Expired or rejected JWT (`401 Unauthorized`).
- Forbidden access (`403 Forbidden`).
- Network error or unavailable backend.
- Unexpected response body or status code.

Do not expose internal exception messages, stack traces, credentials or tokens to the user.

--------------------------------------------------------------------------------------------------------------

## Consumer Account Verification

Consumer account verification is handled through a dedicated frontend flow that relies on the backend API to validate the email verification link and activate the account.

The frontend is responsible for presenting the verification page and requiring an explicit user action before forwarding the request to the backend.

The backend remains responsible for validating the signed verification URL and updating the Consumer account.

### Verification Flow

The verification flow is:

```text
Consumer registration
        │
        ▼
Backend sends verification email
        │
        ▼
Email link to frontend
GET /verification-compte
        │
        ▼
Consumer verification page
        │
        ▼
User clicks "Activer mon compte"
        │
        ▼
POST /verification-compte
        │
        ▼
ConsumerVerificationController
        │
        ├── Validates the CSRF token
        │
        ├── Retrieves the original verification query string
        │
        ▼
ApiService
        │
        ▼
Backend POST /api/consumers/activate
        │
        ├── Validates the signed verification URL
        │
        ├── Activates the Consumer account
        │
        └── Clears the confirmation token
                 │
                 ▼
          Frontend success page
          /verification-compte/terminee
```

### ConsumerVerificationController

The verification flow is managed by `ConsumerVerificationController`.

It exposes three routes:

| Route | Method | Purpose |
|---|---|---|
| `/verification-compte` | `GET` | Displays the verification page |
| `/verification-compte` | `POST` | Forwards the activation request to the backend |
| `/verification-compte/terminee` | `GET` | Displays the activation confirmation page |

The controller:

1. Checks that the verification parameters are present before displaying the activation form.
2. Requires an explicit POST action from the user before calling the backend.
3. Validates a CSRF token associated with the Consumer identifier.
4. Forwards the original verification query string to the backend without reconstructing the signed URL.
5. Displays the backend response through frontend flash messages.
6. Redirects the user to the confirmation page when activation succeeds.

The explicit user action is intentional. Some email security systems automatically follow links to inspect their contents. Requiring a button click prevents a simple GET request from activating an account.

### Signed URL and Docker Networking

The backend generates the signed verification URL using its canonical public URL, configured through `DEFAULT_URI`.

In the local development environment:

- The backend public URL is `http://localhost:8088`.
- The frontend public URL is `http://localhost:8089`.
- The frontend reaches the backend through its internal Docker URL: `http://recettes_apache/api`.

These URLs serve different purposes:

- `API_BACK_URL` is used for HTTP communication from the frontend PHP container to the backend.
- `API_BACK_PUBLIC_HOST` provides the backend host expected when validating the signed verification URL.

The frontend must not replace the internal Docker URL with `localhost` for API communication. Inside a container, `localhost` refers to that container itself.

### Host Header Override

The signed URL is generated using the backend's canonical URL. However, the frontend sends the activation request through the internal Docker hostname.

This creates a host mismatch during signature validation:

```text
Signed URL host:       localhost:8088
Internal API request:  recettes_apache
```

The backend validates the signature against the request context. If the host differs from the one used to generate the signed URL, verification can fail even though the URL parameters are correct.

To preserve the expected host during this specific request, the frontend overrides the `Host` header when calling the activation endpoint:

```php
$apiResponse = $apiService->request(
    'POST',
    '/consumers/activate?'.$verificationQuery,
    [
        'headers' => [
            'Host' => $this->apiBackPublicHost,
        ],
    ]
);
```

The host override is deliberately limited to the Consumer activation request. It must not be applied globally in `ApiService`, because other API requests use the internal Docker network normally.

The expected host is configured through:

```dotenv
API_BACK_PUBLIC_HOST=localhost:8088
```

This configuration must match the host used by the backend when generating the signed verification URL.

For deployments using HTTPS or a reverse proxy, the canonical scheme and forwarded request headers must also be configured and validated consistently. The local HTTP host override alone does not establish the complete production configuration.

### Security Considerations

- The original signed query string is forwarded to the backend rather than regenerated by the frontend.
- The frontend does not validate the cryptographic signature itself.
- The backend remains responsible for checking the signature, expiration and validity of the verification link.
- The activation request is submitted through POST and protected by CSRF.
- A GET request must never activate a Consumer account.
- The backend response is handled without exposing internal exception details to the user.

The verification flow is separate from the existing Admin verification and password-reset mechanisms. Those flows must remain unchanged.

--------------------------------------------------------------------------------------------------------------

## Session Management

The authenticated frontend state is stored in the server-side Symfony session. The JWT is not intended to be placed in a browser-readable cookie or exposed to frontend JavaScript.

On successful authentication, the JWT is stored in the session. On local expiration, the subscriber clears the Security token and invalidates the session before redirecting to the login page.

A session is a frontend mechanism for remembering the authenticated state; it does not replace backend JWT validation.

--------------------------------------------------------------------------------------------------------------

## Error Handling and User Feedback

Authentication and account verification errors should explain what the user can do without revealing sensitive implementation details.

Recommended distinction:

- Invalid email or password: ask the user to check their credentials.
- Expired session: explain that the session has expired and ask the user to sign in again.
- Invalid or expired verification link: explain that the link is no longer valid and that a new verification email may be required.
- Consumer activation failure: display a user-friendly message without exposing backend implementation details.
- Backend unavailable: explain that authentication or account verification is temporarily unavailable and invite the user to retry later.
- Invalid backend response: show a generic error and log technical details safely on the server if required.

Flash messages are short-lived and should be consumed by the template when displayed. Ensure the shared layout or relevant page renders the appropriate flash types, such as `warning`, `success` and `danger`.

--------------------------------------------------------------------------------------------------------------

## Docker Development Environment

The application runs in Docker Compose with separate Apache and PHP-FPM services.

Useful commands:

```bash
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

The Compose service name is `php`; `recipes-front_php` is the container name in the current local environment. Commands using `docker compose exec` must target the service name.

--------------------------------------------------------------------------------------------------------------

## Troubleshooting

### JWT expiration redirects to login

Check:

- The JWT contains a numeric `exp` claim.
- `ApiUser` receives the expiration timestamp.
- `ExpiredJwtSubscriber` is registered as a service.
- The subscriber runs after the router and Security firewall listeners.
- The login template renders the `warning` flash message.

Use a short backend `token_ttl` only for local testing, then restore the intended lifetime.

### Backend authentication is unavailable

Check:

- The backend container or service is running.
- The frontend's configured backend base URL is reachable from the PHP container.
- The endpoint `/api/login_check` is correct.
- The backend's actual HTTP status codes match the handling implemented by the authenticator.

### Consumer verification link is invalid

Check:

- The verification link contains the expected `id`, `token`, `expires` and `signature` parameters.
- The backend's `DEFAULT_URI` matches the canonical URL used to generate the signed verification link.
- `API_BACK_PUBLIC_HOST` matches the expected backend host.
- The activation request overrides the `Host` header only for the Consumer activation endpoint.
- The original verification query string is forwarded without modification.
- The backend activation endpoint is reachable from the frontend PHP container.

### Listener execution order

Run:

```bash
php bin/console debug:event-dispatcher kernel.request
```

Confirm that `RouterListener` and the Security firewall listener run before `ExpiredJwtSubscriber`. Do not assume listener priorities are unchanged after adding or updating bundles.

--------------------------------------------------------------------------------------------------------------

## Design Decisions

- **Backend as the security authority:** credentials and JWT signatures are validated by the backend.
- **Server-side token storage:** the JWT is kept in the Symfony session instead of being exposed to browser JavaScript.
- **Centralized expiration handling:** one subscriber applies the same expiry behavior to main frontend requests.
- **Explicit event priority:** priority `7` lets routing and the Security firewall listener run first, so the subscriber can inspect the resolved route and restored user token.
- **Separation of concerns:** controllers handle page flow, `ApiService` handles API transport, the authenticator handles login, and the subscriber handles cross-cutting JWT expiration behavior.
- **Explicit Consumer activation:** account activation requires a deliberate POST action from the user rather than being triggered by following the email link alone.
- **Backend validation of signed URLs:** the frontend forwards the original verification parameters, while the backend remains responsible for validating the signature and activating the account.
- **Scoped Host header override:** the expected backend host is supplied only for the Consumer activation request to preserve signed URL validation across the Docker network.
