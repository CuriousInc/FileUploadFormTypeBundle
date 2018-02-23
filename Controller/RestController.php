<?php

namespace CuriousInc\FileUploadFormTypeBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class RestController
 *
 * @package CuriousInc\FileUploadFormTypeBundle\Controller
 */
class RestController extends FOSRestController
{
    /**
     * Create HttpException with uniform text throughout the service
     *
     * @return Response
     */
    protected function createHttpForbiddenException($var): Response
    {
        return new Response('Forbidden' . $var, 403);
    }

    /**
     * Return response for succesful creation after POST-request
     * @return Response
     */
    protected function createResponseCreated(): Response
    {
        return new Response('Created', 201);
    }
}
