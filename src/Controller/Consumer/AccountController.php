<?php

namespace App\Controller\Consumer;

use App\Form\Consumer\IdentityType;
use App\Model\User\Identity;
use App\Security\User\ApiUser;
use App\Service\ConsumerProfileService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class AccountController extends AbstractController
{
    private const IDENTITY_FIELD_MAP = [
        'identity.gender' => 'gender',
        'identity.firstName' => 'firstname',
        'identity.lastName' => 'lastname',
        'identity.birthdate' => 'birthdate',
    ];

    #[Route('/mon-compte', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ConsumerProfileService $consumerProfileService,
        LoggerInterface $logger,
    ): Response {
        $apiUser = $this->getUser();

        if (!$apiUser instanceof ApiUser) {
            throw $this->createAccessDeniedException();
        }

        try {
            $identity = $consumerProfileService->getIdentity($apiUser);
        } catch (ExceptionInterface|\UnexpectedValueException $exception) {
            $logger->error(
                'Erreur lors du chargement du profil du consommateur.',
                ['exception' => $exception],
            );

            $this->addFlash('danger', 'Une erreur est survenue lors du chargement de votre compte. Veuillez réessayer.');

            return $this->redirectToRoute('app_home');
        }

        $editIdentity = 'identity' === $request->query->get('edit');

        // The form works on a copy: $identity keeps the values confirmed by the API.
        $form = $this->createForm(IdentityType::class, clone $identity);

        if ($editIdentity) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $submittedIdentity = $form->getData();

                if (!$submittedIdentity instanceof Identity) {
                    throw new \LogicException('Le formulaire doit retourner une instance de '.Identity::class.'.');
                }

                try {
                    $violations = $consumerProfileService->updateIdentity($apiUser, $submittedIdentity);

                    if ([] === $violations) {
                        $this->addFlash('success', 'Vos informations personnelles ont bien été mises à jour.');

                        return $this->redirectToRoute('app_account');
                    }

                    $this->addViolationsToForm($form, $violations);
                } catch (ExceptionInterface|\UnexpectedValueException $exception) {
                    $logger->error(
                        'Erreur lors de la mise à jour de l’identité du consommateur.',
                        ['exception' => $exception],
                    );

                    $this->addFlash('danger', 'Une erreur est survenue lors de la mise à jour de vos informations personnelles. Veuillez réessayer.');
                }
            }
        }

        $statusCode = $form->isSubmitted() && !$form->isValid()
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK;

        return $this->render('consumer/account/index.html.twig', [
            'identity' => $identity,
            'editIdentity' => $editIdentity,
            'identityForm' => $form->createView(),
        ], new Response(status: $statusCode));
    }

    /**
     * @param list<array{propertyPath: string, message: string}> $violations
     */
    private function addViolationsToForm(FormInterface $form, array $violations): void
    {
        foreach ($violations as $violation) {
            $field = self::IDENTITY_FIELD_MAP[$violation['propertyPath']] ?? null;
            $target = null !== $field && $form->has($field) ? $form->get($field) : $form;

            $target->addError(new FormError($violation['message']));
        }
    }
}
