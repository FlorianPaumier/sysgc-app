<?php

declare(strict_types=1);

namespace App\Subscription\Presentation\Http\App;

use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Enum\SubscriptionTier;
use App\Identity\Domain\Enum\SubscriptionStatus;
use App\Subscription\Presentation\Form\BillingPlanType;
use App\Subscription\Application\StripeService;
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
            'action' => $this->generateUrl('app_billing_upgrade_post'),
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
            'subscriptionStatus' => $organization->subscriptionStatus(new \DateTimeImmutable(), $organization->getWorkers()->count(), $organization->getUsers()->count()),
            'workerCount' => $organization->getWorkers()->count(),
            'userCount' => $organization->getUsers()->count(),
        ]);
    }

    #[Route('/forfaits', name: 'billing_upgrade_post', methods: ['POST'])]
    public function upgradePost(Request $request): Response
    {
        $organization = $this->getOrganization();

        $form = $this->createForm(BillingPlanType::class, options: [
            'action' => $this->generateUrl('app_billing_upgrade_post'),
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
                    'subscriptionStatus' => $organization->subscriptionStatus(new \DateTimeImmutable(), $organization->getWorkers()->count(), $organization->getUsers()->count()),
                    'workerCount' => $organization->getWorkers()->count(),
                    'userCount' => $organization->getUsers()->count(),
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

            return $this->redirectToRoute('app_billing_upgrade');
        }
    }

    #[Route('/facturation/portail', name: 'billing_portal', methods: ['GET'])]
    public function portal(): Response
    {
        $organization = $this->getOrganization();

        if ($organization->getStripeCustomerId() === null) {
            $this->addFlash('info', 'Aucun abonnement actif. Choisissez un forfait pour commencer.');
            return $this->redirectToRoute('app_billing_upgrade');
        }

        try {
            return $this->redirect($this->stripeService->createCustomerPortal($organization));
        } catch (\Throwable) {
            $this->addFlash('error', 'Le portail de facturation est temporairement indisponible.');

            return $this->redirectToRoute('app_billing_upgrade');
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
        if (! $user instanceof User || ! $user->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        $organization = $user->getOrganization();
        if ($organization === null) {
            throw $this->createAccessDeniedException();
        }

        return $organization;
    }
}
