<?php
/**
 * REST Endpoint for files
 *
 * @author Webber <webber@takken.io>
 */

namespace CuriousInc\FileUploadFormTypeBundle\Controller;

use CuriousInc\FileUploadFormTypeBundle\Exception\NotImplementedException;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Oneup\UploaderBundle\Uploader\Storage\FilesystemOrphanageStorage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Swagger\Annotations as SWG;

/**
 * Class FileController.
 */
class FileController extends RestController
{
    /**
     * @Operation(
     *     tags={"Files"},
     *     summary="Delete a session file by its name.",
     *     method="post",
     *     @SWG\Parameter(
     *         name="name",
     *         in="body",
     *         description="Name of the file to delete.",
     *         required=true,
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="No Content"
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Request is not properly formatted"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="You do not have access to the wells list"
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="An error occurred while handling your request"
     *     )
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
        $namer = $this->get('curious_file_upload.file_namer');
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($files as $file) {
            // Delete temporary file with given filename
            if ($file->getRelativePathname() === $namer->convertUnderscorePath($name)) {
                $fs = new Filesystem();
                $fs->remove($file);

                return $this->createResponseDeletedOrNot();
            }
        }

        return $this->createResponseDeletedOrNot();
    }

    /**
     * @Operation(
     *     tags={"Files"},
     *     summary="Delete a persisted file by its identifier.",
     *     method="post",
     *     @SWG\Parameter(
     *         name="id",
     *         in="body",
     *         required=true,
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Parameter(
     *         name="sourceEntity",
     *         in="body",
     *         required=true,
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Parameter(
     *         name="fieldName",
     *         in="body",
     *         required=true,
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Parameter(
     *         name="targetEntity",
     *         in="body",
     *         required=true,
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="No Content"
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Request is not properly formatted"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="You do not have access to the wells list"
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="An error occurred while handling your request"
     *     )
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
        $classHelper = $this->get('curious_file_upload.service.class_helper');

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
