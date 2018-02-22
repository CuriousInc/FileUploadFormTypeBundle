<?php
/**
 * FileGallery FormType specifically for using DropzoneJs as a frontend library
 *
 * @date   2018-02-20
 * @author webber
 */

namespace CuriousInc\FileUploadFormTypeBundle\Form\Type;

use CuriousInc\FileUploadFormTypeBundle\Form\Transformer\FilesToEntitiesTransformer;
use Doctrine\Common\Persistence\ObjectManager;
use Oneup\UploaderBundle\Templating\Helper\UploaderHelper;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Symfony\Component\Form\AbstractType;
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
        $transformer = new FilesToEntitiesTransformer(
            $this->objectManager,
            $this->orphanageManager,
            $builder->getName(),
            $options['mappedBy']
        );

        $builder->addModelTransformer($transformer);
    }

    /**
     * Backward compatibility for SF <= 2.6.
     *
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolver $resolver)
    {
        $this->configureOptions($resolver);
    }

    /**
     * Configure options for this FormType
     *
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
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
