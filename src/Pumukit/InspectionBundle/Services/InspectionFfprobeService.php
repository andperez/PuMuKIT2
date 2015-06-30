<?php

namespace Pumukit\InspectionBundle\Services;

use Pumukit\SchemaBundle\Document\Track;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

class InspectionFfprobeService implements InspectionServiceInterface
{

    private $logger;
    private $command;

    public function __construct($command = null, LoggerInterface $logger = null)
    {
        $this->command = $command ?: 'avprobe -v quiet -of json -show_format -show_streams "{{file}}"';
        $this->logger = $logger;
    }

    /**
     * Gets file duration in s.
     * Check "mediainfo -f file" output.
     * @param $file
     * @return integer $duration file duration in s rounded up.
     */
    public function getDuration($file)
    {
        if (!file_exists($file)) {
            throw new \BadMethodCallException("The file " . $file . " does not exist");
        }

        $json = json_decode($this->getMediaInfo($file));
        if (!$this->jsonHasMediaContent($json)) {
            throw new \InvalidArgumentException("This file has no accesible video " .
                "nor audio tracks\n" . $file);
        }

        $duration = ceil(intval((string)$json->format->duration));

        return $duration;
    }

    // Check the desired codec names (MPEG Audio/MPEG-1 Audio layer 3; AAC / Advanced Audio Codec / ...)
    // Now we choose FORMAT.
    /**
     * Completes track information from a given path using mediainfo.
     * @param Track $track
     */
    public function autocompleteTrack(Track $track)
    {
        $only_audio = true; //initialized true until video track is found.
        if (!$track->getPath()) {
            throw new \BadMethodCallException('Input track has no path defined');
        }

        $json = json_decode($this->getMediaInfo($track->getPath()));
        if (!$this->jsonHasMediaContent($json)) {
            throw new \InvalidArgumentException("This file has no accesible video " .
                "nor audio tracks\n" . $track->getPath());
        }


        $track->setMimetype(mime_content_type($track->getPath()));
        $track->setBitrate(intval($json->format->bit_rate));
        $aux = intval((string)$json->format->duration);
        $track->setDuration(ceil($aux));
        $track->setSize((string)$json->format->size);

        foreach ($json->streams as $stream) {
            switch ((string) $stream->codec_type) {
                case "video":
                    $track->setVcodec((string)$stream->codec_name);
                    $track->setFramerate((string)$stream->avg_frame_rate);
                    $track->setWidth(intval($stream->width));
                    $track->setHeight(intval($stream->height));
                    $only_audio = false;
                    break;

                case "audio":
                    $track->setAcodec((string)$stream->codec_name); 
                    $track->setChannels(intval($stream->channels));
                    break;
                
            }
            $track->setOnlyAudio($only_audio);                
        }
    }

    private function jsonHasMediaContent($json)
    {
        if ($json->streams != null) {
            foreach ($json->streams as $stream) {
                if ($stream->codec_type == "audio" || $stream->codec_type == "video") {
                    return true;
                }
            }
        }

        return false;
    }

    private function getMediaInfo($file)
    {
        $command = str_replace('{{file}}', $file, $this->command);
        $process = new Process($command);
        $process->setTimeout(60);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getExitCode().' '.$process->getExitCodeText().'. '.$process->getErrorOutput());
        }

        return $process->getOutput();
    }
}
