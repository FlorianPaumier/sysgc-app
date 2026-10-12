<?php

declare(strict_types=1);

namespace App\Commercial\Presentation\Http;

use App\Commercial\Application\InvoicePdfGenerator;
use App\Commercial\Application\InvoiceMilestoneCalculator;
use App\Commercial\Application\InvoiceTaxCalculator;
use App\Commercial\Application\ChangeOrderService;
use App\Commercial\Domain\Model\ChangeOrder;
use App\Commercial\Domain\Model\Invoice;
use App\Commercial\Infrastructure\Doctrine\ChangeOrderRepository;
use App\Commercial\Infrastructure\Doctrine\InvoiceRepository;
use App\Commercial\Presentation\Form\ChangeOrderType;
use App\Commercial\Presentation\Form\InvoiceType;
use App\Identity\Domain\Model\User;
use App\Project\Domain\Model\Project;
use App\Project\Infrastructure\Security\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/chantiers/{id}', requirements: ['id' => '[0-9a-f-]{36}'])]
final class CommercialController extends AbstractController
{
    #[Route('/avenants', name: 'change_order_index', methods: ['GET'])]
    public function changeOrders(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('commercial/change_order_index.html.twig', [
            'project' => $project,
            'isArchived' => $project->getStatus()->value === 'archived',
        ]);
    }

    #[Route('/avenants/nouveau', name: 'change_order_new', methods: ['GET', 'POST'])]
    public function newChangeOrder(Request $request, Project $project, ChangeOrderService $changeOrders): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $this->denyIfArchived($project);
        $changeOrder = new ChangeOrder()->setProject($project)->setRequestedBy($this->userName());
        $form = $this->createForm(ChangeOrderType::class, $changeOrder);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $changeOrders->create($changeOrder, $this->requireUser());
            $this->addFlash('success', 'L’avenant a été créé.');

            return $this->redirectToRoute('app_change_order_index', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commercial/change_order_new.html.twig', ['project' => $project, 'form' => $form]);
    }

    #[Route('/avenants/{changeOrderId}', name: 'change_order_edit', methods: ['POST'], requirements: ['changeOrderId' => '[0-9a-f-]{36}'])]
    public function editChangeOrder(Request $request, Project $project, string $changeOrderId, ChangeOrderRepository $repository, ChangeOrderService $changeOrders): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $changeOrder = $this->findChangeOrder($project, $changeOrderId, $repository);
        $this->denyIfArchived($project);

        if ($request->isMethod('POST')) {
            $token = $request->getPayload()->getString('_token');
            if (! $this->isCsrfTokenValid('change-order-'.$changeOrder->getId(), $token)) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $action = $request->getPayload()->getString('workflow_action');
            if ($action === 'approve') {
                $changeOrders->approve($changeOrder, $this->requireUser());
                $this->addFlash('success', 'L’avenant a été approuvé.');
            } elseif ($action === 'reject') {
                $changeOrders->reject($changeOrder, $this->requireUser(), $request->getPayload()->getString('rejection_reason'));
                $this->addFlash('success', 'L’avenant a été rejeté.');
            } elseif ($action === 'reopen') {
                $changeOrders->reopen($changeOrder, $this->requireUser());
                $this->addFlash('success', 'L’avenant a été rouvert.');
            } elseif ($action === 'invoiced') {
                $changeOrders->markInvoiced($changeOrder, $this->requireUser());
                $this->addFlash('success', 'L’avenant a été marqué comme facturé.');
            } else {
                $form = $this->createForm(ChangeOrderType::class, $changeOrder, ['csrf_token_id' => 'change-order-'.$changeOrder->getId()]);
                $before = ['title' => $changeOrder->getTitle(), 'description' => $changeOrder->getDescription(), 'costImpact' => $changeOrder->getCostImpact(), 'scheduleImpactDays' => $changeOrder->getScheduleImpactDays()];
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    $after = ['title' => $changeOrder->getTitle(), 'description' => $changeOrder->getDescription(), 'costImpact' => $changeOrder->getCostImpact(), 'scheduleImpactDays' => $changeOrder->getScheduleImpactDays()];
                    $changeOrder->setTitle($before['title'])->setDescription($before['description'])->setCostImpact($before['costImpact'])->setScheduleImpactDays($before['scheduleImpactDays']);
                    $changeOrders->update($changeOrder, $this->requireUser(), $after);
                    $this->addFlash('success', 'L’avenant a été mis à jour.');
                } else {
                    return $this->render('commercial/change_order_show.html.twig', ['project' => $project, 'changeOrder' => $changeOrder, 'form' => $form]);
                }
            }

