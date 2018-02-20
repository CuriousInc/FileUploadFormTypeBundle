<?php

namespace CuriousInc\FileUploadFormTypeBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/")
     */
    public function indexAction()
    {
        return $this->render('CuriousIncFileUploadFormTypeBundle:Default:index.html.twig');
    }
}
