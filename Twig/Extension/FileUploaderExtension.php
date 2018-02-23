<?php

namespace CuriousInc\FileUploadFormTypeBundle\Twig\Extension;

use CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class UploaderExtension
 *
 * @package CuriousInc\FileUploadFormTypeBundle\Twig\Extension
 */
class FileUploaderExtension extends \Twig_Extension
{
    protected $orphanManager;
    protected $config;
    protected $session;

    /**
     * UploaderExtension constructor.
     *
     * @param OrphanageManager $orphanManager
     * @param SessionInterface $session
     * @param array            $config
     */
    public function __construct(OrphanageManager $orphanManager, SessionInterface $session, array $config)
    {
        $this->orphanManager = $orphanManager;
        $this->session       = $session;
        $this->config        = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'curious_file_upload';
    }

    /**
     * @return array|\Twig_Function[]
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('curious_file_upload_cache_clear', [$this, 'clear']),
            new \Twig_SimpleFunction('curious_file_upload_load_on_cache', [$this, 'load']),
        ];
    }

    /**
     * @param $fieldName
     */
    public function clear($fieldName)
    {
        #ToDO prueba de concepto sacar a un servicio
        $manager = $this->orphanManager->get('gallery');
        $fs      = new Filesystem();
        $finder  = new Finder();
        if ($fs->exists($this->config['directory'] . '/' . $this->session->getId())) {
            $files = $finder->ignoreUnreadableDirs()->in($this->config['directory'] . '/' . $this->session->getId());
            foreach ($files->files() as $image) {
                if (explode('_', $image->getFilename())[0] == $fieldName) {
                    $fs->remove($image);
                }
            }
        }
    }

    public function load(BaseFile $file)
    {
        #ToDO prueba de concepto sacar a un servicio
        $manager = $this->orphanManager->get('gallery');
        $fs      = new Filesystem();
        // Check file exists
        if (!file_exists($file->getAbsolutePath())) {
            return false;
        }
        $fs->copy(
            $file->getAbsolutePath(),
            $this->config['directory'] . '/' . $this->session->getId() . '/gallery/' . $file->getName()
        );
    }
}
