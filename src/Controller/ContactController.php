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

class ContactController extends AbstractController
{
    #[Route('/contact', name: "app_contact", methods: ["GET"])]
    public function index(): Response
    {
        $form = $this->createForm(ContactType::class, new ContactDTO());

        return $this->render('contact.html.twig', [
            "form" => $form
        ]);
    }

    #[Route("/contact", name: "app_contact_post", methods: ["POST"])]
    public function contact(Request $request, MessageBusInterface $messageBus): Response
    {
        $contact = new ContactDTO();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        $success = false;

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get("website") !== null) {
                $messageBus->dispatch(new SendContactEmail(
                    $contact
                ));
            }
            $success = true;
        }
        return $this->render('contact.html.twig', [
            "form" => $form,
            "success" => $success
        ]);
    }
}
