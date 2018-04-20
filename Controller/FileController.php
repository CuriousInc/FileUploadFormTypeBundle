<?php
/**
 * REST Endpoint for files
 *
 * @date   2018-02-22
 * @author webber
 */

namespace CuriousInc\FileUploadFormTypeBundle\Controller;

use CuriousInc\FileUploadFormTypeBundle\Exception\NotImplementedException;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Oneup\UploaderBundle\Uploader\Storage\FilesystemOrphanageStorage;
use Symfony\Component\Filesystem\Filesystem;
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
     * @return Response|HttpException
     *
     * @Rest\Post("/deleteSessionFile")
     */
    public function deleteSessionFileAction(Request $request)
    {
        $name = (string)$request->get('name');

        // Don't delete files without a name specified
        if ('' === $name) {
            return $this->createHttpForbiddenException();
        }

        // Get current temporary files from orphanage manager
        /** @var FilesystemOrphanageStorage $manager */
        $manager = $this->get('oneup_uploader.orphanage_manager')->get('gallery');

        $files = $manager->getFiles();
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            // Delete temporary file with given filename
            if ($file->getFileName() === $name) {
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
     * @return Response|HttpException
     *
     * @Rest\Post("/deletePersistedFile")
     */
    public function deletePersistedFileAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $cardinalityDetector = $this->get('curious_file_upload.cardinality_detector');

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
        if ($cardinalityDetector->canHaveMultiple($sourceEntity, $fieldName)) {
            try {
                $removeMethod = $this->retrieveRemoveMethod($sourceEntity, $fieldName);
            } catch (NotImplementedException $e) {
                return $this->createHttpForbiddenException();
            }
            $sourceEntity->$removeMethod($targetEntity);
        } else {
            $setMethod = 'set' . ucfirst($fieldName);
            $sourceEntity->$setMethod(null);
        }

        // Persist owning entity
        $em->persist($sourceEntity);
        $em->flush();

        return $this->createResponseDeletedOrNot();
    }

    private function retrieveRemoveMethod($entity, string $fieldName): string
    {
        $cardinalityDetector = $this->get('curious_file_upload.cardinality_detector');

        $method = 'remove' . $cardinalityDetector->prepareString($fieldName);
        if (method_exists($entity, $method)) {
            return $method;
        }

        $method = 'remove' . ucfirst($fieldName);
        if (method_exists($entity, $method)) {
            return $method;
        }

        throw new NotImplementedException("Invalid domain object");
    }
}
