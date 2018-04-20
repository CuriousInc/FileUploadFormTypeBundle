<?php

namespace CuriousInc\FileUploadFormTypeBundle\Entity;

/**
 * Interface FileInterface.
 */
interface FileInterface
{
    public function getId();

    public function getPath();

    public function getWebPath();

    public function getAbsolutePath();

    public function getSize();
}
