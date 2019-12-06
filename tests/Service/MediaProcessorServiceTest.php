<?php


namespace App\Tests\Service;


use App\Service\MediaProcessorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MediaProcessorServiceTest extends TestCase
{
    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var MediaProcessorService
     */
    private $subject;

    protected function setUp()
    {
        $this->logger    = $this->createMock(LoggerInterface::class);

        $this->subject = new MediaProcessorService($this->logger);
    }

    public function willProcessFile() {}
    public function willReturnFalseIfFFMPEGFails() {}
    public function willThrowExceptionIfPropogateExceptionIsTrue() {}

}
