<?php

namespace CuriousInc\FileUploadFormTypeBundle\Form\DataTransformer;

use CuriousInc\FileUploadFormTypeBundle\Detector\CardinalityDetector;
use CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile;
use CuriousInc\FileUploadFormTypeBundle\Exception\FileTransformationException;
use CuriousInc\FileUploadFormTypeBundle\Form\Type\DropzoneType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Class SessionFilesToEntitiesTransformer.
 */
class SessionFilesToEntitiesTransformer implements DataTransformerInterface
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var OrphanageManager
     */
    private $orphanageManager;

    /**
     * @var string the fully qualified className for the entity owning the file(s)
     */
    private $sourceEntity;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $sourceEntityRepository;

    /**
     * @var \Doctrine\ORM\Mapping\Entity
     */
    private $owningEntityObject;

    /**
     * string the property in the owning entity referencing the file(s)
     */
    private $fieldName;

    /**
     * @var string the fully qualified className for the image entity
     */
    private $targetEntity;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $targetEntityRepository;

    /**
     * @var array
     */
    private $options;

    /**
     * @param ObjectManager    $om
     * @param OrphanageManager $orphanageManager
     */
    public function __construct(ObjectManager $om, OrphanageManager $orphanageManager, $options, $mapping)
    {
        $this->om                     = $om;
        $this->orphanageManager       = $orphanageManager;
        $this->sourceEntity           = $mapping['sourceEntity'];
        $this->sourceEntityRepository = $this->om->getRepository($this->sourceEntity);
        $this->fieldName              = $mapping['fieldName'];
        $this->targetEntity           = $mapping['targetEntity'];
        $this->targetEntityRepository = $this->om->getRepository($this->targetEntity);
        $this->options                = $options;
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
            throw new FileTransformationException();
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
     * @param $existingFiles
     *
     * @return array|mixed|null
     */
    public function reverseTransform($existingFiles)
    {
        /** @var \Symfony\Component\Finder\Finder $uploadedFiles */
        $uploadedFiles = null;
        // Get necessary information from the request
        $this->owningEntityObject = $this->sourceEntityRepository->find($_REQUEST['entity_id']);
        $this->mode               = null === $this->owningEntityObject ? 'create' : 'edit';
        // Get the files that are uploaded in current session
        $manager = $this->orphanageManager->get('gallery');
        // Finder (iterable) that points to the current gallery
        $uploadedFiles = $manager->getFiles();

        try {
            // Process uploaded and existing files
            $data = $this->reverseTransformUploadedAndExistingFiles($uploadedFiles, $existingFiles);
        } catch (\Exception $ex) {
            // Catch exception and turn it into a transformationFailedException
            $exception = new TransformationFailedException($ex->getMessage(), $ex->getCode());
        } finally {
            // Clear the files in gallery
            $this->clearFiles($uploadedFiles);
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

        if ($totalFileCount > $this->getMaxFiles()) {
            // Single files only
            throw new TransformationFailedException(
                ($this->isMultipleAllowed()
                    ? 'Expected ' . $this->getMaxFiles() . 'files'
                    : 'Expected a single file'
                ) . ', got ' . $uploadedFileCount . '.'
            );
        } elseif ($existingFileCount >= 1) {
            // Add files that already existed
            foreach ($existingFiles as $id => $path) {
                $existingFile = $this->targetEntityRepository->find($id);
                if (null === $existingFile) {
                    throw new TransformationFailedException('Invalid existing file.');
                }
                $data[] = $existingFile;
            }
        } elseif ($uploadedFileCount >= 1) {
            // Process files that were uploaded in this session
            foreach ($uploadedFiles as $uploadedFile) {
                $data[] = $this->processFile($uploadedFile);
            }
            $this->clearFiles($uploadedFiles);
        } else {
            // No files were uploaded or already existed ($totalFileCount <= 0)
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
        $cardinalityDetector = new CardinalityDetector();

        return $cardinalityDetector->canHaveMultiple($this->sourceEntity, $this->fieldName);
    }

    private function processFile(\SplFileInfo $uploadedFile)
    {
        // Move files to gallery location and return the corresponding domain objects
        $data = null;

        // TODO - Add uploadpath to options and link it to one-up uploaderbundle endpoints
        $alreadyExists = null !== $this->targetEntityRepository->findOneBy([
                'path' => 'uploads/gallery/' . $uploadedFile->getFilename(),
            ]);

        if ($alreadyExists) {
            // TODO - When the frontend already does check (within this bundle), we should actually just skip the file.
            throw new TransformationFailedException('Uploads should be checked for existence in the frontend');
        }

        /** @var BaseFile $fileEntity */
        $fileEntity = new $this->targetEntity();
        $fileEntity->setPath('uploads/gallery/' . $uploadedFile->getFileName());
        $this->om->persist($fileEntity);
        $this->om->flush();
        $data = $fileEntity;

        // TODO - Remove this duplicate variable $manager in multiple methods
        $manager = $this->orphanageManager->get('gallery');
        $manager->uploadFiles([$uploadedFile]);

        return $data;
    }

    private function clearFiles(Finder $files)
    {
        $fileSystem = new Filesystem();
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $fileSystem->remove($file);
        }
    }
}