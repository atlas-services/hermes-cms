<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route(path: '/{_locale}/admin')]
class AdminController extends AbstractController
{

    #[Route(path: '/', name: 'admin_index', methods: ['GET'])]
    public function index(): Response
    {
        $array = [];

        return $this->render('admin/index.html.twig', $array);
    }

}
