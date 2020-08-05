<?php

namespace CuriousInc\FileUploadFormTypeBundle\Twig\Extension;

use CuriousInc\FileUploadFormTypeBundle\Service\CacheHelper;
use CuriousInc\FileUploadFormTypeBundle\Service\ClassHelper;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;

/**
 * Class UploaderExtension.
 */
class UploaderExtension extends \Twig_Extension
{
    protected $orphanManager;

    protected $config;

    protected $cacheHelper;

    protected $classHelper;

    public function __construct(
        OrphanageManager $orphanManager,
        ClassHelper $classHelper,
        CacheHelper $cacheHelper,
        array $config
    ) {
        $this->orphanManager = $orphanManager;
        $this->classHelper = $classHelper;
        $this->cacheHelper   = $cacheHelper;
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

    public function clearCache($objectId)
    {
        $this->cacheHelper->clear(null, $objectId);
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
        return $this->classHelper->hasCollection($entity, $property);
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
