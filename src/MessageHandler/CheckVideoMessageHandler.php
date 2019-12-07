<?php

namespace App\MessageHandler;

use App\Entity\JavFile;
use App\Repository\JavFileRepository;
use Pbxg33k\MessagePack\Message\CalculateFileHashesMessage;
use Pbxg33k\MessagePack\Message\CheckVideoMessage;
use Pbxg33k\MessagePack\Message\GenerateThumbnailMessage;
use App\Message\FooMessage;
use App\Service\MediaProcessorService;
use Doctrine\ORM\EntityManagerInterface;
use Pbxg33k\MessagePack\Message\VideoCheckedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CheckVideoMessageHandler implements MessageHandlerInterface
{
    /**
     * @var MediaProcessorService
     */
    private $mediaProcessorService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var bool
     *
     * Will be set to true if handler is fired from CLI (for testing purposes)
     */
    private $interactive = false;

    public function __construct(
        MediaProcessorService $mediaProcessorService,
        LoggerInterface $logger,
        MessageBusInterface $messageBus
    ) {
        $this->mediaProcessorService = $mediaProcessorService;
        $this->logger = $logger;
        $this->messageBus = $messageBus;
    }

    public function setInteractive(bool $interactive)
    {
        $this->interactive = $interactive;
    }

    public function __invoke(CheckVideoMessage $message)
    {
        if(!is_file($message->getPath())) {
            $this->logger->error('FILE NOT FOUND', [
                'path'  => $message->getPath()
            ]);
            return;
        }

        $videoLength = $message->getVideoLength() ?? $this->mediaProcessorService->getFrameCountViaFFMPEG($message->getPath());

        $startTime = time();
        // @todo Move this check to dispatcher
//        if (!$javFile->getInode()->isChecked()) {
            $consistent = $this->mediaProcessorService->checkHealth(
                $message->getPath(),
                true,
                function ($type, $buffer) use ($message, $videoLength) {
                    $callback = $message->getCallback();
                    if (is_callable($callback)) {
                        $callback($type, $buffer);
                    } else {
                        if (false !== strpos($buffer, 'time=')) {
                            if($videoLength && preg_match('/frame=\s*?(?<frame>[0-9]+)/', $buffer, $matches)) {
                                $this->logger->info('Progress '.number_format(($matches['frame'] / $videoLength) * 100, 2).'%', [
                                    'path' => $message->getPath(),
                                    'frame_count' => $videoLength,
                                    'current_frame' => $matches['frame'],
                                    'perc' => number_format(($matches['frame'] / $videoLength) * 100, 2).'%',
                                ]);
                            }
                        } else {
                            $this->logger->debug($buffer);
                        }
                    }
                }
            );

        $this->messageBus->dispatch((new VideoCheckedMessage($message->getPath()))->setChecked(true)->setConsistent($consistent));
        return $consistent;
    }
}
