<?php

namespace CuriousInc\FileUploadFormTypeBundle\Exception;

/**
 * Class InvalidFileNameException.
 */
class InvalidFileNameException extends \Exception
{
    protected $message = 'Given path for the file is invalid.';
}
