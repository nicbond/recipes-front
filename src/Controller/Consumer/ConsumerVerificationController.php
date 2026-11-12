<?php

namespace App\Controller\Consumer;

use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConsumerVerificationController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(resolve:API_BACK_PUBLIC_HOST)%')]
        private readonly string $apiBackPublicHost,
    ) {
    }

    #[Route('/verification-compte', name: 'app_verify_consumer_account', methods: ['GET'])]
    public function verify(Request $request): Response
    {
        $verificationData = [
            'id' => $request->query->get('id'),
            'token' => $request->query->get('token'),
            'expires' => $request->query->get('expires'),
            'signature' => $request->query->get('signature'),
        ];

        $isValidRequest = null !== $verificationData['id']
            && null !== $verificationData['token']
            && null !== $verificationData['expires']
            && null !== $verificationData['signature'];

        return $this->render('consumer/verification.html.twig', [
            'verificationData' => $verificationData,
            'isValidRequest' => $isValidRequest,
            'queryString' => $request->getQueryString(),
        ]);
    }

    #[Route('/verification-compte', name: 'app_verify_consumer_account_submit', methods: ['POST'])]
    public function activate(Request $request, ApiService $apiService): Response
    {
        $id = $request->request->getString('id');

        if (
            '' === $id
            || !$this->isCsrfTokenValid(
                'activate_consumer_'.$id,
                $request->request->getString('_csrf_token')
            )
        ) {
            $this->addFlash('danger', 'La demande d’activation est invalide.');

            return $this->redirectToRoute('app_verify_consumer_account');
        }

        $verificationQuery = $request->request->getString('verification_query');

        if ('' === $verificationQuery) {
            $this->addFlash('danger', 'Le lien de vérification est incomplet.');

            return $this->redirectToRoute('app_verify_consumer_account');
        }

        $apiResponse = $apiService->request(
            'POST',
            '/consumers/activate?'.$verificationQuery,
            [
                'headers' => [
                    'Host' => $this->apiBackPublicHost,
                ],
            ]
        );

        $statusCode = $apiResponse->getStatusCode();
        $content = $apiResponse->getContent(false);

        $data = json_decode($content, true);

        $message = is_array($data)
            && isset($data['message'])
            && is_string($data['message'])
                ? $data['message']
                : 'Une erreur est survenue lors de l’activation de votre compte.';

        if (Response::HTTP_OK === $statusCode) {
            $this->addFlash('success', $message);

            return $this->redirectToRoute(
                'app_verify_consumer_account_completed'
            );
        }

        $this->addFlash('danger', $message);

        return $this->redirectToRoute('app_verify_consumer_account');
    }

    #[Route('/verification-compte/terminee', name: 'app_verify_consumer_account_completed', methods: ['GET'])]
    public function completed(): Response
    {
        return $this->render('consumer/verification_completed.html.twig');
    }
}
