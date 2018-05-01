<?php
/**
 * FileNamer that is specifically built for this bundle.
 * Entities' names and properties are used to determine the folder structure.
 *
 * @author Webber <webber@takken.io>
 */

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
        $fileName = '';
        $path     = explode('_', $underscorePath);
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

    /**
     * Generate the path for a persisted file, based on owning entity, property name and filename.
     *
     * @param string $className The short class name of the owning domain object
     * @param string $fieldName The name of the field that contains the relation to the File entity
     * @param string $fileName  The name of the file to generate a path for
     *
     * @return string The path for a file including the filename, for it to be stored or referenced
     */
    public function generateFilePath($className, $fieldName, $fileName): string
    {
        return sprintf(
            'uploads/gallery/%s/%s/%s',
            $className,
            $fieldName,
            $fileName
        );
    }
}
