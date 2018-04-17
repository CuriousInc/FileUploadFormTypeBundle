<?php
/**
 * REST Endpoint for files
 *
 * @date   2018-02-22
 * @author webber
 */

namespace CuriousInc\FileUploadFormTypeBundle\Controller;

use CuriousInc\FileUploadFormTypeBundle\Entity\BaseFile;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController as FosRestController;
use Oneup\UploaderBundle\Uploader\Storage\FilesystemOrphanageStorage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class FileController
 *
 * @package CuriousInc\FileUploadFormTypeBundle\Controller
 */
class FileController extends RestController
{
    /**
     * Delete a file by its identifier
     *
     * @ApiDoc(
     *     description="Delete a file",
     *     section="Files",
     *     requirements={
     *       {"name"="deleteName", "requirement"="[a-zA-Z\d]+"}
     *     },
     *     statusCodes={
     *       201="Created",
     *       400="Request is not properly formatted",
     *       403="Permission denied",
     *       500="An error occurred while handling your request"
     *     },
     * )
     *
     * @param Request $request
     *
     * @return Response|HttpException
     *
     * @Rest\Post("/deleteFile")
     */
    public function deleteFileAction(Request $request)
    {
        $fileId   = (int)$request->get('deleteId');
        $fileName = (string)$request->get('deleteName');

        // Don't delete files without a name specified
        if ('' === $fileName) {
            return $this->createHttpForbiddenException();
        }

        return $this->deleteTemporaryFile($fileName);
    }

    /**
     * Retrieve the files to be shown in the FileUpload FormType
     *
     * @ApiDoc(
     *     description="Retrieve a file by its name",
     *     section="Files",
     *     requirements={
     *       {"name"="filename", "requirement"="\[a-zA-Z\d]+"}
     *     },
     *     statusCodes={
     *       200="OK",
     *       400="Request is not properly formatted",
     *       403="Permission denied",
     *       500="An error occurred while handling your request"
     *     },
     *     output={
     *       "class"="CMS3\CoreBundle\Entity\Container",
     *       "groups"={"details"},
     *       "parsers"={"Nelmio\ApiDocBundle\Parser\JmsMetadataParser"},
     *     },
     * )
     *
     * @param Request $request
     * @param string  $filename
     *
     * @return Response|HttpException
     * @Rest\Get("/uploadedFile/{filename}")
     *
     */
    public function getFileAction(Request $request, string $filename)
    {
        $manager = $this->get('oneup_uploader.orphanage_manager')->get('gallery');
        $files = $manager->getFiles();

        /** @var BaseFile $file */
        foreach ($files as $file) {
            if ($file->getFileName() == $filename) {
                $response = new BinaryFileResponse($file);

                return $response;
            }
        }

        return $this->createHttpForbiddenException();
    }

    /**
     * @param string $fileName
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function deleteTemporaryFile(string $fileName): Response
    {
        // Get current temporary files from orphanage manager
        /** @var FilesystemOrphanageStorage $manager */
        $manager = $this->get('oneup_uploader.orphanage_manager')->get('gallery');

        $files = $manager->getFiles();
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            // Delete temporary file with given filename
            if ($file->getFileName() === $fileName) {
                $fs = new Filesystem();
                $fs->remove($file);

                return $this->createResponseDeletedOrNot();
            }
        }

        return $this->createResponseDeletedOrNot();
    }
}
