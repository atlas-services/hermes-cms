<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\NewsletterSubscriberType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin/user', requirements: ['_locale' => 'fr|en'])]
final class UserController extends AbstractController
{
    #[Route('/user/newsletter', name: 'user_newsletter', methods: ['GET'])]
    public function newsletter(UserRepository $userRepository): Response
    {
        return $this->render('admin/user/newsletter.html.twig', [
            'users' => $userRepository->findNewsletterSubscribers(false),
        ]);
    }

    #[Route('/{id}/newsletter/edit', name: 'user_newsletter_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editNewsletterSubscriber(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        if (!$user->hasNewsletterRole()) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(NewsletterSubscriberType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if (\is_string($plain) && $plain !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }
            $entityManager->flush();

            return $this->redirectToRoute('user_newsletter', ['_locale' => $request->getLocale()]);
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/ajax/switch/user', name: 'switch_user_active_ajax', methods: ['POST'])]
    public function ajaxSwitchUser(Request $request, UserRepository $userRepository): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return new Response('Not an XMLHttpRequest', Response::HTTP_BAD_REQUEST);
        }

        $id = filter_var($request->request->get('id'), \FILTER_VALIDATE_INT);
        if ($id === false) {
            return new JsonResponse(['error' => 'invalid id'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $userRepository->switchNewsletterActive($id);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $user->isActiveNewsletter()]);
    }

    #[Route('/ajax/switch/users', name: 'switch_users_active_ajax', methods: ['POST'])]
    public function ajaxSwitchAll(Request $request, UserRepository $userRepository): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return new Response('Not an XMLHttpRequest', Response::HTTP_BAD_REQUEST);
        }

        $active = filter_var($request->request->get('active'), \FILTER_VALIDATE_BOOLEAN);
        $userRepository->switchNewsletterActiveAll($active);

        return new JsonResponse(['data' => true]);
    }
}
