<?php

declare(strict_types=1);

namespace App\Workforce\Presentation\Http;

use App\Identity\Domain\Model\User;
use App\Workforce\Domain\Model\Worker;
use App\Workforce\Presentation\Form\WorkerType;
use App\Workforce\Infrastructure\Doctrine\WorkerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/travailleurs', name: 'worker_')]
final class WorkerController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(WorkerRepository $workerRepository): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        if (! $user->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('worker/index.html.twig', [
            'workers' => $workerRepository->findBy(['organization' => $user->getOrganization()], ['name' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $worker = new Worker();
        $user = $this->getUser();
        \assert($user instanceof User);
        if (! $user->isAdmin()) {
            throw $this->createAccessDeniedException();
        }
        $worker->setOrganization($user->getOrganization());
        $form = $this->createForm(WorkerType::class, $worker);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($worker);
            $entityManager->flush();

            return $this->redirectToRoute('app_worker_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('worker/new.html.twig', [
            'worker' => $worker,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Worker $worker, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || ! $user->isAdmin() || $worker->getOrganization() !== $user->getOrganization()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(WorkerType::class, $worker);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_worker_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('worker/edit.html.twig', [
            'worker' => $worker,
            'form' => $form,
        ]);
    }

}
