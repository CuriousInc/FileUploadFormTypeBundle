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
    protected function createHttpForbiddenException($message = null): Response
    {
        if (null !== $message) {
            return new Response('Forbidden - ' . $message, 403);
        }

        return new Response('Forbidden', 403);
    }

    /**
     * Return response for successful creation of a file
     *
     * @return Response
     */
    protected function createResponseCreated(): Response
    {
        return new Response('Created', 201);
    }

    /**
     * Return response for successful deletion of a file
     */
    protected function createResponseDeletedOrNot()
    {
        return new Response(null, 204);
    }
}
