<?php
/**
 * MediaBundle migrator.
 *
 * Migrates data from SonataMediaBundle to work with CuriousUploadBundle.
 *
 * The Entity that owns the media will be referred to as `Owning Entity` or `owner`.
 * The old media will be referred to as `Media` or `media object`.
 * The new media will be referred to as `File` or `file object`.
 *
 * @author Webber <webber@takken.io>
 */

namespace CuriousInc\FileUploadFormTypeBundle\Migration;

use CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile;
use CuriousInc\FileUploadFormTypeBundle\Exception\MissingServiceException;
use CuriousInc\FileUploadFormTypeBundle\Namer\FileNamer;
use CuriousInc\FileUploadFormTypeBundle\Service\AnnotationHelper;
use CuriousInc\FileUploadFormTypeBundle\Service\ClassHelper;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Entity;
use Sonata\MediaBundle\Model\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class MediaBundleMigrator.
 */
class MediaBundleMigrator
{
    private $annotationHelper;

    private $container;

    private $em;

    private $fileBasePath;

    private $fileNamer;

    private $propertyHoldsCollection;

    /**
     * MediaBundleMigrator constructor.
     */
    public function __construct(
        ContainerInterface $container,
        EntityManager $em,
        FileNamer $fileNamer,
        AnnotationHelper $annotationHelper,
        ClassHelper $classHelper
    ) {
        $this->container        = $container;
        $this->em               = $em;
        $this->fileNamer        = $fileNamer;
        $this->annotationHelper = $annotationHelper;
        $this->classHelper      = $classHelper;
        $this->fs               = new Filesystem();

        $oneUpConfig        = $this->container->getParameter('oneup_uploader.config');
        $this->fileBasePath = $oneUpConfig['mappings']['gallery']['storage']['directory'];
    }

    /**
     * Migrate data for an Entity from SonataMediaBundle to CuriousUploadBundle, from one property to the other.
     *
     * @param string      $entityClassName          The class that owns the media
     * @param string      $fromProperty             The property containing the reference to SonataMediaBundle data
     * @param string      $toProperty               The property containing the reference to CuriousUploadBundle
     * @param string|null $fromIntersectionProperty The property of the intersection entity containing the reference to
     *                                              the Media entity
     */
    public function migrateEntity(
        string $entityClassName,
        string $fromProperty,
        string $toProperty,
        ?string $fromIntersectionProperty
    ): void {

        // Get a collection of all instances of given Entity
        $ownerName        = $this->classHelper->getShortClassName($entityClassName);
        $entityRepository = $this->em->getRepository($entityClassName);
        $owners           = $entityRepository->findAll();

        // Handle multiple or single Media objects per owning entity?
        $this->propertyHoldsCollection = $this->classHelper->hasCollection($entityClassName, $toProperty);
        if ($this->propertyHoldsCollection && null === $fromIntersectionProperty) {
            throw new \InvalidArgumentException(
                'Missing argument: `fromIntersectionProperty`. Expected for collection types.'
            );
        }

        // Initial stats
        echo "\n" .
             'Found ' . \count($owners) . ' instances of class ' . $ownerName . "\n" .
             'Each ' . $ownerName . ' ' .
             'holds ' . ($this->propertyHoldsCollection ? 'a collection' : 'a single media file') . "\n" .
             "\n";

        // Go through all domain objects to migrate from Media to BaseFile
        foreach ($owners as $owner) {
            // Get an array with a single or multiple Media objects
            if ($this->propertyHoldsCollection) {
                $mediaObjects = $this->getMediaObjectCollection($owner, $fromProperty, $fromIntersectionProperty);
            } else {
                $mediaObjects = [$this->getSingleMediaObject($owner, $fromProperty)];
            }

            // Skip entity if it has no linked Media objects.
            if (empty($mediaObjects)) {
                continue;
            }

            // Migrate all media objects for this owning object
            foreach ($mediaObjects as $mediaObject) {
                // Skip links to Media that do not exist anymore
                if (null === $mediaObject) {
                    dump(
                        sprintf(
                            'Link to a Media file missing for %s %s. Skipping null value.',
                            $ownerName,
                            $owner->getId()
                        )
                    );
                    dump('skipping null value in : array looks like this:::', $mediaObjects);
                    continue;
                }

                $this->migrateMedia($owner, $fromProperty, $toProperty, $mediaObject);
            }
        }

        dump('end transaction');
    }

    /**
     * Migrate the data of a Media Entity class to a File entity class, owned by an `owning` Entity class.
     *
     * @param mixed          $entity       The Entity that owns the old media objects, and will own the new file objects
     * @param string         $fromProperty The property referencing the Media Entity
     * @param string         $toProperty   The property referencing File Entity
     * @param MediaInterface $mediaObject  A reference to a Media object, owned by the entity
     */
    private function migrateMedia($entity, string $fromProperty, string $toProperty, MediaInterface $mediaObject): void
    {
        // Get the path for the Media object
        $mediaFilePath = $this->getMediaPath($mediaObject);

        // Create a new File object
        try {
            $fileObject   = $this->createFileObjectFromMedia($entity, $toProperty, $mediaObject);
            $fileFullPath = $this->getFullPathFromFile($fileObject);
            $this->fs->copy($mediaFilePath, $fileFullPath);
        } catch (FileNotFoundException $ex) {
            dump(
                sprintf(
                    'Physical file %s does not exist in %s %s, skipping.',
                    $mediaFilePath,
                    $this->classHelper->getShortClassName($entity),
                    $entity->getId()
                )
            );

            return;
        }

        // Link the File object to the owner
        $this->linkFileObjectToOwner($entity, $toProperty, $fileObject);
        $newId = $fileObject->getId();

        // Unlink the Media object from the owner
        $oldId = $mediaObject->getId();
        $this->unlinkMediaObjectFromOwner($entity, $fromProperty, $mediaObject);

        // Report progress
        // TODO - check whether this progress report is given
        dump("migrated $fromProperty $oldId to $toProperty $newId.");
    }

