<?php

namespace CuriousInc\FileUploadFormTypeBundle\Entity;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Model as ORMBehavior;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class BaseFile
 *
 * @package CuriousInc\FileUploadFormTypeBundle\Entity
 *
 * @ORM\MappedSuperclass(repositoryClass="CuriousInc\FileUploadFormTypeBundle\Entity\Repository\BaseFileRepository")
 */
class BaseFile
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
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    public function getAbsolutePath()
    {
        return null === $this->path ? null : $this->getUploadRootDir() . '/' . $this->path;
    }

    protected function getUploadRootDir()
    {
        // the absolute directory path where uploaded
        // documents should be saved
        return __DIR__ . '/../../../web' . $this->getUploadDir();
    }

    protected function getUploadDir()
    {
        // get rid of the __DIR__ so it doesn't screw up
        // when displaying uploaded doc/image in the view.
        return '';
    }

    public function getWebPath()
    {
        return null === $this->path ? null : $this->getUploadDir() . '/' . $this->path;
    }

    public function getFrontPath()
    {
        return null === $this->path ? null : $this->getUploadDir() . '/' . $this->path;
    }

    public function getName()
    {
        return explode('/', $this->getPath())[2];
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
     * @ORM\PostRemove
     *
     * @param \Doctrine\Common\Persistence\Event\LifecycleEventArgs $event
     */
    public function deleteFile(LifecycleEventArgs $event)
    {
        $fs = new Filesystem();
        //$fs->remove($this->getAbsolutePath());
    }
}