<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LegalController extends AbstractController
{
    #[Route('/terms', name: 'terms', methods: ['GET'])]
    public function terms(): Response
    {
        $html = file_get_contents(__DIR__ . '/../../public/terms.html');

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    #[Route('/privacy-policy', name: 'privacy_policy', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        $html = file_get_contents(__DIR__ . '/../../public/privacy-policy.html');

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
}
