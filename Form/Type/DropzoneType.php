<?php
/**
 * FileGallery FormType specifically for using DropzoneJs as a frontend library
 *
 * @date   2018-02-20
 * @author webber
 */

namespace CuriousInc\FileUploadFormTypeBundle\Form\Type;

use CuriousInc\FileUploadFormTypeBundle\Exception\NotImplementedException;
use CuriousInc\FileUploadFormTypeBundle\Form\DataTransformer\SessionFilesToEntitiesTransformer;
use Doctrine\Common\Persistence\ObjectManager;
use Oneup\UploaderBundle\Templating\Helper\UploaderHelper;
use Oneup\UploaderBundle\Uploader\Orphanage\OrphanageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class FileGalleryDropzone.
 */
class DropzoneType extends AbstractType
{
    public const DEFAULT_MAX_FILES = 8;
    public const DEFAULT_MAX_SIZE  = 8;

    /** @var ContainerInterface */
    private $container;

    /** @var UploaderHelper */
    private $uploaderHelper;

    /** @var OrphanageManager */
    private $orphanageManager;

    /** @var ObjectManager */
    private $objectManager;

    /** @var array */
    private $mapping;

    /**
     * FileGalleryDropzone constructor.
     *
     * @param ContainerInterface $container
     * @param UploaderHelper     $uploaderHelper
     * @param OrphanageManager   $orphanageManager
     * @param ObjectManager      $objectManager
     */
    public function __construct(
        ContainerInterface $container,
        UploaderHelper $uploaderHelper,
        OrphanageManager $orphanageManager,
        ObjectManager $objectManager
    ) {
        $this->container        = $container;
        $this->uploaderHelper   = $uploaderHelper;
        $this->orphanageManager = $orphanageManager;
        $this->objectManager    = $objectManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $transformer = new SessionFilesToEntitiesTransformer(
            $this->container,
            $this->objectManager,
            $this->orphanageManager,
            $options,
            $this->getMapping($options)
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
                'attr' => [
                    'action' => $this->uploaderHelper->endpoint('gallery'),
                    'class' => 'dropzone',
                ],
                'multiple' => 'autodetect',
                'maxFiles' => static::DEFAULT_MAX_FILES,
                'maxFileSize' => static::DEFAULT_MAX_SIZE,
                'type' => 'form_widget',
                'btnClass' => 'btn btn-info btn-lg',
                'btnText' => 'Files',
                'uploaderText' => 'Drop files here to upload',
                'style_type' => 'style_default',
                'acceptedFiles' => '.jpeg,.jpg,.png,.gif,.JPEG,.JPG,.PNG,.GIF',
                'compound' => 'true',
                'deletingAllowed' => true,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (array_key_exists('multiple', $options)) {
            $view->vars['multiple'] = $options['multiple'];
        }
        if (array_key_exists('maxFiles', $options)) {
            $view->vars['maxFiles'] = $options['maxFiles'];
        }
        if (array_key_exists('maxFileSize', $options)) {
            $view->vars['maxFileSize'] = $options['maxFileSize'];
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
        if (array_key_exists('deletingAllowed', $options)) {
            $view->vars['deletingAllowed'] = $options['deletingAllowed'];
        }

        $view->vars['mapping'] = $this->getMapping($options);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'dropzone';
    }

    /**
     * Determine the mapping for this field, containing the owning entity, its property and the file entity.
     *
     * @param array $options the options provided to this field.
     *
     * @return array The mapping information about the mapping between the owning entity and the file entity.
     */
    private function getMapping(array $options): array
    {
        if (null !== $this->mapping) {
           return $this->mapping;
        }

        $fieldDescriptionClassName = 'Sonata\DoctrineORMAdminBundle\Admin\FieldDescription';
        if (array_key_exists('sonata_field_description', $options)
            && class_exists($fieldDescriptionClassName)
            && $options['sonata_field_description'] instanceof $fieldDescriptionClassName) {
            $this->mapping = $options['sonata_field_description']->getAssociationMapping();
        } else {
            throw new NotImplementedException('Cannot determine mapping without Sonata\\DoctrineORMAdminBundle.');
        }

        return $this->mapping;
    }
}
