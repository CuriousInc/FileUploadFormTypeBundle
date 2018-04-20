<?php

namespace CuriousInc\FileUploadFormTypeBundle\Namer;

use Oneup\UploaderBundle\Uploader\File\FileInterface;
use Oneup\UploaderBundle\Uploader\Naming\NamerInterface;
use Symfony\Component\Validator\Constraints\Date;

/**
 * Class FileNamer.
 */
class FileNamer implements NamerInterface
{
    /**
     * TODO - Check for security
     *
     * @param FileInterface $file
     *
     * @return string
     */
    public function name(FileInterface $file)
    {
        $uniqueFileName = $_REQUEST['uniqueFileName'];

        if (1 === preg_match('A–Za–z0–9\.\_\-', $uniqueFileName)) {
            return $uniqueFileName;
        }

        return md5($uniqueFileName);
    }
}
