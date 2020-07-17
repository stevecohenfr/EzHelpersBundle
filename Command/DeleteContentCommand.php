<?php

/**
 * This file is part of the eZ Publish Kernel package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Smile\EzHelpersBundle\Command;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Exception;

/**
 * Console Command which deletes a given Translation from all the Versions of a given Content Item.
 */
class DeleteContentCommand extends Command
{
    /** @var \eZ\Publish\API\Repository\Repository */
    private $repository;

    /** @var \eZ\Publish\API\Repository\ContentService */
    private $contentService;

    /** @var \Symfony\Component\Console\Input\InputInterface */
    private $input;

    /** @var \Symfony\Component\Console\Output\OutputInterface */
    private $output;

    /** @var \Symfony\Component\Console\Helper\QuestionHelper */
    private $questionHelper;

    public function __construct(Repository $repository)
    {
        parent::__construct(null);
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('stevecohenfr:content:delete')
            ->addArgument('content-ids', InputArgument::REQUIRED, 'Content Object Ids (separated by comma)')
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_OPTIONAL,
                'eZ Platform username (with Role containing at least Content policies: read, versionread, edit, remove, versionremove)',
                'admin'
            )
            ->setDescription('Delete Content Items');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->input = $input;
        $this->output = $output;
        $this->questionHelper = $this->getHelper('question');
        $this->contentService = $this->repository->getContentService();

        $this->repository->getPermissionResolver()->setCurrentUserReference(
            $this->repository->getUserService()->loadUserByLogin($input->getOption('user'))
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $contentIds = explode(",", $input->getArgument('content-ids'));
        if (array_filter($contentIds,'is_int') === false) {
            throw new InvalidArgumentException(
                'content-ids',
                'Content Object Ids have to be integers'
            );
        }
        $this->output->writeln(
            '<comment>**NOTE**: Make sure to run this command using the same SYMFONY_ENV setting as your eZ Platform installation does</comment>'
        );

        $contentInfos = $this->contentService->loadContentInfoList($contentIds);

        foreach ($contentInfos as $contentInfo) {
            try {
                $this->repository->beginTransaction();
                $contentName = $contentInfo->name;

                $question = new ConfirmationQuestion(
                    "Are you sure you want to delete \"{$contentName}\"#{$contentInfo->id} ? This operation is permanent. [y/N] ",
                    false
                );
                if (!$this->questionHelper->ask($this->input, $this->output, $question)) {
                    // Rollback any cleanup change (see above)
                    $this->repository->rollback();
                    $this->output->writeln('Reverting and aborting.');

                    return;
                }
                $output->writeln(
                    "<info>Deleting {$contentName}</info>"
                );

                $this->contentService->deleteContent($contentInfo);

                $output->writeln('<info>Content deleted</info>');

                $this->repository->commit();
            } catch (Exception $e) {
                $this->repository->rollback();
                throw $e;
            }
        }
    }
}
