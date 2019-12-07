<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MediaProcessorService
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger  = $logger;
    }

    public function getFrameCountViaFFMPEG(string $path)
    {
        $processArgs = [
            'ffprobe',
            '-v',
            'error',
            '-select_streams',
            'v:0',
            '-show_entries',
            'stream=nb_frames',
            '-of',
            'default=nokey=1:noprint_wrappers=1',
            $path
        ];

        $process = new Process($processArgs);
        try {
            $process->setTimeout(3600);
            $process->mustRun();

            if($process->isSuccessful()) {
                $output = trim($process->getOutput());
                if(is_numeric($output)) {
                    return intval($output);
                } else {
                    throw new \Exception('failed to read frame count');
                }
            } else {
                throw new ProcessFailedException($process);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('ffmpeg failed', [
                'path' => $path,
                'exception' => [
                    'message' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }
    }

    public function checkHealth(string $path, bool $strict, callable $cmdCallback, bool $propogateException = false): bool
    {
        $consistent = false;

        $this->logger->info('Checking video consistency', [
            'strict' => $strict,
            'path' => $path,
        ]);

        // command: "ffmpeg -v verbose -err_detect explode -xerror -i \"{$file->getPath()}\" -map 0:1 -f null -"
        $processArgs = [
            'ffmpeg',
            '-v',
            'verbose',
            '-err_detect',
            'explode',
            '-xerror',
            '-i',
            $path,
        ];
        if (!$strict) {
            $processArgs = array_merge($processArgs, ['-map', '0:1']);
        }
        $processArgs = array_merge($processArgs, [
            '-f',
            'null',
            '-',
        ]);

        $process = new Process($processArgs);
        try {
            $process->setTimeout(3600);
            $process->mustRun($cmdCallback);

            $consistent = 0 === $process->getExitCode();

            $this->logger->debug('ffmpeg output', [
                'file' => $path,
                'output' => $process->getOutput(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('ffmpeg failed', [
                'path' => $path,
                'exception' => [
                    'message' => $exception->getMessage(),
                ],
            ]);

            if ($propogateException) {
                throw $exception;
            }
        }

        $this->logger->info('video check completed', [
            'strict' => $strict,
            'result' => ($process->getExitCode() > 0) ? 'FAILED' : 'SUCCESS',
            'path' => $path,
        ]);

        return $consistent;
    }
}
