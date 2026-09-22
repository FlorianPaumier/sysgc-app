<?php

declare(strict_types=1);

namespace App\PublicSite\Presentation\Http;

use App\PublicSite\Application\DTO\ContactDTO;
use App\PublicSite\Presentation\Form\ContactType;
use App\PublicSite\Application\Message\SendContactEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    private const int RATE_LIMIT_MAX = 3;
    private const int RATE_LIMIT_WINDOW = 3600; // 1 hour in seconds

    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $contact = new ContactDTO;
        $form = $this->createForm(ContactType::class, $contact);

        return $this->render('contact.html.twig', [
            'form' => $form,
            'rate_limited' => $this->isRateLimited($request),
        ]);
    }

    #[Route('/contact', name: 'app_contact_post', methods: ['POST'])]
    public function contact(Request $request, MessageBusInterface $messageBus): Response
    {
        $rateLimited = $this->isRateLimited($request);

        $contact = new ContactDTO;
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Honeypot: the hidden 'website' field must be empty for real humans.
            $honeypot = $form->get('website')->getData();
            if (($honeypot === null || $honeypot === '') && !$rateLimited) {
                $messageBus->dispatch(new SendContactEmail(
                    $contact,
                    'emails/contact.html.twig',
                ));

                $this->recordSubmission($request);

                $this->addFlash(
                    'success',
                    'Votre message a été envoyé. Nous vous répondrons dans les plus brefs délais.',
                );
            }

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact.html.twig', [
            'form' => $form,
            'rate_limited' => $rateLimited,
        ]);
    }

    /**
     * @param Request $request
     * @return int[] Timestamps of recent submissions in the current window
     */
    private function getRecentAttempts(Request $request): array
    {
        /** @var int[] $attempts */
        $attempts = (array) $request->getSession()->get('contact_attempts', []);

        $now = time();
        $attempts = array_values(array_filter(
            $attempts,
            static fn (int $timestamp): bool => ($now - $timestamp) < self::RATE_LIMIT_WINDOW,
        ));

        return $attempts;
    }

    private function isRateLimited(Request $request): bool
    {
        return count($this->getRecentAttempts($request)) >= self::RATE_LIMIT_MAX;
    }

    private function recordSubmission(Request $request): void
    {
        $attempts = $this->getRecentAttempts($request);
        $attempts[] = time();

        $request->getSession()->set('contact_attempts', array_values($attempts));
    }
}
