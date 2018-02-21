<?php
/**
 * FileGallery FormType specifically for using DropzoneJs as a frontend library
 *
 * @date   2018-02-20
 * @author webber
 */

namespace CuriousInc\FileUploadFormTypeBundle\Form\Type;

use Doctrine\Common\Persistence\ObjectManager;
use Oneup\UploaderBundle\Templating\Helper\UploaderHelper;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Sonata\AdminBundle\Form\Type\ModelHiddenType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class FileGalleryDropzone
 *
 * @package CMS3\CoreBundle\Form\Type
 */
class DropzoneType extends AbstractType
{
    /** @var UploaderHelper */
    private $uploaderHelper;

    /** @var OrphanageManager */
    private $orphanageManager;

    /** @var ObjectManager */
    private $objectManager;

    /**
     * FileGalleryDropzone constructor.
     *
     * @param UploaderHelper   $uploaderHelper
     * @param OrphanageManager $orphanageManager
     * @param ObjectManager    $objectManager
     */
    public function __construct(
        UploaderHelper $uploaderHelper,
        OrphanageManager $orphanageManager,
        ObjectManager $objectManager
    ) {
        $this->uploaderHelper   = $uploaderHelper;
        $this->orphanageManager = $orphanageManager;
        $this->objectManager    = $objectManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Todo: add transformer from https://github.com/sopinet/UploadFilesBundle/blob/master/Form/Type/DropzoneType.php
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        return [];
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function setDefaultOptions(OptionsResolver $resolver)
    {
        // Todo: strip what we don't need here
        $resolver->setDefaults(
            [
                'attr'          => [
                    'action' => $this->uploaderHelper->endpoint('gallery'),
                    'class'  => 'dropzone',
                ],
                'maxFiles'      => 8,
                'type'          => 'form_widget',
                'btnClass'      => 'btn btn-info btn-lg',
                'btnText'       => 'Files',
                'uploaderText'  => 'Drop files here to upload',
                'style_type'    => 'style_default',
                'acceptedFiles' => '.jpeg,.jpg,.png,.gif,.JPEG,.JPG,.PNG,.GIF',
                'compound'      => 'true',
                'mappedBy'      => 'default',
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        // Todo: strip what we don't need
        if (array_key_exists('maxFiles', $options)) {
            $view->vars['maxFiles'] = $options['maxFiles'];
        }
        if (array_key_exists('type', $options)) {
            $view->vars['type'] = $options['type'];
        }
        if (array_key_exists('btnClass', $options)) {
            $view->vars['btnClass'] = $options['btnClass'];
        }
        if (array_key_exists('btnText', $options)) {
            $view->vars['btnText'] = $options['btnText'];
        }
        if (array_key_exists('uploaderText', $options)) {
            $view->vars['uploaderText'] = $options['uploaderText'];
        }
        if (array_key_exists('attr', $options)) {
            $view->vars['attr'] = $options['attr'];
            if (!isset($view->vars['attr']['action'])) {
                $view->vars['attr']['action'] = $this->uploaderHelper->endpoint('gallery');
            }
        }
        if (array_key_exists('style_type', $options)) {
            $view->vars['style_type'] = $options['style_type'];
        }
        if (array_key_exists('acceptedFiles', $options)) {
            $view->vars['acceptedFiles'] = $options['acceptedFiles'];
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'dropzone';
    }
}