            return $this->redirectToRoute('app_change_order_edit', ['id' => $project->getId(), 'changeOrderId' => $changeOrder->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commercial/change_order_show.html.twig', [
            'project' => $project,
            'changeOrder' => $changeOrder,
            'form' => $this->createForm(ChangeOrderType::class, $changeOrder),
        ]);
    }

    #[Route('/avenants/{changeOrderId}', name: 'change_order_show', methods: ['GET'], requirements: ['changeOrderId' => '[0-9a-f-]{36}'])]
    public function showChangeOrder(Project $project, string $changeOrderId, ChangeOrderRepository $changeOrders): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        $changeOrder = $this->findChangeOrder($project, $changeOrderId, $changeOrders);

        return $this->render('commercial/change_order_show.html.twig', [
            'project' => $project,
            'changeOrder' => $changeOrder,
            'form' => $project->getStatus()->value === 'archived' || ! $this->isGranted(ProjectVoter::EDIT, $project)
                ? null
                : $this->createForm(ChangeOrderType::class, $changeOrder, ['csrf_token_id' => 'change-order-'.$changeOrder->getId()]),
            'isArchived' => $project->getStatus()->value === 'archived',
        ]);
    }

    #[Route('/factures', name: 'invoice_index', methods: ['GET'])]
    public function invoices(Project $project, InvoiceTaxCalculator $taxes): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $subtotal = '0.00';
        foreach ($project->getInvoices() as $invoice) {
            $subtotal = number_format((float) $subtotal + (float) $invoice->getAmount(), 2, '.', '');
        }

        return $this->render('commercial/invoice_index.html.twig', [
            'project' => $project,
            'taxSummary' => $taxes->calculate($subtotal),
            'canManageInvoices' => $this->canManageInvoices($project),
            'isReadOnly' => ! $this->canManageInvoices($project),
        ]);
    }

    #[Route('/factures/nouvelle', name: 'invoice_new', methods: ['GET', 'POST'])]
    public function newInvoice(Request $request, Project $project, InvoiceRepository $invoices, InvoiceMilestoneCalculator $milestones): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        if (! $this->canManageInvoices($project)) {
            throw $this->createAccessDeniedException('La facturation est en lecture seule sans forfait actif.');
        }
        $invoice = new Invoice()->setProject($project);
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $invoice->setAmount($milestones->amountFor($project->getBudget(), $invoice->getPercentage()));
            $invoices->save($invoice);
            $this->addFlash('success', 'La facture a été créée.');

            return $this->redirectToRoute('app_invoice_index', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commercial/invoice_new.html.twig', ['project' => $project, 'form' => $form, 'budget' => $project->getBudget()]);
    }

    #[Route('/factures/{invoiceId}/statut', name: 'invoice_status', methods: ['POST'], requirements: ['invoiceId' => '[0-9a-f-]{36}'])]
    public function invoiceStatus(Request $request, Project $project, string $invoiceId, InvoiceRepository $invoices): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        if (! $this->canManageInvoices($project)) {
            throw $this->createAccessDeniedException('La facturation est en lecture seule.');
        }
        $invoice = $invoices->find($this->uuid($invoiceId));
        if (! $invoice instanceof Invoice || $invoice->getProject()?->getId()?->equals($project->getId()) !== true) {
            throw $this->createNotFoundException();
        }
        if (! $this->isCsrfTokenValid('invoice-'.$invoice->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        match ($request->getPayload()->getString('status')) {
            'sent' => $invoice->markSent(),
            'paid' => $invoice->markPaid(),
            'overdue' => $invoice->setStatus(\App\Commercial\Domain\Enum\InvoiceStatus::OVERDUE),
            default => throw $this->createNotFoundException(),
        };
        $invoices->save($invoice);

        return $this->redirectToRoute('app_invoice_index', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/factures/{invoiceId}/pdf', name: 'invoice_pdf', methods: ['GET'], requirements: ['invoiceId' => '[0-9a-f-]{36}'])]
    public function invoicePdf(Project $project, string $invoiceId, InvoiceRepository $invoices, InvoicePdfGenerator $pdfs): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        $invoice = $invoices->find($this->uuid($invoiceId));
        if (!$invoice instanceof Invoice || $invoice->getProject()?->getId()?->equals($project->getId()) !== true) {
            throw $this->createNotFoundException();
        }

        return new Response($pdfs->generate($invoice), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="facture-'.$invoice->getId().'.pdf"',
        ]);
    }

    private function uuid(string $id): Uuid
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        return Uuid::fromString($id);
    }

    private function userName(): string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getEmail() : 'Système';
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function denyIfArchived(Project $project): void
    {
        if ($project->getStatus()->value === 'archived') {
            throw $this->createAccessDeniedException('Un chantier archivé est en lecture seule.');
        }
    }

    private function findChangeOrder(Project $project, string $id, ChangeOrderRepository $repository): ChangeOrder
    {
        $changeOrder = $repository->find($this->uuid($id));
        if (! $changeOrder instanceof ChangeOrder || $changeOrder->getProject()?->getId()?->equals($project->getId()) !== true) {
            throw $this->createNotFoundException();
        }

        return $changeOrder;
    }

    private function canManageInvoices(Project $project): bool
    {
        return $project->getStatus()->value !== 'archived'
            && $this->isGranted(ProjectVoter::EDIT, $project)
            && !$project->getOrganization()->isReadOnly()
            && $project->getOrganization()->getSubscriptionTier() !== null;
    }
}
