<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\OrganizationSettingsType;

final class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/organisation', name: 'organisation', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $organization = $user->getOrganization();
        if (null === $organization) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(OrganizationSettingsType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Vos modifications ont été enregistrées.');

            return $this->redirectToRoute('organisation');
        }

        return $this->render('organization/settings.html.twig', [
            'form' => $form,
            'organization' => $organization,
        ]);
    }
}
