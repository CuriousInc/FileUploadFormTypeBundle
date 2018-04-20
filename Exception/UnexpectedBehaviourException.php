<?php

namespace CuriousInc\FileUploadFormTypeBundle\Exception;

/**
 * Class UnexpectedBehaviourException.
 */
class UnexpectedBehaviourException extends \Exception
{
    protected $message = 'The application has gotten into an unexpected state and cannot continue.';
}
