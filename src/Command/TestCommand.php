<?php

namespace App\Command;

use App\MessageHandler\CheckVideoMessageHandler;
use App\Service\MediaProcessorService;
use Pbxg33k\MessagePack\Message\CheckVideoMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestCommand extends Command
{
    protected static $defaultName = 'app:test';

    /**
     * @var CheckVideoMessageHandler
     */
    private $messageHandler;

    /**
     * @var MediaProcessorService
     */
    private $mediaProcessorService;

    public function __construct(
        CheckVideoMessageHandler $messageHandler,
        MediaProcessorService $mediaProcessorService,
        string $name = null)
    {
        $this->messageHandler        = $messageHandler;
        $this->mediaProcessorService = $mediaProcessorService;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('Test message handler')
            ->addArgument('path', InputArgument::REQUIRED, 'path to file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);



        $message = new CheckVideoMessage($input->getArgument('path'));
        $this->messageHandler->setInteractive(true);
        $this->messageHandler->__invoke($message) ? $io->success('Finished') : $io->error('Failed');

        return 0;
    }
}
