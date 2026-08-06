<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ContactDTO;
use App\Form\ContactType;
use App\Message\SendContactEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function index(): Response
    {
        $form = $this->createForm(ContactType::class, new ContactDTO);

        return $this->render('contact.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/contact', name: 'app_contact_post', methods: ['POST'])]
    public function contact(Request $request, MessageBusInterface $messageBus): Response
    {
        $contact = new ContactDTO;
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Honeypot: the hidden 'website' field must be empty for real humans.
            $honeypot = $form->get('website')->getData();
            if ($honeypot === null || $honeypot === '') {
                $messageBus->dispatch(new SendContactEmail(
                    $contact,
                    'emails/contact.html.twig',
                ));

                $this->addFlash(
                    'success',
                    'Votre message a été envoyé. Nous vous répondrons dans les plus brefs délais.',
                );
            }

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact.html.twig', [
            'form' => $form,
        ]);
    }
}
