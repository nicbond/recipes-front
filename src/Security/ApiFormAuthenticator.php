<?php

namespace App\Security;

use App\Exception\ApiUnauthorizedException;
use App\Form\Security\LoginType;
use App\Model\User\User;
use App\Security\User\ApiUser;
use App\Service\ApiService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class ApiFormAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    private const MESSAGE_INVALID_FORM = 'Veuillez vérifier les données du formulaire.';
    private const MESSAGE_BAD_CREDENTIALS = 'Adresse e-mail ou mot de passe incorrect.';
    private const MESSAGE_UNAVAILABLE = 'Le service d’authentification est momentanément indisponible.';
    private const MESSAGE_PROFILE_UNAVAILABLE = 'Impossible de récupérer votre profil utilisateur.';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private FormFactoryInterface $formFactory,
        private ApiService $apiService,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'app_login' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        [$email, $password] = $this->extractCredentials($request);

        try {
            [$token, $expiresAt] = $this->login($email, $password);
            $user = $this->fetchUser($token);
        } catch (ApiUnauthorizedException $exception) {
            // The API rejected the token it has just issued: the login cannot succeed.
            throw new CustomUserMessageAuthenticationException(self::MESSAGE_PROFILE_UNAVAILABLE, previous: $exception);
        } catch (ExceptionInterface|\JsonException $exception) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE_UNAVAILABLE, previous: $exception);
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $user->getEmail(),
                static fn (): ApiUser => new ApiUser($user, $token, $expiresAt),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        $user = $token->getUser();

        if (!$user instanceof ApiUser) {
            throw new \LogicException(sprintf('Expected %s, got %s.', ApiUser::class, get_debug_type($user)));
        }

        $session = $request->getSession();

        // Back to the page the user was on when the session was closed, if any.
        $targetPath = $this->getTargetPath($session, $firewallName);

        if (null !== $targetPath) {
            $this->removeTargetPath($session, $firewallName);

            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        $request->getSession()->set(
            SecurityRequestAttributes::AUTHENTICATION_ERROR,
            $exception,
        );

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    /**
     * @return array{string, string} email and password
     */
    private function extractCredentials(Request $request): array
    {
        $form = $this->formFactory->create(LoginType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE_INVALID_FORM);
        }

        $data = $form->getData();
        $email = is_array($data) ? ($data['email'] ?? null) : null;
        $password = is_array($data) ? ($data['password'] ?? null) : null;

        if (!is_string($email) || '' === $email || !is_string($password) || '' === $password) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE_INVALID_FORM);
        }

        return [$email, $password];
    }

    /**
     * @return array{string, int} JWT and its expiration timestamp
     *
     * @throws ExceptionInterface
     * @throws \JsonException
     */
    private function login(string $email, string $password): array
    {
        $response = $this->apiService->postLogin($email, $password);
        $statusCode = $response->getStatusCode();

        if (Response::HTTP_UNAUTHORIZED === $statusCode || Response::HTTP_UNPROCESSABLE_ENTITY === $statusCode) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE_BAD_CREDENTIALS);
        }

        if (Response::HTTP_OK !== $statusCode) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE_UNAVAILABLE);
        }

        $data = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);
        $token = is_array($data) ? ($data['token'] ?? null) : null;

        if (!is_string($token) || '' === $token) {
            throw new CustomUserMessageAuthenticationException('Réponse d’authentification invalide.');
        }

        $expiresAt = $this->getTokenExpiration($token);

        if (null === $expiresAt || $expiresAt <= time()) {
            throw new CustomUserMessageAuthenticationException('Jeton d’authentification expiré ou invalide.');
        }

        return [$token, $expiresAt];
    }

    /**
     * @throws ApiUnauthorizedException
     * @throws ExceptionInterface
     * @throws \JsonException
     */
    private function fetchUser(string $token): User
    {
        $response = $this->apiService->request('GET', '/me', [], $token);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE_PROFILE_UNAVAILABLE);
        }

        $data = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);

        if (!$this->isStringKeyedArray($data)) {
            throw new CustomUserMessageAuthenticationException('Réponse du profil utilisateur invalide.');
        }

        return $this->createUserFromApiResponse($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createUserFromApiResponse(array $data): User
    {
        $id = $data['id'] ?? null;
        $email = $data['email'] ?? null;
        $roles = $data['roles'] ?? null;

        if (
            !is_string($id)
            || '' === $id
            || !is_string($email)
            || '' === $email
            || !is_array($roles)
            || !array_is_list($roles)
        ) {
            throw new CustomUserMessageAuthenticationException('Les données du profil utilisateur sont invalides.');
        }

        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new CustomUserMessageAuthenticationException('Les rôles du profil utilisateur sont invalides.');
            }
        }

        $createdAt = null;

        if (null !== ($data['createdAt'] ?? null)) {
            if (!is_string($data['createdAt']) || '' === trim($data['createdAt'])) {
                throw new CustomUserMessageAuthenticationException('La date de création du profil est invalide.');
            }

            try {
                $createdAt = new \DateTimeImmutable($data['createdAt']);
            } catch (\Exception) {
                throw new CustomUserMessageAuthenticationException('La date de création du profil est invalide.');
            }
        }

        return new User($id, $email, $roles, $createdAt);
    }

    /**
     * @phpstan-assert-if-true array<string, mixed> $data
     */
    private function isStringKeyedArray(mixed $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        foreach (array_keys($data) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    private function getTokenExpiration(string $token): ?int
    {
        $parts = explode('.', $token);

        if (
            3 !== count($parts)
            || '' === $parts[0]
            || '' === $parts[1]
            || '' === $parts[2]
        ) {
            return null;
        }

        $payload = base64_decode(
            strtr($parts[1], '-_', '+/'),
            true,
        );

        if (false === $payload) {
            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) && is_int($data['exp'] ?? null)
            ? $data['exp']
            : null;
    }
}
