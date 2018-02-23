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
        $var = '';
        $files = $this->get('oneup_uploader.orphanage.gallery')->getFiles();
        $deleteName = $request->get('deleteName');
        /** @var BaseFile $file */
        foreach ($files as $file) {
            $var .= "\n" . print_r($file, true) ;
            if ($file->getFileName() === $deleteName) {
                $fs = new Filesystem();
                $fs->remove($file);

                return $this->createResponseCreated();
            }
        }

        return $this->createHttpForbiddenException($var);
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
        $files = $this->get('oneup_uploader.orphanage.gallery')->getFiles();

        /** @var BaseFile $file */
        foreach ($files as $file) {
            if ($file->getFileName() == $filename) {
                $response = new BinaryFileResponse($file);

                return $response;
            }
        }

        return $this->createHttpForbiddenException();
    }
}
