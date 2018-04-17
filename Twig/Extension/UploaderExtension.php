<?php

namespace CuriousInc\FileUploadFormTypeBundle\Twig\Extension;

use CuriousInc\FileUploadFormTypeBundle\Detector\CardinalityDetectorInterface;
use CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile;
use Doctrine\ORM\Mapping\Entity;
use Oneup\UploaderBundle\Templating\Helper\UploaderHelper;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class UploaderExtension extends \Twig_Extension
{
    protected $container;
    protected $orphanManager;
    protected $session;
    protected $config;

    public function __construct(
        ContainerInterface $container,
        OrphanageManager $orphanManager,
        SessionInterface $session,
        array $config
    ) {
        $this->container = $container;
        $this->orphanManager = $orphanManager;
        $this->session = $session;
        $this->config = $config;
    }

    public function getName()
    {
        return 'dropzone';
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('curious_file_upload_cache_clear', [$this, 'clear']),
            new \Twig_SimpleFunction('curious_file_upload_load_on_cache', [$this, 'load']),
            new \Twig_SimpleFunction('curiousFileUploadAutodetectMultiple', [$this, 'autodetectMultiple']),
        ];
    }

    public function clear($fieldName)
    {
        #ToDO prueba de concepto sacar a un servicio
        $manager = $this->orphanManager->get('gallery');
        $fs = new Filesystem();
        $finder = new Finder();
        if ($fs->exists($this->config['directory'].'/'.$this->session->getId())) {
            $files = $finder->ignoreUnreadableDirs()->in($this->config['directory'].'/'.$this->session->getId());
            foreach ($files->files() as $image) {
                if (explode('_', $image->getFilename())[0]==$fieldName) {
                    $fs->remove($image);
                }
            }
        }
    }

    public function load(BaseFile $file)
    {
        #ToDO prueba de concepto sacar a un servicio
        $manager = $this->orphanManager->get('gallery');
        $fs = new Filesystem();
        // Check file exists
        if (!file_exists($file->getAbsolutePath())) {
                return false;
        }
        $fs->copy($file->getAbsolutePath(), $this->config['directory'].'/'.$this->session->getId().'/gallery/'.$file->getName());
    }

    /**
     * Detect whether given property in given entity represents a single or multiple files
     *
     * @param        $entity
     * @param string $property
     *
     * @return bool
     */
    public function autodetectMultiple($entity, string $property): bool
    {
        $detector = $this->container->get('curious_file_upload.cardinality_detector');

        return $detector->canHaveMultiple($entity, $property);
    }
}
