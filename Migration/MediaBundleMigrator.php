<?php
/**
 * MediaBundle migrator.
 *
 * Migrates data from SonataMediaBundle to work with CuriousUploadBundle.
 *
 * The Entity that owns the media will be referred to as `Owning Entity` or `owner`.
 *
 * The old media will be referred to as `Media` or `Media object`.
 * The new media will be referred to as `BaseFile` or `BaseFile object`.
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
     * @param array       $options                  The options used when running this command
     */
    public function migrateEntity(
        string $entityClassName,
        string $fromProperty,
        string $toProperty,
        ?string $fromIntersectionProperty,
        array $options
    ): void {
        $force = $options['force'];

        // Get a collection of all instances of given Entity
        $ownerName            = $this->classHelper->getShortClassName($entityClassName);
        $entityRepository     = $this->em->getRepository($entityClassName);
        $owners               = $entityRepository->findAll();

        // Initialise counters
        $ownerCount           = \count($owners);
        $nonExistentFileCount = 0;
        $existentFileCount    = 0;
        $mediaObjectCount     = 0;
        $missingLinkCount     = 0;

        // Handle multiple or single Media objects per owning entity?
        $this->propertyHoldsCollection = $this->classHelper->hasCollection($entityClassName, $toProperty);
        if ($this->propertyHoldsCollection && null === $fromIntersectionProperty) {
            throw new \InvalidArgumentException(
                'Missing argument: `fromIntersectionProperty`. Expected for collection types.'
            );
        }

        // Initial stats
        print "\n" .
              "Found $ownerCount instances of class $ownerName\n" .
              "Each $ownerName holds " .
              ($this->propertyHoldsCollection ? 'a collection' : 'a single media file') . ".\n" .
              "\n";

        // Go through all domain objects to migrate from Media to BaseFile
        foreach ($owners as $owner) {
            $ownerId = $owner->getId();
            print "Marking Media from $ownerName ($ownerId) for migration.\n";

            // Get an array with a single or multiple Media objects
            if ($this->propertyHoldsCollection) {
                $mediaObjects = $this->getMediaObjectCollection($owner, $fromProperty, $fromIntersectionProperty);
            } else {
                $mediaObjects = [$this->getSingleMediaObject($owner, $fromProperty)];
            }
            $mediaObjectCount += \count($mediaObjects);

            // Skip entity if it has no linked Media objects.
            if (empty($mediaObjects)) {
                continue;
            }

            // Migrate all media objects for this owning object
            foreach ($mediaObjects as $mediaObject) {
                // Skip links to Media that do not exist anymore
                if (null === $mediaObject) {
                    $missingLinkCount += 1;
                    sprintf(
                        'Link to a Media file missing for %s %s. Skipping null value.',
                        $ownerName,
                        $owner->getId()
                    );

                    continue;
                }

                print "Creating BaseFile from Media\n";
                $newFile = $this->createFileFromMedia($owner, $toProperty, $mediaObject);
                if (null === $newFile) {
                    print "The file in Media does not exist.\n";
                    $nonExistentFileCount += 1;
                    continue;
                }

                $existentFileCount += 1;
                print "Created new BaseFile from Media\n";

                // Mark the old data object for removal for this owning object
                if (!$this->propertyHoldsCollection) {
                    print "marking media object from owner for removal\n";
                    $this->removeMediaObjectFromOwner($owner, $fromProperty, $mediaObject);
                }
            }

            // Mark the old data collection for removal for this owning object
            if ($this->propertyHoldsCollection) {
                print "marking media collection and intersection collection from owner for removal\n";
                $this->removeMediaCollectionFromOwner($owner, $fromProperty, $fromIntersectionProperty);
            }
        }

        print "\n"
              . "#######################################################################\n"
              . "# Summary:\n"
              . "# --------\n"
              . "# Processed $ownerCount instances of class $ownerName which hold "
              . ($this->propertyHoldsCollection ? "a collection" : "a single media file") . " each.\n"
              . "# \n"
              . "# Number of Media objects to migrate: $mediaObjectCount\n"
              . "# \n"
              . "# Missing links: $missingLinkCount\n"
              . "# Non-existent files: $nonExistentFileCount\n"
              . "# Existent files: $existentFileCount\n"
              . "#######################################################################\n";

        if ($force) {
            print "\nPersisting changes to database\n";
            $this->em->flush();
        }

        print "\nend transaction\n";
    }

    /**
     * Migrate the data of a Media Entity class to a BaseFile entity class, owned by an `owning` Entity class.
     *
     * @param mixed          $entity      The Entity that owns the old media objects, and will own the new file objects
     * @param string         $toProperty  The property referencing BaseFile Entity
     * @param MediaInterface $mediaObject A reference to a Media object, owned by the entity
     *
     * @return BaseFile|null The newly created BaseFile object or null if it wasn't created.
     */
    private function createFileFromMedia($entity, string $toProperty, MediaInterface $mediaObject): ?BaseFile
    {
        // Get the path for the Media object
        $mediaFilePath = $this->getMediaPath($mediaObject);

        // Create a new BaseFile object
        try {
            $fileObject   = $this->createFileObjectFromMedia($entity, $toProperty, $mediaObject);
            $fileFullPath = $this->getFullPathFromFile($fileObject);
            $this->fs->copy($mediaFilePath, $fileFullPath, true);
        } catch (FileNotFoundException $ex) {
            sprintf(
                'Physical file %s does not exist in %s %s, skipping. Reason: %s.',
                $mediaFilePath,
                $this->classHelper->getShortClassName($entity),
                $entity->getId(),
                $ex->getMessage()
            );

            return null;
        }

        // Link the BaseFile object to the owner
        $this->linkFileObjectToOwner($entity, $toProperty, $fileObject);

        print "Created new $toProperty.\n";

        return $fileObject;
    }

    /**
     * Create BaseFile object from the Media object
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

        // Create and save BaseFile object
        /** @var BaseFile $fileObject */
        $fileObject = new $targetEntity();
        $fileObject->setPath($path);

        $this->em->persist($fileObject);

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
     * Get the full path for the BaseFile object.
     *
     * @param \CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile $fileObject
     *
     * @return string the full path for the BaseFile object
     */
    private function getFullPathFromFile(BaseFile $fileObject)
    {
        return $this->fileBasePath . '/' . $fileObject->getRelativePath();
    }

    /**
     * Link the BaseFile object to the owner that owned the Media object.
     *
     * @param mixed    $entity     The domain object owning the Media object
     * @param string   $toProperty The Entity's property relating to the BaseFile entity
     * @param BaseFile $fileObject The file object
     */
    private function linkFileObjectToOwner($entity, string $toProperty, BaseFile $fileObject): void
    {
        if ($this->propertyHoldsCollection) {
            // Add BaseFile object to properties collection
            $addMethod = $this->classHelper->retrieveAdder($entity, $toProperty);
            $entity->$addMethod($fileObject);
        } else {
            // Add BaseFile object to property
            $setMethod = $this->classHelper->retrieveSetter($entity, $toProperty);
            $entity->$setMethod($fileObject);
        }

        $this->em->persist($entity);
    }

    /**
     * Unlink the Media object from the owner that now owns the BaseFile object.
     *
     * @param mixed          $entity       The domain object owning the Media object
     * @param string         $fromProperty The Entity's property relating to the Media entity
     * @param MediaInterface $mediaObject  The file object
     */
    private function removeMediaObjectFromOwner($entity, string $fromProperty, MediaInterface $mediaObject): void
    {
        // Remove Media object from property
        $setMethod = $this->classHelper->retrieveSetter($entity, $fromProperty);
        $entity->$setMethod(null);

        $this->em->persist($entity);

        $this->em->remove($mediaObject);
    }

    /**
     * @param        $entity
     * @param string $fromProperty
     * @param string $fromIntersectionProperty
     */
    private function removeMediaCollectionFromOwner($entity, string $fromProperty, string $fromIntersectionProperty)
    {
        // Get intersection entities
        $getMethod            = $this->classHelper->retrieveGetter($entity, $fromProperty);
        $intersectionEntities = $entity->$getMethod();
        print 'intersection entities to iterate `' . \count($intersectionEntities) . '`' . "\n";

        // Remove each Intersection and the media that it holds
        foreach ($intersectionEntities as $intersectionEntity) {
            // Get the media object for the intersection (one per)
            $getMediaMethod = $this->classHelper->retrieveGetter($intersectionEntity, $fromIntersectionProperty);
            $mediaObject    = $intersectionEntity->$getMediaMethod();

            print 'about to remove intersection Entity of type '
                  . '`' . $this->classHelper->getShortClassName($intersectionEntity) . '`' . "\n";

            // Unlink the intersection from the owning entity
            $removeIntersectionMethod = $this->classHelper->retrieveRemover($entity, $fromProperty);
            $entity->$removeIntersectionMethod($intersectionEntity);
            $this->em->remove($intersectionEntity);
            print 'marked intersection object for removal' . "\n";

            if (null !== $mediaObject) {
                $this->em->remove($mediaObject);
                print 'marked media object for removal' . "\n";
            } else {
                print 'skipped non-existent media object' . "\n";
            }
        }
    }
}
