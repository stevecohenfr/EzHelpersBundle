<?php

namespace Smile\EzHelpersBundle\Command;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use Smile\EzHelpersBundle\Services\SmileFindService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteSubtreeCommand extends Command
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

    /** @var SmileFindService */
    private $findService;

    public function __construct(Repository $repository, SmileFindService $findService)
    {
        parent::__construct(null);
        $this->repository = $repository;
        $this->findService = $findService;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('stevecohenfr:subtree:delete')
            ->addOption('subtree', null,InputOption::VALUE_REQUIRED, 'Delete all children')
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
        $parentid = $input->getOption("subtree");

        $count = $this->findService->countChildrenTree(
            $this->repository->getLocationService()->loadLocation($parentid)
        );

        $question = new ConfirmationQuestion(
            "Are you sure you want to delete $count contents ? This operation is permanent. [y/N] ",
            false
        );
        if (!$this->questionHelper->ask($this->input, $this->output, $question)) {
            $this->output->writeln('Aborting.');
            return;
        }

        $children = $this->findService->findChildrenList(
            $this->repository->getLocationService()->loadLocation($parentid)
        );
        foreach ($children as $child) {
            $output->writeln("Deleting " . $child->getContent()->getName());
            $this->repository->getLocationService()->deleteLocation($child);
        }
        $output->writeln("Finish.");
    }
}
