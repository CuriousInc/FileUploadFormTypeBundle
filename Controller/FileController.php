<?php
/**
 * REST Endpoint for files
 *
 * @author Webber <webber@takken.io>
 */

namespace CuriousInc\FileUploadFormTypeBundle\Controller;

use CuriousInc\FileUploadFormTypeBundle\Exception\InvalidFileNameException;
use CuriousInc\FileUploadFormTypeBundle\Exception\NotImplementedException;
use CuriousInc\FileUploadFormTypeBundle\Namer\FileNamer;
use CuriousInc\FileUploadFormTypeBundle\Service\ClassHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Oneup\UploaderBundle\Uploader\Storage\FilesystemOrphanageStorage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class FileController.
 */
class FileController extends RestController
{
    /**
     * Delete a session file by its name
     *
     * @ApiDoc(
     *     description="Delete a file",
     *     section="Files",
     *     requirements={
     *       {"name"="name", "requirement"="[a-zA-Z\d]+"}
     *     },
     *     statusCodes={
     *       204="No Content",
     *       400="Request is not properly formatted",
     *       403="Forbidden",
     *       500="An error occurred while handling your request"
     *     },
     * )
     *
     * @param Request $request
     *
     * @param FilesystemOrphanageStorage $orphanageStorage
     * @param FileNamer $fileNamer
     * @return Response|HttpException
     *
     * @throws InvalidFileNameException
     * @Rest\Post("/deleteSessionFile")
     */
    public function deleteSessionFileAction(Request $request, FilesystemOrphanageStorage $orphanageStorage, FileNamer $fileNamer)
    {
        $name = (string)$request->get('name');

        // Don't delete files without a name specified
        if ('' === $name) {
            return $this->createHttpForbiddenException();
        }

        // Get current temporary files from orphanage manager
        $files = $orphanageStorage->getFiles();
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            // Delete temporary file with given filename
            if ($file->getRelativePathname() === $fileNamer->convertUnderscorePath($name)) {
                $fs = new Filesystem();
                $fs->remove($file);

                return $this->createResponseDeletedOrNot();
            }
        }

        return $this->createResponseDeletedOrNot();
    }

    /**
     * Delete a persisted file by its identifier
     *
     * @ApiDoc(
     *     description="Delete a file from given domain object",
     *     section="Files",
     *     requirements={
     *       {"name"="id", "requirement"="[a-zA-Z\d]+"},
     *       {"name"="sourceEntity", "requirement"="[a-zA-Z\\\d]+"},
     *       {"name"="fieldName", "requirement"="[a-zA-Z\d]+"},
     *       {"name"="targetEntity", "requirement"="[a-zA-Z\\\d]+"},
     *     },
     *     statusCodes={
     *       204="No Content",
     *       400="Request is not properly formatted",
     *       403="Forbidden",
     *       500="An error occurred while handling your request"
     *     },
     * )
     *
     * @param Request $request
     *
     * @param ClassHelper $classHelper
     * @return Response|HttpException
     *
     * @Rest\Post("/deletePersistedFile")
     */
    public function deletePersistedFileAction(Request $request, ClassHelper $classHelper)
    {
        $em = $this->getDoctrine()->getManager();

        $id = (int)$request->get('id');
        $sourceEntityId = (string)$request->get('sourceEntityId');
        $sourceEntityClassName = (string)$request->get('sourceEntity');
        $targetEntityClassName = (string)$request->get('targetEntity');
        $fieldName = (string)$request->get('fieldName');
        $sourceEntityRepository = $em->getRepository($sourceEntityClassName);
        $targetEntityRepository = $em->getRepository($targetEntityClassName);
        $sourceEntity = $sourceEntityRepository->find($sourceEntityId);
        $targetEntity = $targetEntityRepository->find($id);

        // Remove the file(s) from owning entity
        if ($classHelper->hasCollection($sourceEntity, $fieldName)) {
            try {
                $removeMethod = $classHelper->retrieveRemover($sourceEntity, $fieldName);
            } catch (NotImplementedException $ex) {
                return $this->createHttpForbiddenException();
            }

            $sourceEntity->$removeMethod($targetEntity);
        } else {
            $setMethod = $classHelper->retrieveSetter($sourceEntity, $fieldName);
            $sourceEntity->$setMethod(null);
        }

        // Persist owning entity
        $em->persist($sourceEntity);
        $em->flush();

        return $this->createResponseDeletedOrNot();
    }
}
