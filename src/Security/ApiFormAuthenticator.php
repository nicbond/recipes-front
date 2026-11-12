<?php

namespace App\Security;

use App\Form\Security\LoginType;
use App\Model\User\Identity;
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
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class ApiFormAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private FormFactoryInterface $formFactory,
        private ApiService $apiService,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'app_login' === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $form = $this->formFactory->create(LoginType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new CustomUserMessageAuthenticationException('Veuillez vérifier les données du formulaire.');
        }

        $data = $form->getData();

        if (!is_array($data)) {
            throw new CustomUserMessageAuthenticationException('Données du formulaire invalides.');
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (
            !is_string($email)
            || '' === $email
            || !is_string($password)
            || '' === $password
        ) {
            throw new CustomUserMessageAuthenticationException('Veuillez vérifier les données du formulaire.');
        }

        try {
            $loginResponse = $this->apiService->postLogin($email, $password);
            $statusCode = $loginResponse->getStatusCode();

            if (
                Response::HTTP_UNAUTHORIZED === $statusCode
                || Response::HTTP_UNPROCESSABLE_ENTITY === $statusCode
            ) {
                throw new CustomUserMessageAuthenticationException('Adresse e-mail ou mot de passe incorrect.');
            }

            if (Response::HTTP_OK !== $statusCode) {
                throw new CustomUserMessageAuthenticationException('Le service d’authentification est momentanément indisponible.');
            }

            $loginData = json_decode(
                $loginResponse->getContent(false),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($loginData)) {
                throw new CustomUserMessageAuthenticationException('Réponse d’authentification invalide.');
            }

            $token = $loginData['token'] ?? null;

            if (!is_string($token) || '' === $token) {
                throw new CustomUserMessageAuthenticationException('Réponse d’authentification invalide.');
            }

            $expiresAt = $this->getTokenExpiration($token);

            if (null === $expiresAt || $expiresAt <= time()) {
                throw new CustomUserMessageAuthenticationException('Jeton d’authentification expiré ou invalide.');
            }

            $profileResponse = $this->apiService->request(
                'GET',
                '/me',
                [],
                $token
            );

            if (Response::HTTP_OK !== $profileResponse->getStatusCode()) {
                throw new CustomUserMessageAuthenticationException('Impossible de récupérer votre profil utilisateur.');
            }

            $profileData = json_decode(
                $profileResponse->getContent(false),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!$this->isStringKeyedArray($profileData)) {
                throw new CustomUserMessageAuthenticationException('Réponse du profil utilisateur invalide.');
            }

            $user = $this->createUserFromApiResponse($profileData);
        } catch (CustomUserMessageAuthenticationException $exception) {
            throw $exception;
        } catch (ExceptionInterface|\JsonException) {
            throw new CustomUserMessageAuthenticationException('Le service d’authentification est momentanément indisponible.');
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $user->getEmail(),
                static fn (): ApiUser => new ApiUser(
                    $user,
                    $token,
                    $expiresAt
                )
            )
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createUserFromApiResponse(array $data): User
    {
        $id = $data['id'] ?? null;
        $email = $data['email'] ?? null;
        $roles = $data['roles'] ?? null;
        $identityData = $data['identity'] ?? null;

        if (
            !is_string($id)
            || '' === $id
            || !is_string($email)
            || '' === $email
            || !is_array($roles)
            || !array_is_list($roles)
            || !$this->isStringKeyedArray($identityData)
        ) {
            throw new CustomUserMessageAuthenticationException('Les données du profil utilisateur sont invalides.');
        }

        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new CustomUserMessageAuthenticationException('Les rôles du profil utilisateur sont invalides.');
            }
        }

        $identity = new Identity(
            $this->getNullableString($identityData, 'gender'),
            $this->getNullableString($identityData, 'firstname'),
            $this->getNullableString($identityData, 'lastname'),
            $this->parseBirthdate($identityData['birthdate'] ?? null),
        );

        $createdAt = null;

        if (isset($data['createdAt'])) {
            if (!is_string($data['createdAt'])) {
                throw new CustomUserMessageAuthenticationException('La date de création du profil est invalide.');
            }

            try {
                $createdAt = new \DateTimeImmutable($data['createdAt']);
            } catch (\Exception) {
                throw new CustomUserMessageAuthenticationException('La date de création du profil est invalide.');
            }
        }

        return new User(
            $id,
            $email,
            $roles,
            $createdAt,
            $identity
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getNullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (null !== $value && !is_string($value)) {
            throw new CustomUserMessageAuthenticationException(sprintf('La propriété "%s" du profil est invalide.', $key));
        }

        return $value;
    }

    private function parseBirthdate(mixed $birthdate): ?\DateTimeImmutable
    {
        if (null === $birthdate) {
            return null;
        }

        if (!is_string($birthdate)) {
            throw new CustomUserMessageAuthenticationException('La date de naissance du profil est invalide.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);

        if (false === $date || $date->format('Y-m-d') !== $birthdate) {
            throw new CustomUserMessageAuthenticationException('La date de naissance du profil est invalide.');
        }

        return $date;
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

        if (!isset($parts[1])) {
            return null;
        }

        $payload = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/')),
            true
        );

        return is_array($payload) && is_int($payload['exp'] ?? null)
            ? $payload['exp']
            : null;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var ApiUser $user */
        $user = $token->getUser();

        $request->getSession()->set('jwt_token', $user->getToken());

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(
            SecurityRequestAttributes::AUTHENTICATION_ERROR,
            $exception
        );

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
