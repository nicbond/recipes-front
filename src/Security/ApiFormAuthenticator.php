<?php

namespace App\Security;

use App\Form\Security\LoginType;
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
            $apiResponse = $this->apiService->postLogin($email, $password);
            $statusCode = $apiResponse->getStatusCode();

            if (
                Response::HTTP_UNAUTHORIZED === $statusCode
                || Response::HTTP_UNPROCESSABLE_ENTITY === $statusCode
            ) {
                throw new CustomUserMessageAuthenticationException('Adresse e-mail ou mot de passe incorrect.');
            }

            if (Response::HTTP_OK !== $statusCode) {
                throw new CustomUserMessageAuthenticationException('Le service d’authentification est momentanément indisponible.');
            }

            $responseData = $apiResponse->toArray(false);
        } catch (CustomUserMessageAuthenticationException $exception) {
            throw $exception;
        } catch (ExceptionInterface) {
            throw new CustomUserMessageAuthenticationException('Le service d’authentification est momentanément indisponible.');
        }

        $token = $responseData['token'] ?? null;

        if (!is_string($token) || '' === $token) {
            throw new CustomUserMessageAuthenticationException('Réponse d’authentification invalide.');
        }

        $expiresAt = $this->getTokenExpiration($token);

        if (null === $expiresAt || $expiresAt <= time()) {
            throw new CustomUserMessageAuthenticationException('Jeton d’authentification expiré ou invalide.');
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $email,
                static fn (): ApiUser => new ApiUser(
                    $email,
                    ['ROLE_CONSUMER'],
                    $token,
                    $expiresAt
                )
            )
        );
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
