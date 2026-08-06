<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Exception\NotImplementedException;
use App\Form\BillingPlanType;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BillingController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    #[Route('/forfaits', name: 'billing_upgrade', methods: ['GET'])]
    public function upgrade(Request $request): Response
    {
        $organization = $this->getOrganization();

        $form = $this->createForm(BillingPlanType::class, options: [
            'action' => $this->generateUrl('billing_upgrade_post'),
        ]);

        return $this->render('billing/upgrade.html.twig', [
            'form' => $form,
            'tiers' => SubscriptionTier::cases(),
            'prices' => [
                'starter' => '79',
                'pro' => '149',
                'business' => '199',
            ],
            'currentTier' => $organization->getSubscriptionTier(),
        ]);
    }

    #[Route('/forfaits', name: 'billing_upgrade_post', methods: ['POST'])]
    public function upgradePost(Request $request): Response
    {
        $organization = $this->getOrganization();

        $form = $this->createForm(BillingPlanType::class, options: [
            'action' => $this->generateUrl('billing_upgrade_post'),
        ]);
        $form->handleRequest($request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            return $this->render(
                'billing/upgrade.html.twig',
                [
                    'form' => $form,
                    'tiers' => SubscriptionTier::cases(),
                    'prices' => [
                        'starter' => '79',
                        'pro' => '149',
                        'business' => '199',
                    ],
                    'currentTier' => $organization->getSubscriptionTier(),
                ],
                new Response(status: 422),
            );
        }

        /** @var SubscriptionTier $tier */
        $tier = $form->get('tier')->getData();
        $priceId = $this->stripeService->priceIdForTier($tier);

        try {
            $url = $this->stripeService->createCheckoutSession($organization, $priceId);

            return $this->redirect($url);
        } catch (\Throwable) {
            $this->addFlash('error', 'Le paiement n\'a pas pu etre initie. Reessayez.');

            return $this->redirectToRoute('billing_upgrade');
        }
    }

    #[Route('/facturation/portail', name: 'billing_portal', methods: ['GET'])]
    public function portal(): Response
    {
        $organization = $this->getOrganization();

        try {
            $url = $this->stripeService->createCustomerPortal($organization);

            return $this->redirect($url);
        } catch (NotImplementedException) {
            $this->addFlash('info', 'Le portail client sera bientot disponible.');

            return $this->redirectToRoute('billing_upgrade');
        }
    }

    #[Route('/facturation/succes', name: 'billing_success', methods: ['GET'])]
    public function success(): Response
    {
        return $this->render('billing/success.html.twig');
    }

    private function getOrganization(): Organization
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $organization = $user->getOrganization();
        if ($organization === null) {
            throw $this->createAccessDeniedException();
        }

        return $organization;
    }
}
