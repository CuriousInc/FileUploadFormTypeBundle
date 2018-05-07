<?php
/**
 * Command-line utility for migrating data from SonataMediaBundle to CuriousFileUpload.
 *
 * @author Webber <webber@takken.io>
 */

namespace CuriousInc\FileUploadFormTypeBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MigrateCommand.
 */
class MigrateCommand extends ContainerAwareCommand
{
    /**
     * Command configuration.
     */
    protected function configure()
    {
        // Configure command
        $this
            ->setName('curious:uploadbundle:migrate')
            ->setDescription('Migrate existing media from SonataMediaBundle to Curious FileUploadBundle.');

        // Configure arguments
        $this
            ->addArgument(
                'className',
                InputArgument::REQUIRED,
                'Fully qualified class name of the entity owning the media.'
            )
            ->addArgument(
                'fromProperty',
                InputArgument::REQUIRED,
                'Property name describing the relation to Media, to migrate from.'
            )
            ->addArgument(
                'toProperty',
                InputArgument::REQUIRED,
                'Property name describing the relation to newly implemented File, to migrate to.'
            )
            ->addArgument(
                'fromIntersectionProperty',
                InputArgument::OPTIONAL,
                'Property name describing the relation between the intersection entity and the media entity'
            );

        // Configure options (always optional)
        $this
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Using this option will continue the process even if the files related to the Media objects ' . "\n"
                . ' do not exist.'
            );
    }

    /**
     * Execute the current command.
     *
     * Migrate an Entity from using SonataMediaBundle:Media to using CuriousFileUpload:BaseFile
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get inputs
        $className                = $input->getArgument('className');
        $fromProperty             = $input->getArgument('fromProperty');
        $toProperty               = $input->getArgument('toProperty');
        $fromIntersectionProperty = $input->getArgument('fromIntersectionProperty');
        $options                  = $input->getOptions();

        // Check whether class exists or not
        if (!class_exists($className)) {
            throw new \InvalidArgumentException('Given className could not be found.');
        }

        // Check whether fromProperty exists or not
        $reflectionClass = new \ReflectionClass($className);
        if (!$reflectionClass->hasProperty($fromProperty)) {
            throw new \InvalidArgumentException('Given fromProperty does not exist in given class.');
        }

        // Check whether toProperty exists or not
        if (!$reflectionClass->hasProperty($toProperty)) {
            throw new \InvalidArgumentException('Given toProperty does not exist in given class.');
        }

        // Execute the migration action
        $migrator = $this->getContainer()->get('curious_file_upload.migrator.media_bundle');
        $migrator->migrateEntity($className, $fromProperty, $toProperty, $fromIntersectionProperty, $options);
    }
}
