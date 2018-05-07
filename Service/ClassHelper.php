<?php
/**
 * Helper for retrieving metadata about given class and properties
 *
 * @author webber <webber@takken.io>
 */

namespace CuriousInc\FileUploadFormTypeBundle\Service;

use CuriousInc\FileUploadFormTypeBundle\Exception\MissingServiceException;
use CuriousInc\FileUploadFormTypeBundle\Exception\NotImplementedException;

/**
 * Class ClassHelper.
 */
class ClassHelper
{
    /** @var \CuriousInc\FileUploadFormTypeBundle\Service\AnnotationHelper */
    private $annotationHelper;

    /**
     * ClassHelper constructor.
     *
     * @param \CuriousInc\FileUploadFormTypeBundle\Service\AnnotationHelper $annotationHelper
     */
    public function __construct(AnnotationHelper $annotationHelper)
    {
        $this->annotationHelper = $annotationHelper;
    }

    /**
     * Detect cardinality of the relation between given entity and the File entity, referenced by given property.
     *
     * Uses ORM Mapping annotations to detect the real mapped relation, or defaults to checking for the existence of an
     * `addProperty(s)` method.
     *
     * @param mixed  $entity   Either a string containing the name of the class to reflect, or an object.
     * @param string $property The property in given entity that links to the target entity
     *
     * @return bool Whether or not the property holds a collection
     */
    public function hasCollection($entity, string $property): bool
    {
        // Get relation type by annotation
        try {
            $annotationType = $this->annotationHelper->getPropertyRelationType($entity, $property);
        } catch (MissingServiceException $ex) {
            $annotationType = null;
        }

        // Return true or false based on the type of relation
        switch ($annotationType) {
            case $this->annotationHelper::RELATION_TO_MANY:
                return true;
                break;
            case $this->annotationHelper::RELATION_TO_ONE:
                return false;
                break;
            case $this->annotationHelper::RELATION_NONE:
            case null:
            default:
                return $this->hasCollectionByCustomDetermination($entity, $property);
        }
    }

    /**
     * Detect cardinality of the relation between given entity and the File entity, referenced by given property by
     * checking for the existence of an `addProperty(s)` method.
     *
     * @param mixed  $entity   Either a string containing the name of the class to reflect, or an object.
     * @param string $property The property in given entity that links to the target entity
     *
     * @return bool Whether or not the property holds a collection
     */
    private function hasCollectionByCustomDetermination($entity, string $property): bool
    {
        return null !== $this->retrieveAdder($entity, $property)
               || null !== $this->retrieveRemover($entity, $property);
    }

    /**
     * Retrieve adder for given entity's property.
     *
     * @param mixed  $entity    The class instance or class name to retrieve the method for
     * @param string $fieldName The field for which to get the method
     *
     * @return null|string name of the method or null if it does not exist
     */
    public function retrieveAdder($entity, string $fieldName): string
    {
        return $this->retrieveAdderOrRemover($entity, $fieldName, 'add');
    }

    /**
     * Retrieve getter for given entity's property.
     *
     * @param mixed  $entity    The class instance or class name to retrieve the method for
     * @param string $fieldName The field for which to get the method
     *
     * @return null|string name of the method or null if it does not exist
     */
    public function retrieveRemover($entity, string $fieldName): string
    {
        return $this->retrieveAdderOrRemover($entity, $fieldName, 'remove');
    }

    /**
     * Retrieve getter for given entity's property.
     *
     * @param mixed  $entity      The class instance or class name to retrieve the method for
     * @param string $fieldName   The field for which to get the method
     * @param string $addOrRemove One of `add` or `remove` to indicate which method to retrieve
     *
     * @return null|string name of the method or null if it does not exist
     */
    private function retrieveAdderOrRemover($entity, string $fieldName, string $addOrRemove): string
    {
        if (!\in_array($addOrRemove, ['add', 'remove'], true)) {
            throw new \InvalidArgumentException("Expected `add` or `remove`, got `$addOrRemove`");
        }

        $method = $addOrRemove . $this->prepareString($fieldName);
        if (method_exists($entity, $method)) {
            return $method;
        }

        $method = $addOrRemove . ucfirst($fieldName);
        if (method_exists($entity, $method)) {
            return $method;
        }

        throw new NotImplementedException("Invalid domain object.");
    }

    /**
     * Retrieve getter for given entity's property.
     *
     * @param mixed  $entity    The class instance or class name to retrieve the method for
     * @param string $fieldName The field for which to get the method
     *
     * @return null|string name of the method or null if it does not exist
     */
    public function retrieveGetter($entity, string $fieldName): ?string
    {
        foreach (['get', 'has', 'is'] as $type) {
            $getter = $this->retrieveGetterOrSetter($entity, $fieldName, $type);
            if (null !== $getter) {
                return $getter;
            }
        }

        return null;
    }

    /**
     * Retrieve setter for given entity's property.
     *
     * @param mixed  $entity    The class instance or class name to retrieve the method for
     * @param string $fieldName The field for which to get the method
     *
     * @return null|string name of the method or null if it does not exist
     */
    public function retrieveSetter($entity, string $fieldName): ?string
    {
        return $this->retrieveGetterOrSetter($entity, $fieldName, 'set');
    }

    /**
     * Retrieve getter or setter for given entity's property.
     *
     * @param mixed  $entity         The class instance or class name to retrieve the method for
     * @param string $fieldName      The field for which to get the method
     * @param string $getterOrSetter One of 'get', 'has', 'is' or 'set'
     *
     * @return null|string name of the method or null if it does not exist
     */
    public function retrieveGetterOrSetter($entity, string $fieldName, string $getterOrSetter): ?string
    {
        if (!\in_array($getterOrSetter, ['get', 'has', 'is', 'set'])) {
            throw new \InvalidArgumentException("Expected `get`, `has`, `is` or `set`, got `$getterOrSetter`");
        }

        $method = $getterOrSetter . ucfirst($fieldName);
        if (method_exists($entity, $method)) {
            return $method;
        }

        return null;
    }

    /**
     * Get the short name for a class or object.
     *
     * @param mixed $class An instance of a class or fully qualified class name
     *
     * @return string The short name of given class or object's class
     */
    public function getShortClassName($class)
    {
        $reflectionClass = new \ReflectionClass($class);

        return $reflectionClass->getShortName();
    }

    /**
     * Return a string that can be used after `add` or `remove` to create a method name.
     *
     * @param string $fieldName The name of a property to get the prepared string for
     *
     * @return string The right-hand part of a `remove` or `add` method name
     */
    private function prepareString(string $fieldName)
    {
        $field = ucfirst($fieldName);

        return substr($field, 0, -1);
    }
}
