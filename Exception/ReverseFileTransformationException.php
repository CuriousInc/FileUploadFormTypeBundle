<?php

namespace CuriousInc\FileUploadFormTypeBundle\Exception;

/**
 * Class ReverseFileTransformationException.
 */
class ReverseFileTransformationException extends \Exception
{
    protected $message = 'The session file could not be converted to a file entity object.';
}
