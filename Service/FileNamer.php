<?php

namespace CuriousInc\FileUploadFormTypeBundle\Service;

use Oneup\UploaderBundle\Uploader\File\FileInterface;
use Oneup\UploaderBundle\Uploader\Naming\NamerInterface;
use Symfony\Component\Validator\Constraints\Date;

/**
 * Class FileNamer
 *
 * @package CuriousInc\FileUploadFormTypeBundle\Service
 */
class FileNamer implements NamerInterface
{
    /**
     * @param FileInterface $file
     *
     * @return string
     */
    public function name(FileInterface $file)
    {
        return $_REQUEST['uniqueFileName'];
    }
}
