<?php

namespace App\Controller;

use App\Form\Security\LoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // If the user is already logged in, redirect them to the Homepage
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(LoginType::class, [
            'attr' => ['novalidate' => 'novalidate'],
        ]);

        // Retrieves the authentication error if the authenticator failed
        $error = $authenticationUtils->getLastAuthenticationError();
        $statusCode = Response::HTTP_OK;

        if ($error) {
            $form->addError(new FormError($error->getMessageKey()));

            $statusCode = str_contains($error->getMessageKey(), 'indisponible')
                ? Response::HTTP_SERVICE_UNAVAILABLE
                : Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('security/login.html.twig', [
            'form' => $form->createView(),
        ], new Response(status: $statusCode));
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode est interceptée par le firewall.');
    }
}
