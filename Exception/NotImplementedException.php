<?php

namespace CuriousInc\FileUploadFormTypeBundle\Exception;

/**
 * Class NotImplementedException.
 */
class NotImplementedException extends \Exception
{
    private const DEFAULT_MESSAGE = 'Feature has not been implemented.';

    public function __construct($message)
    {
        parent::__construct(\trim($message . ' ' . static::DEFAULT_MESSAGE));
    }
}
