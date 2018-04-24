<?php

namespace CuriousInc\FileUploadFormTypeBundle\Twig\Extension;

use CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class UploaderExtension.
 */
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
        $this->container     = $container;
        $this->orphanManager = $orphanManager;
        $this->session       = $session;
        $this->config        = $config;
    }

    public function getName()
    {
        return 'dropzone';
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('curiousFileUploadClearCache', [$this, 'clearCache']),
            new \Twig_SimpleFunction('curiousFileUploadAutodetectMultiple', [$this, 'autodetectMultiple']),
            new \Twig_SimpleFunction('curiousFileUploadTypeOf', [$this, 'typeOf']),
        ];
    }

    public function clearCache()
    {
        $cache = $this->container->get('curious_file_upload.service.cache');

        $cache->clear();
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

    /**
     * Get the name of entity class.
     *
     * @param $entity
     *
     * @return string
     */
    public function typeOf($entity): string
    {
        return (new \ReflectionClass($entity))->getShortName();
    }
}
