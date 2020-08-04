<?php
/**
 * Transformer specific for CuriousUploadBundle.
 *
 * Transforms files that are uploaded in the form within a certain session, into domain objects, and vice versa.
 *
 * @author Webber <webber@takken.io>
 */

namespace CuriousInc\FileUploadFormTypeBundle\Form\DataTransformer;

use CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile;
use CuriousInc\FileUploadFormTypeBundle\Exception\MissingServiceException;
use CuriousInc\FileUploadFormTypeBundle\Form\Type\DropzoneType;
use CuriousInc\FileUploadFormTypeBundle\Namer\FileNamer;
use CuriousInc\FileUploadFormTypeBundle\Service\CacheHelper;
use CuriousInc\FileUploadFormTypeBundle\Service\ClassHelper;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Oneup\UploaderBundle\Uploader\Storage\FilesystemOrphanageStorage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Class SessionFilesToEntitiesTransformer.
 */
class SessionFilesToEntitiesTransformer implements DataTransformerInterface
{
    /**
     * @var CacheHelper
     */
    private $cacheHelper;

    /**
     * @var \CuriousInc\FileUploadFormTypeBundle\Service\ClassHelper
     */
    private $classHelper;

    /**
     * string the property in the owning entity referencing the file(s)
     */
    private $fieldName;

    /**
     * @var \CuriousInc\FileUploadFormTypeBundle\Namer\FileNamer
     */
    private $fileNamer;

    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var array
     */
    private $options;

    /**
     * @var OrphanageManager
     */
    private $orphanageManager;

    /**
     * @var \Doctrine\ORM\Mapping\Entity
     */
    private $owningEntityObject;

    /**
     * @var string the fully qualified className for the entity owning the file(s)
     */
    private $sourceEntity;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $sourceEntityRepository;

    /**
     * @var string the fully qualified className for the image entity
     */
    private $targetEntity;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $targetEntityRepository;

    public function __construct(
        ObjectManager $om,
        OrphanageManager $orphanageManager,
        ClassHelper $classHelper,
        CacheHelper $cacheHelper,
        FileNamer $namer,
        $options,
        $mapping
    ) {
        $this->om = $om;
        $this->orphanageManager = $orphanageManager;
        $this->classHelper = $classHelper;
        $this->cacheHelper = $cacheHelper;
        $this->fileNamer = $namer;
        $this->sourceEntity = $mapping['sourceEntity'];
        $this->sourceEntityRepository = $this->om->getRepository($this->sourceEntity);
        $this->fieldName = $mapping['fieldName'];
        $this->targetEntity = $mapping['targetEntity'];
        $this->targetEntityRepository = $this->om->getRepository($this->targetEntity);
        $this->options = $options;
        $this->mapping = $mapping;
    }

    /**
     * The transform method of the transformer is used to convert data from the
     * model (File domain object) to the normalized format (Filesystem File).
     *
     * @param  BaseFile[]|BaseFile $files
     *
     * @return array|string array of string or empty string when empty
     */
    public function transform($files)
    {
        if (null === $files) {
            // No files to be transformed
            return '';
        } elseif ($files instanceof BaseFile) {
            // One file to be transformed
            $files = [$files];
        } elseif ($files instanceof Collection) {
            // Multiple files to be transformed
            $files = $files->getValues();
        } else {
            throw new MissingServiceException();
        }

        // An array of files to be transformed
        $data = [];
        foreach ($files as $file) {
            $data[$file->getId()] = $file->getWebPath();
        }

        return $data;
    }

