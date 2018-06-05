<?php

namespace CuriousInc\FileUploadFormTypeBundle\Exception;

/**
 * Class FileTransformationException.
 */
class MissingServiceException extends \Exception
{
    private const DEFAULT_MESSAGE = 'Service is not registered.';

    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct(\trim($message . ' ' . static::DEFAULT_MESSAGE), $code, $previous);
    }
}
