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

    public function __construct(
        MediaProcessorService $mediaProcessorService,
        LoggerInterface $logger,
        MessageBusInterface $messageBus
    ) {
        $this->mediaProcessorService = $mediaProcessorService;
        $this->logger = $logger;
        $this->messageBus = $messageBus;
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
                    // Force ping to DBAL to prevent time-out
                    if ((time() - $startTime) >= 30) {
                        $this->logger->debug('KEEPALIVE');
                        $this->messageBus->dispatch(new FooMessage());
                        $startTime = time();
                    }

                    $callback = $message->getCallback();
                    if (is_callable($callback)) {
                        $callback($type, $buffer);
                    } else {
                        if (false !== strpos($buffer, ' time=')) {
//                             Calculate/estimate progress
//                            if (preg_match('~time=(?<hours>[\d]{1,2})\:(?<minutes>[\d]{2})\:(?<seconds>[\d]{2})?(?:\.(?<millisec>[\d]{0,3}))\sbitrate~', $buffer, $matches)) {
//                                $time = ($matches['hours'] * 3600 + $matches['minutes'] * 60 + $matches['seconds']) * 1000 + ($matches['millisec'] * 10);
                                // @todo Pass metadata with CheckVideoMessage
//                                $this->logger->debug('Progress '.number_format(($time / $javFile->getInode()->getLength()) * 100, 2).'%', [
//                                    'path' => $javFile->getPath(),
//                                    'length' => $javFile->getInode()->getLength(),
//                                    'mark' => $time,
//                                    'perc' => number_format($time / $javFile->getInode()->getLength() * 100, 2).'%',
//                                ]);
//                            }
//                        } else {
                            $this->logger->debug($buffer);
                        }
                    }
                }
            );
//        }

        if($consistent) {
            $this->messageBus->dispatch(new GenerateThumbnailMessage($message->getPath()));
            $this->messageBus->dispatch(new CalculateFileHashesMessage($message->getPath(), CalculateFileHashesMessage::HASH_XXHASH | CalculateFileHashesMessage::HASH_MD5));
        }
    }
}
