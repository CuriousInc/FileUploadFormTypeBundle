<?php

namespace CuriousInc\FileUploadFormTypeBundle\Entity;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Model as ORMBehavior;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class BaseFile
 *
 * @ORM\MappedSuperclass(repositoryClass="CuriousInc\FileUploadFormTypeBundle\Entity\Repository\BaseFileRepository")
 */
class BaseFile implements FileInterface
{
    use ORMBehavior\Timestampable\Timestampable;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $path;

    /**
     * @ORM\PostRemove
     *
     * @param \Doctrine\Common\Persistence\Event\LifecycleEventArgs $event
     */
    public function deleteFile(LifecycleEventArgs $event)
    {
        $fs = new Filesystem();

        $fs->remove($this->getPath());
    }

    public function getAbsolutePath()
    {
        return null === $this->path ? null : $this->getUploadRootDir() . '/' . $this->path;
    }

    protected function getUploadRootDir()
    {
        return __DIR__ . '/../../../web' . $this->getUploadDir();
    }

    protected function getUploadDir()
    {
        return '';
    }

    public function getFrontPath()
    {
        return null === $this->path ? null : $this->getUploadDir() . '/' . $this->path;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        $fullPath = explode('/', $this->getPath());

        return $fullPath[\count($fullPath) - 1];
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param $path
     *
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get size of the file that is represented by this entity
     *
     * @return int|false The size of the file in bytes, or false if the file doesn't exist
     */
    public function getSize()
    {
        return filesize($this->getPath()) ?? 0;
    }

    public function getWebPath()
    {
        return null === $this->path ? null : $this->getUploadDir() . '/' . $this->path;
    }
}
