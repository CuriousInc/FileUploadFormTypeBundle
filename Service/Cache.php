<?php
namespace CuriousInc\FileUploadFormTypeBundle\Service;

use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Cache.
 */
class Cache
{
    /**
     * @var \Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager
     */
    private $om;

    public function __construct(OrphanageManager $om)
    {
        $this->om = $om;
    }

    /**
     * Clears all files from session orphanage.
     */
    public function clear()
    {
        $manager = $this->om->get('gallery');
        $files = $manager->getFiles();

        $fileSystem = new Filesystem();
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $fileSystem->remove($file);
        }
    }
}
