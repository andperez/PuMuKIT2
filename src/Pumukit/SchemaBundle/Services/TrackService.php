<?php

namespace Pumukit\SchemaBundle\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\EncoderBundle\Services\JobService;
use Pumukit\EncoderBundle\Services\ProfileService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\Finder\Finder;

class TrackService
{
    private $dm;
    private $tmpPath;
    private $jobService;
    private $profileService;
    private $forceDeleteOnDisk;

    public function __construct(DocumentManager $documentManager, JobService $jobService, ProfileService $profileService, $tmpPath=null, $forceDeleteOnDisk=true)
    {
        $this->dm = $documentManager;
        $this->jobService = $jobService;
        $this->profileService = $profileService;
        $this->tmpPath = $tmpPath ? realpath($tmpPath) : sys_get_temp_dir();
        $this->forceDeleteOnDisk = $forceDeleteOnDisk;
    }

    /**
     * Create track from local hard drive with job service
     * 
     * @param MultimediaObject $multimediaObject
     * @param UploadedFile $file
     * @param string $profile
     * @param int $priority
     * @param string $language
     * @param array $description
     * @return MultimediaObject
     */
    public function createTrackFromLocalHardDrive(MultimediaObject $multimediaObject, UploadedFile $trackFile, $profile, $priority, $language, $description)
    {

        if (null === $this->profileService->getProfile($profile)){
            throw new \Exception("Can't find given profile with name ".$profile);
        }

        if(UPLOAD_ERR_OK != $trackFile->getError()) {
           throw new \Exception($trackFile->getErrorMessage());
        }

        if (!is_file($trackFile->getPathname())) {
            throw new FileNotFoundException($trackFile->getPathname());
        }

        $pathFile = $trackFile->move($this->tmpPath."/".$multimediaObject->getId(), $trackFile->getClientOriginalName());

        $this->jobService->addJob($pathFile, $profile, $priority, $multimediaObject, $language, $description);

        return $multimediaObject;
    }

    /**
     * Create track from inbox on server with job service
     * 
     * @param MultimediaObject $multimediaObject
     * @param string $trackUrl
     * @param string $profile
     * @param int $priority
     * @param string $language
     * @param array $description
     * @return MultimediaObject
     */
    public function createTrackFromInboxOnServer(MultimediaObject $multimediaObject, $trackUrl, $profile, $priority, $language, $description)
    {
        if (null === $this->profileService->getProfile($profile)){
            throw new \Exception("Can't find given profile with name ".$profile);
        }

        if (!is_file($trackUrl)) {
            throw new FileNotFoundException($trackUrl);
        }

        $this->jobService->addJob($trackUrl, $profile, $priority, $multimediaObject, $language, $description);

        return $multimediaObject;
    }

    /**
     * Update Track in Multimedia Object
     */
    public function updateTrackInMultimediaObject(MultimediaObject $multimediaObject)
    {
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Remove Track from Multimedia Object
     */
    public function removeTrackFromMultimediaObject(MultimediaObject $multimediaObject, $trackId)
    {
        $track = $multimediaObject->getTrackById($trackId);
        $trackPath = $track->getPath();

        $multimediaObject->removeTrackById($trackId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        if ($this->forceDeleteOnDisk && $trackPath) {
            $this->deleteFileOnDisk($trackPath);
        }

        return $multimediaObject;
    }

    /**
     * Up Track in Multimedia Object
     */
    public function upTrackInMultimediaObject(MultimediaObject $multimediaObject, $trackId)
    {
        $multimediaObject->upTrackById($trackId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Down Track in Multimedia Object
     */
    public function downTrackInMultimediaObject(MultimediaObject $multimediaObject, $trackId)
    {
        $multimediaObject->downTrackById($trackId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Get temp directories
     */
    public function getTempDirs()
    {
        return array($this->tmpPath);
    }

    private function deleteFileOnDisk($path)
    {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);
        try {
            $deleted = unlink($path);
            if (!$deleted) {
                throw new \Exception("Error deleting file '".$path."' on disk");
            }
            $finder = new Finder();
            $finder->files()->in($dirname);
            if (0 === $finder->count()) {
                $dirDeleted = rmdir($dirname);
                if (!$deleted) {
                    throw new \Exception("Error deleting directory '".$dirname."'on disk");
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
