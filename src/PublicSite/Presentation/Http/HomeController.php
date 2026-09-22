<?php

declare(strict_types=1);

namespace App\PublicSite\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ["GET"])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/a-propos', name: 'about', methods: ["GET"])]
    public function about(): Response
    {
        return $this->render('home/about.html.twig');
    }
}
