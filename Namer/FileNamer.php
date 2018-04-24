<?php

namespace CuriousInc\FileUploadFormTypeBundle\Namer;

use CuriousInc\FileUploadFormTypeBundle\Exception\InvalidFileNameException;
use Oneup\UploaderBundle\Uploader\File\FileInterface;
use Oneup\UploaderBundle\Uploader\Naming\NamerInterface;

/**
 * Class FileNamer.
 */
class FileNamer implements NamerInterface
{
    /**
     * Determine and sanitise file path from request
     *
     * @param FileInterface $file
     *
     * @return string
     */
    public function name(FileInterface $file)
    {
        $uniqueFileName = $_REQUEST['uniqueFileName'];

        return $this->convertUnderscorePath($uniqueFileName);
    }

    /**
     * Converts a file name containing underscores to a path.
     *
     * @param $underscorePath
     *
     * @return string
     */
    public function convertUnderscorePath($underscorePath): string
    {
        $fileName          = '';
        $path              = explode('_', $underscorePath);
        foreach ($path as $key => $part) {
            $isLast = $key === \count($path) - 1;
            if ($isLast && 1 === preg_match('/[a-zA-Z0-9\.\-]+/u', $part)) {
                // FileName
                $fileName .= $part;
            } elseif (!$isLast && 1 === preg_match('/[a-zA-Z0-9\-]+/u', $part)) {
                // Folder
                $fileName .= $part . '/';
            } else {
                // Invalid input
                throw new InvalidFileNameException();
            }
        }

        return $fileName;
    }
}
