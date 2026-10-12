<?php

declare(strict_types=1);

namespace App\Compliance\Presentation\Http;

use App\Compliance\Application\BatchCsvImporter;
use App\Compliance\Application\LicenseVerifier;
use App\Compliance\Domain\Model\Subcontractor;
use App\Compliance\Infrastructure\Doctrine\SubcontractorRepository;
use App\Compliance\Infrastructure\RbqEvidenceUploader;
use App\Compliance\Presentation\Form\SubcontractorImportType;
use App\Compliance\Presentation\Form\SubcontractorType;
use App\Identity\Domain\Model\User;
use App\Project\Domain\Model\Project;
use App\Project\Infrastructure\Security\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/chantiers/{id}/rbq', name: 'rbq_')]
final class RbqController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDirectory)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('rbq/index.html.twig', ['project' => $project]);
    }

    #[Route('/ajouter', name: 'add', methods: ['GET', 'POST'])]
    public function add(Request $request, Project $project, EntityManagerInterface $entityManager, RbqEvidenceUploader $evidenceUploader): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $this->denyIfArchived($project);
        $subcontractor = new Subcontractor();
        $form = $this->createForm(SubcontractorType::class, $subcontractor);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $manualVerification = true === $form->get('manuallyVerified')->getData();
            $sourceData = $form->get('manualVerificationSource')->getData();
            $source = is_string($sourceData) ? trim($sourceData) : '';
            if ($manualVerification && '' === $source) {
                $form->get('manualVerificationSource')->addError(new \Symfony\Component\Form\FormError('La source est obligatoire pour une vérification manuelle.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if (true === $form->get('manuallyVerified')->getData()) {
                $evidenceOriginalName = null;
                $evidencePath = null;
                $evidence = $form->get('manualVerificationEvidence')->getData();
                if ($evidence instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    $metadata = $evidenceUploader->upload($evidence, $subcontractor);
                    $evidenceOriginalName = $metadata['originalName'];
                    $evidencePath = $metadata['path'];
                }
                $actor = $this->getUser();
                if (! $actor instanceof User) {
                    throw $this->createAccessDeniedException();
                }
                $sourceData = $form->get('manualVerificationSource')->getData();
                $notesData = $form->get('manualVerificationNotes')->getData();
                $subcontractor->recordManualVerification(
                    is_string($sourceData) ? $sourceData : '',
                    is_string($notesData) && '' !== $notesData ? $notesData : null,
                    $evidenceOriginalName,
                    $evidencePath,
                    $actor,
                    new \DateTimeImmutable(),
                );
            }
            $project->addSubcontractor($subcontractor);
            $entityManager->persist($subcontractor);
            $entityManager->flush();
            $this->addFlash('success', 'Le sous-traitant a été ajouté.');

            return $this->redirectToRoute('app_rbq_index', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rbq/add.html.twig', ['project' => $project, 'form' => $form]);
    }

    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function uploadCsv(Request $request, Project $project, BatchCsvImporter $importer): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $this->denyIfArchived($project);
        $form = $this->createForm(SubcontractorImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('csv')->getData();
            $contents = $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile ? file_get_contents($file->getPathname()) : false;
            if (!is_string($contents)) {
                $form->addError(new \Symfony\Component\Form\FormError('Le fichier CSV est illisible.'));
            } else {
                try {
                    $result = $importer->import($project, $contents);
                    $this->addFlash('success', sprintf('%d sous-traitant(s) importé(s), %d vérification(s) mise(s) en file.', $result['created'] + $result['updated'], $result['queued']));

                    return $this->redirectToRoute('app_rbq_index', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
                } catch (\InvalidArgumentException $exception) {
                    $form->addError(new \Symfony\Component\Form\FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('rbq/upload.html.twig', ['project' => $project, 'form' => $form]);
    }

    #[Route('/{subId}/verifier', name: 'verify_single', methods: ['POST'])]
    public function verifySingle(Request $request, Project $project, string $subId, SubcontractorRepository $subcontractors, \Symfony\Component\Messenger\MessageBusInterface $messages): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $this->denyIfArchived($project);
        $subcontractor = $subcontractors->findOneForProjectById($project, Uuid::fromString($subId));
        if (!$subcontractor instanceof Subcontractor) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('verify-rbq-'.$subcontractor->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $messages->dispatch(new \App\Compliance\Message\CheckSingleLicenseMessage($subcontractor->getId()->toRfc4122()));
        $this->addFlash('success', 'La vérification RBQ a été mise en file.');

        return $this->redirectToRoute('app_rbq_index', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/tout-verifier', name: 'verify_all', methods: ['POST'])]
    public function verifyAll(Request $request, Project $project, LicenseVerifier $verifier): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $this->denyIfArchived($project);
        if ($this->isCsrfTokenValid('verify-all-rbq-'.$project->getId(), $request->getPayload()->getString('_token'))) {
            $result = $verifier->verifyProject($project);
            $this->addFlash('success', sprintf('%d vérification(s) RBQ mise(s) en file.', $result['queued']));
        }

        return $this->redirectToRoute('app_rbq_index', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{subId}/evidence', name: 'evidence', methods: ['GET'])]
    public function evidence(Project $project, string $subId, SubcontractorRepository $subcontractors): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        if (!Uuid::isValid($subId)) {
            throw $this->createNotFoundException();
        }
        $subcontractor = $subcontractors->findOneForProjectById($project, Uuid::fromString($subId));
        if (!$subcontractor instanceof Subcontractor || $subcontractor->getEvidencePath() === null) {
            throw $this->createNotFoundException();
        }

        $storageRoot = realpath($this->projectDirectory.'/var/uploads');
        $path = realpath($this->projectDirectory.'/var/uploads/'.ltrim($subcontractor->getEvidencePath(), '/'));
        if ($storageRoot === false || $path === false || !str_starts_with($path, $storageRoot.'/') || !is_file($path)) {
            throw $this->createNotFoundException();
        }

        return $this->file($path, $subcontractor->getEvidenceOriginalName() ?? basename($path));
    }

    private function denyIfArchived(Project $project): void
    {
        if ($project->getStatus()->value === 'archived') {
            throw $this->createAccessDeniedException('Un chantier archivé est en lecture seule.');
        }
    }
}
