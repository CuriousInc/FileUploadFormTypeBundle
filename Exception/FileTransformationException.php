<?php

namespace CuriousInc\FileUploadFormTypeBundle\Exception;

/**
 * Class FileTransformationException.
 */
class FileTransformationException extends \Exception
{
    protected $message = 'The file entity could not be transformed to a session file for the view';
}
