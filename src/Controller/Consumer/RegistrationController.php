<?php

namespace App\Controller\Consumer;

use App\Form\Consumer\RegistrationType;
use App\Model\User\Identity;
use App\Model\User\RegistrationConsumer;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_consumer_registration', methods: ['GET', 'POST'])]
    public function register(Request $request, ApiService $apiService): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $registration = new RegistrationConsumer('', '', new Identity());

        $form = $this->createForm(RegistrationType::class, $registration, [
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var RegistrationConsumer $registration */
            $registration = $form->getData();

            try {
                $response = $apiService->request('POST', '/consumers', [
                    'json' => $this->prepareRegistrationPayload($registration),
                ]);

                $statusCode = $response->getStatusCode();

                if (Response::HTTP_CREATED === $statusCode) {
                    $this->addFlash(
                        'success',
                        'Votre compte a bien été créé. Un e-mail de validation vient de vous être envoyé. Veuillez consulter votre boîte mail pour activer votre compte.',
                    );

                    return $this->redirectToRoute('app_login');
                }

                if (Response::HTTP_UNPROCESSABLE_ENTITY === $statusCode) {
                    $this->addApiValidationErrors(
                        $form,
                        $response->getContent(false),
                    );
                } elseif ($statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
                    return $this->renderRegistrationForm(
                        $form,
                        Response::HTTP_SERVICE_UNAVAILABLE,
                    );
                } else {
                    $form->addError(new FormError(
                        'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.',
                    ));
                }
            } catch (TransportExceptionInterface) {
                return $this->renderRegistrationForm(
                    $form,
                    Response::HTTP_SERVICE_UNAVAILABLE,
                );
            }
        }

        return $this->renderRegistrationForm(
            $form,
            $form->isSubmitted()
                ? Response::HTTP_UNPROCESSABLE_ENTITY
                : Response::HTTP_OK,
        );
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function renderRegistrationForm(FormInterface $form, int $statusCode = Response::HTTP_OK): Response
    {
        if (Response::HTTP_SERVICE_UNAVAILABLE === $statusCode) {
            $form->addError(new FormError(
                'Le service est momentanément indisponible. Veuillez réessayer ultérieurement.',
            ));
        }

        return $this->render('consumer/registration.html.twig', [
            'form' => $form->createView(),
        ], new Response(status: $statusCode));
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function addApiValidationErrors(FormInterface $form, string $content): void
    {
        $data = json_decode($content, true);

        if (
            !is_array($data)
            || !isset($data['violations'])
            || !is_array($data['violations'])
        ) {
            $form->addError(new FormError(
                'Les informations saisies ne sont pas valides. Veuillez les vérifier.',
            ));

            return;
        }

        foreach ($data['violations'] as $violation) {
            if (
                !is_array($violation)
                || !isset($violation['propertyPath'], $violation['message'])
                || !is_string($violation['propertyPath'])
                || !is_string($violation['message'])
            ) {
                continue;
            }

            $propertyPath = $violation['propertyPath'];
            $message = $violation['message'];

            if (str_starts_with($propertyPath, 'identity.')) {
                $field = substr($propertyPath, strlen('identity.'));

                if ($form->get('identity')->has($field)) {
                    $form->get('identity')->get($field)->addError(
                        new FormError($message),
                    );

                    continue;
                }
            } elseif ($form->has($propertyPath)) {
                $form->get($propertyPath)->addError(
                    new FormError($message),
                );

                continue;
            }

            $form->addError(new FormError($message));
        }
    }

    /**
     * @return array{
     *     email: string,
     *     password: string,
     *     identity: array{
     *         gender: ?string,
     *         firstname: ?string,
     *         lastname: ?string,
     *         birthdate: ?string
     *     }
     * }
     */
    private function prepareRegistrationPayload(RegistrationConsumer $registration): array
    {
        $identity = $registration->getIdentity();

        return [
            'email' => $registration->getEmail(),
            'password' => $registration->getPassword(),
            'identity' => [
                'gender' => $identity->getGender(),
                'firstname' => $identity->getFirstname(),
                'lastname' => $identity->getLastname(),
                'birthdate' => $identity->getBirthdate()?->format('Y-m-d'),
            ],
        ];
    }
}
