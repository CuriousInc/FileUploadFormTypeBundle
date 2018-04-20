<?php
/**
 * CardinalityDetector, Detector of cardinalities.
 *
 * @date   2018-04-11
 * @author webber
 */

namespace CuriousInc\FileUploadFormTypeBundle\Detector;

use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;

/**
 * Class CardinalityDetector.
 */
class CardinalityDetector implements CardinalityDetectorInterface
{
    /**
     * {@inheritdoc}
     */
    public function canHaveMultiple($entity, string $fileProperty): bool
    {
        $reflectionClass = new \ReflectionClass($entity);

        if (class_exists('\Doctrine\Common\Annotations\AnnotationReader')) {
            $annotationReader = new \Doctrine\Common\Annotations\AnnotationReader();

            $propertyAnnotations = $annotationReader
                ->getPropertyAnnotations($reflectionClass->getProperty($fileProperty));

            foreach ($propertyAnnotations as $annotation) {
                if ($annotation instanceof OneToOne || $annotation instanceof ManyToOne) {
                    return false;
                } elseif ($annotation instanceof ManyToMany || $annotation instanceof OneToMany) {
                    return true;
                } else {
                    // Not a ORM-relationship annotation
                }
            }
        }

        return $reflectionClass->hasMethod('add' . $fileProperty)
               || $reflectionClass->hasMethod('add' . $this->prepareString($fileProperty));
    }

    /**
     * @param $fieldName
     *
     * @return string
     */
    public function prepareString($fieldName)
    {
        $field = ucfirst($fieldName);

        return substr($field, 0, -1);
    }
}
