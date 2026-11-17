<?php

namespace App\Controller\Consumer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_account')]
    public function index(): Response
    {
        return $this->render('consumer/account/index.html.twig');
    }
}
