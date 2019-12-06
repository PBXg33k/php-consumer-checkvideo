<?php
namespace App\Tests\MessageHandler;

use App\Entity\Inode;
use App\Repository\JavFileRepository;
use org\bovigo\vfs\content\LargeFileContent;
use org\bovigo\vfs\vfsStreamContent;
use Pbxg33k\MessagePack\Message\CalculateFileHashesMessage;
use Pbxg33k\MessagePack\Message\CheckVideoMessage;
use Pbxg33k\MessagePack\Message\GenerateThumbnailMessage;
use App\MessageHandler\CheckVideoMessageHandler;
use App\Service\MediaProcessorService;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Entity\JavFile;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CheckVideoMessageHandlerTest extends TestCase
{
    /**
     * @var MediaProcessorService|MockObject
     */
    private $mediaProcessorService;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var MessageBusInterface|MockObject
     */
    private $messageBus;

    /**
     * @var CheckVideoMessageHandler
     */
    private $handler;

    /**
     * @var vfsStreamFile
     */
    private $dummyFile;

    protected function setUp()
    {
        $this->mediaProcessorService = $this->getMockBuilder(MediaProcessorService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->messageBus = $this->getMockBuilder(MessageBusInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->handler = new CheckVideoMessageHandler(
            $this->mediaProcessorService,
            $this->logger,
            $this->messageBus
        );

        $root = vfsStream::setup();
        $this->dummyFile = vfsStream::newFile('ABC-123.mp4')
            ->withContent(LargeFileContent::withGigabytes(1))
            ->at($root);
    }

    /**
     * @test
     */
    public function willCheckNewVideoFile()
    {
        $callback = function($type, $buffer) {};
        $message = new CheckVideoMessage($this->dummyFile->url(), $callback);

        $this->mediaProcessorService->expects($this->once())
            ->method('checkHealth')
            ->with(
                $this->dummyFile->url(),
                true,
                function($subject) {
                    return is_callable($subject);
                }
            )
            ->willReturn(true);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [self::isInstanceOf(GenerateThumbnailMessage::class)],
                [self::isInstanceOf(CalculateFileHashesMessage::class)]
            )
            ->willReturn(new Envelope($message));

        $handler = $this->handler;
        $handler($message);
    }

    /**
     * @test
     */
    public function willNotDispatchMessagesIfVideoNotConsistent()
    {
        $callback = function($type, $buffer) {};
        $message = new CheckVideoMessage($this->dummyFile->url(), $callback);

        $this->mediaProcessorService->expects($this->once())
            ->method('checkHealth')
            ->with(
                $this->dummyFile->url(),
                true,
                function($subject) {
                    return is_callable($subject);
                }
            )
            ->willReturn(false);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $handler = $this->handler;
        $handler($message);
    }

//    /**
//     * @test
//     */
//    public function willNotRecheckFileButDispatchMessagesIfAlreadyCheckedAndConsistent()
//    {
//
//        $callback = function($type, $buffer) {};
//        $message = new CheckVideoMessage('test', $callback);
//
//        $inode    = (new Inode())->setConsistent(true)->setChecked(true);
//        $javFile  = (new JavFile())->setId(1)->setInode($inode)->setPath($this->dummyFile->url());
//
//        $this->javFileRepository->expects($this->once())
//            ->method('findOneByPath')
//            ->with('test')
//            ->willReturn($javFile);
//
//        $this->mediaProcessorService->expects($this->never())
//            ->method('checkHealth');
//
//        $this->messageBus->expects($this->exactly(2))
//            ->method('dispatch')
//            ->withConsecutive(
//                self::isInstanceOf(GenerateThumbnailMessage::class),
//                self::isInstanceOf(CalculateFileHashesMessage::class)
//            )
//            ->willReturn(new Envelope($message));
//
//        $handler = $this->handler;
//        $handler($message);
//    }
//
//    /**
//     * @test
//     */
//    public function willNotRecheckFileAndDispatchMessagesIfAlreadyCheckedAndNotConsistent()
//    {
//
//        $callback = function($type, $buffer) {};
//        $message = new CheckVideoMessage('test', $callback);
//
//        $inode    = (new Inode())->setConsistent(false)->setChecked(true);
//        $javFile  = (new JavFile())->setPath('test')->setInode($inode)->setPath($this->dummyFile->url());
//
//        $this->javFileRepository->expects($this->once())
//            ->method('findOneByPath')
//            ->with('test')
//            ->willReturn($javFile);
//
//        $this->mediaProcessorService->expects($this->never())
//            ->method('checkHealth');
//
//        $this->messageBus->expects($this->never())
//            ->method('dispatch');
//
//        $handler = $this->handler;
//        $handler($message);
//    }
}
