<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/maintenance', name: 'maintenance')]
    public function maintenance(): Response
    {
        return $this->render('error/maintenance.html.twig', [], new Response('', Response::HTTP_SERVICE_UNAVAILABLE));
    }
}