    /**
     * The reverseTransform method of the transformer is used to convert from the
     * normalized format (Filesystem File) to the model format (File domain object).
     *
     * @param string[] $existingFiles Array of file ID => file web path
     *
     * @return array|mixed|null
     */
    public function reverseTransform($existingFiles)
    {
        /** @var \Symfony\Component\Finder\Finder $uploadedFiles */
        $uploadedFiles = null;
        // Get necessary information from the request
        $this->owningEntityObject = $this->sourceEntityRepository->find($_REQUEST['entity_id']);
        $this->mode = null === $this->owningEntityObject ? 'create' : 'edit';
        // Get the files that are uploaded in current session
        /** @var FilesystemOrphanageStorage $manager */
        $manager = $this->orphanageManager->get('gallery');
        // Finder (iterable) that points to the current gallery, only including the properties directory
        $uploadedFiles = $manager->getFiles()->filter(function (SplFileInfo $file) {
            return false !== \strpos($file->getRelativePath(), $this->fieldName, -\strlen($this->fieldName));
        });

        try {
            // Process uploaded and existing files
            $data = $this->reverseTransformUploadedAndExistingFiles($uploadedFiles, $existingFiles);
        } catch (\Exception $ex) {
            // Catch exception and turn it into a transformationFailedException
            $exception = new TransformationFailedException($ex->getMessage(), $ex->getCode());
        } finally {
            // Clear the files in gallery, for this field
            $this->cacheHelper->clear($this->fieldName, $this->options['objectId']);
        }

        // Throw exception if any was given
        if (null !== $exception = $exception ?? null) {
            throw $exception;
        }

        // Give return value when scalar is expected
        if (!$this->hasOwningEntityCollection()) {
            return $data[0] ?? null;
        }

        // Give return value for collection
        return $data;
    }

    private function reverseTransformUploadedAndExistingFiles(Finder $uploadedFiles, array $existingFiles)
    {
        $data = [];
        $uploadedFileCount = \count($uploadedFiles);
        $existingFileCount = \count($existingFiles);
        $totalFileCount = $uploadedFileCount + $existingFileCount;
        $objectId = $this->options['objectId'];

        if ($totalFileCount > $this->getMaxFiles()) {
            // Single files only
            throw new TransformationFailedException(
                ($this->isMultipleAllowed()
                    ? 'Expected ' . $this->getMaxFiles() . ' files'
                    : 'Expected a single file'
                ) . ', got ' . $uploadedFileCount . '.'
            );
        }

        if ($existingFileCount >= 1) {
            // Add files that already existed
            foreach ($existingFiles as $id => $path) {
                $existingFile = $this->targetEntityRepository->find($id);
                if (null === $existingFile) {
                    throw new TransformationFailedException('Invalid existing file.');
                }
                $data[] = $existingFile;
            }
        }

        if ($uploadedFileCount >= 1) {
            // Process files that were uploaded in this session, adding only images belong to the object
            foreach ($uploadedFiles as $uploadedFile) {
                if ($objectId === null || $objectId !== null && preg_split( '/[_.]/', $uploadedFile)[1] === (string) $objectId) {
                    $data[] = $this->processFile($uploadedFile);
                }
            }
            $this->cacheHelper->clear($this->fieldName, $this->options['objectId']);
        }
        return $data;
    }

    private function isMultipleAllowed(): bool
    {
        // If multiple option is defined by user configuration
        if ('autodetect' === $this->options['multiple']) {
            return $this->hasOwningEntityCollection();
        }

        return (bool)$this->options['multiple'];
    }

    private function getMaxFiles(): int
    {
        if (!$this->isMultipleAllowed()) {
            return 1;
        } elseif (is_int($this->options['maxFiles'])) {
            return $this->options['maxFiles'];
        } else {
            return DropzoneType::DEFAULT_MAX_FILES;
        }
    }

    private function hasOwningEntityCollection()
    {
        return $this->classHelper->hasCollection($this->sourceEntity, $this->fieldName);
    }

    public function processFile(\SplFileInfo $uploadedFile)
    {
        // Move files to gallery location and return the corresponding domain objects
        $data = null;

        $splitSourceEntity = explode('\\', $this->mapping['sourceEntity']);
        $entityClassName = end($splitSourceEntity);

        $path = $this->fileNamer->generateFilePath(
            $entityClassName,
            $this->mapping['fieldName'],
            $uploadedFile->getFilename()
        );

        $alreadyExists = null !== $this->targetEntityRepository->findOneBy(['path' => $path]);
        if ($alreadyExists) {
            throw new TransformationFailedException('Uploads should be checked for existence in the frontend');
        }

        /** @var BaseFile $fileEntity */
        $fileEntity = new $this->targetEntity();
        $fileEntity->setPath($path);
        $this->om->persist($fileEntity);
        $this->om->flush();
        $data = $fileEntity;

        $manager = $this->orphanageManager->get('gallery');
        $manager->uploadFiles([$uploadedFile]);

        return $data;
    }
}