    /**
     * Create File object from the Media object
     *
     * @param mixed          $entity
     * @param string         $toProperty
     * @param MediaInterface $media
     *
     * @return \CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile
     */
    private function createFileObjectFromMedia($entity, string $toProperty, MediaInterface $media)
    {
        // Get class for BaseFile
        $targetEntity = $this->annotationHelper->getTargetEntityForProperty($entity, $toProperty);

        // Determine path
        $owningEntityName = $this->classHelper->getShortClassName($entity);
        $filename         = $media->getMetadataValue('filename');
        $path             = $this->fileNamer->generateFilePath($owningEntityName, $toProperty, $filename);

        // Create and save File object
        /** @var BaseFile $fileObject */
        $fileObject = new $targetEntity();
        $fileObject->setPath($path);
        $this->em->persist($fileObject);

        $this->em->flush();

        return $fileObject;
    }

    /**
     * Get the media object related to the `fromProperty` if any.
     *
     * @param Entity $entity       The Entity that owns the old media objects and will own the new file objects
     * @param string $fromProperty The property referencing the Media Entity
     *
     * @return MediaInterface|null The related media object if any
     */
    private function getSingleMediaObject($entity, $fromProperty): ?MediaInterface
    {
        $getMethod = $this->classHelper->retrieveGetter($entity, $fromProperty);

        $media = $entity->$getMethod();

        // Check whether the right type was found
        if (null !== $media && !$media instanceof MediaInterface) {
            throw new \InvalidArgumentException('Expected from property to reference Media from SonataMediaBundle');
        }

        return $media;
    }

    /**
     * Get the media objects from the collection related to the `fromProperty` if any.
     *
     * @param Entity $entity                   The Entity that owns the old media objects and will
     *                                         own the new file objects
     * @param string $fromProperty             The property referencing the intersection table
     * @param string $fromIntersectionProperty The property referencing the Media Entity
     *
     * @return array An array containing the related media objects
     */
    private function getMediaObjectCollection($entity, $fromProperty, $fromIntersectionProperty)
    {
        $getMethod = $this->classHelper->retrieveGetter($entity, $fromProperty);

        $intersectionEntities = $entity->$getMethod();
        $mediaObjects         = [];
        if (\count($intersectionEntities) >= 1) {
            foreach ($intersectionEntities as $intersectionEntity) {
                $mediaObjects[] = $this->getSingleMediaObject($intersectionEntity, $fromIntersectionProperty);
            }
        }

        return $mediaObjects;
    }

    /**
     * Get the full path to the media file
     *
     * @param MediaInterface $mediaObject
     *
     * @return string The full path to the media file if any
     */
    private function getMediaPath(MediaInterface $mediaObject): string
    {
        // Check whether provider exists
        $providerName = $mediaObject->getProviderName();
        if (!$this->container->has($providerName)) {
            throw new MissingServiceException("Unable to find $providerName.");
        }

        /** @var \Sonata\MediaBundle\Provider\MediaProviderInterface $provider */
        $provider = $this->container->get($providerName);

        /** @var \Gaufrette\File $mediaReference */
        $mediaReference = $provider->getReferenceFile($mediaObject);

        $fs      = $provider->getFilesystem();
        $adapter = $fs->getAdapter();

        return $adapter->getDirectory() . '/' . $mediaReference->getKey();
    }

    /**
     * Get the full path for the File object.
     *
     * @param \CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile $fileObject
     *
     * @return string the full path for the File object
     */
    private function getFullPathFromFile(BaseFile $fileObject)
    {
        return $this->fileBasePath . '/' . $fileObject->getRelativePath();
    }

    /**
     * Link the File object to the owner that owned the Media object.
     *
     * @param mixed    $entity     The domain object owning the Media object
     * @param string   $toProperty The Entity's property relating to the File entity
     * @param BaseFile $fileObject The file object
     */
    private function linkFileObjectToOwner($entity, string $toProperty, BaseFile $fileObject): void
    {
        if ($this->propertyHoldsCollection) {
            // Add File object to properties collection
            $addMethod = $this->classHelper->retrieveAdder($entity, $toProperty);
            $entity->$addMethod($fileObject);
        } else {
            // Add File object to property
            $setMethod = $this->classHelper->retrieveSetter($entity, $toProperty);
            $entity->$setMethod($fileObject);
        }

        $this->em->persist($entity);

        $this->em->flush();
    }

    /**
     * Unlink the Media object from the owner that now owns the File object.
     *
     * @param mixed          $entity       The domain object owning the Media object
     * @param string         $fromProperty The Entity's property relating to the Media entity
     * @param MediaInterface $mediaObject  The file object
     */
    private function unlinkMediaObjectFromOwner($entity, string $fromProperty, MediaInterface $mediaObject): void
    {
        if ($this->propertyHoldsCollection) {
            // Remove File object from properties collection
            $removeMethod = $this->classHelper->retrieveRemover($entity, $fromProperty);
            $entity->$removeMethod($mediaObject);
        } else {
            // Remove File object from property
            $setMethod = $this->classHelper->retrieveSetter($entity, $fromProperty);
            $entity->$setMethod(null);
        }

        $this->em->persist($entity);

        $this->em->remove($mediaObject);

        $this->em->flush();
    }
}
