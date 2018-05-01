<?php
namespace CuriousInc\FileUploadFormTypeBundle\Service;

use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Cache.
 */
class CacheHelper
{
    /**
     * @var \Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager
     */
    private $om;

    /**
     * CacheHelper constructor.
     *
     * @param \Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager $om
     */
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

        $fs = new Filesystem();
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $fs->remove($file);
        }
    }
}
