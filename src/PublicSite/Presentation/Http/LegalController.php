<?php

declare(strict_types=1);

namespace App\PublicSite\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    public function __construct(
        private readonly string $operatorName,
        private readonly string $operatorAddress,
        private readonly string $operatorEmail,
        private readonly string $privacyOfficerName,
        private readonly string $privacyOfficerEmail,
        private readonly string $effectiveDate,
    ) {
    }

    #[Route('/confidentialite', name: 'privacy_policy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig', $this->legalIdentity());
    }

    #[Route('/conditions-utilisation', name: 'terms_of_use', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig', $this->legalIdentity());
    }

    /** @return array<string, string> */
    private function legalIdentity(): array
    {
        return [
            'operator_name' => $this->operatorName,
            'operator_address' => $this->operatorAddress,
            'operator_email' => $this->operatorEmail,
            'privacy_officer_name' => $this->privacyOfficerName,
            'privacy_officer_email' => $this->privacyOfficerEmail,
            'effective_date' => $this->effectiveDate,
        ];
    }
}
