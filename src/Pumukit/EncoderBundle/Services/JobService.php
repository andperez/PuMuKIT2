<?php

namespace Pumukit\EncoderBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Pumukit\EncoderBundle\Document\Job;
use Pumukit\EncoderBundle\Event\JobEvent;
use Pumukit\EncoderBundle\Event\EncoderEvents;
use Pumukit\EncoderBundle\Executor\LocalExecutor;
use Pumukit\EncoderBundle\Executor\RemoteHTTPExecutor;
use Pumukit\EncoderBundle\Services\ProfileService;
use Pumukit\EncoderBundle\Services\CpuService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\InspectionBundle\Services\InspectionServiceInterface;

class JobService
{
    private $dm;
    private $repo;
    private $profileService;
    private $cpuService;
    private $inspectionService;
    private $tmp_path;
    private $dispatcher;
    private $logger;
    private $environment;
    private $tokenStorage;

    public function __construct(DocumentManager $documentManager, ProfileService $profileService, CpuService $cpuService, 
                                InspectionServiceInterface $inspectionService, EventDispatcherInterface $dispatcher, LoggerInterface $logger,
                                TokenStorage $tokenStorage, $environment="dev", $tmp_path=null)
    {
        $this->dm = $documentManager;
        $this->repo = $this->dm->getRepository('PumukitEncoderBundle:Job');
        $this->profileService = $profileService;
        $this->cpuService = $cpuService;
        $this->inspectionService = $inspectionService;
        $this->tmp_path = $tmp_path ? realpath($tmp_path) : sys_get_temp_dir();
        $this->logger = $logger;
        $this->tokenStorage = $tokenStorage;
        $this->dispatcher = $dispatcher;
        $this->environment = $environment;
    }


    /**
     * Add job checking if not exists.
     */
    public function addUniqueJob($pathFile, $profile, $priority, MultimediaObject $multimediaObject, $language = null, $description = array())
    {
        $job = $this->repo->findOneBy(array("profile" => $profile, "mm_id" => $multimediaObject->getId()));

        if ($job) {
            return $job;
        } else {
            return $this->addJob($pathFile, $profile, $priority, $multimediaObject, $language, $description);
        }
    }

    /**
     * Add job
     */
    public function addJob($pathFile, $profile, $priority, MultimediaObject $multimediaObject, $language = null, $description = array())
    {
        $this->checkService();

        if (!is_file($pathFile)) {
            $this->logger->addError('[addJob] FileNotFoundException: Could not find file "'.$pathFile);
            throw new FileNotFoundException($pathFile); 
        }

        if (null === $this->profileService->getProfile($profile)){
            $this->logger->addError('[addJob] Can not find given profile with name "'.$profile);
            throw new \Exception("Can't find given profile with name ".$profile);
        }
        
        if (null === $multimediaObject){
            $this->logger->addError('[addJob] Given null multimedia object');
            throw new \Exception("Given null multimedia object");
        }

        try{
            $duration = $this->inspectionService->getDuration($pathFile);
        }catch (\Exception $e){
            $this->logger->addError('[addJob] InspectionService getDuration error message: '. $e->getMessage());
            throw new \Exception($e->getMessage());
        }

        $job = new Job();
        $job->setMmId($multimediaObject->getId());
        $job->setProfile($profile);
        $job->setPathIni($pathFile);
        $job->setDuration($duration);
        $job->setPriority($priority);
        if (null !== $language){
            //TODO languageId is only language "es", "en", "gl"
            $job->setLanguageId($language);
        }
        if (!empty($description)){
            $job->setI18nDescription($description);
        }
        if ($email = $this->getUserEmail()) {
            $job->setEmail($email);
        }
        $job->setTimeini(new \DateTime('now'));
        $this->dm->persist($job);
        $this->dm->flush();

        $this->setPathEndAndExtensions($job);

        $this->logger->addInfo('[addJob] Added job with id: '.$job->getId());

        $this->executeNextJob();

        return $job;
    }

    /**
     * Pause job
     *
     * Given an id, pauses the job only if it's waiting
     */
    public function pauseJob($id)
    {
        $job = $this->repo->find($id);

        if (null === $job){
            $this->logger->addError('[pauseJob] Can not find job with id '.$id);
            throw new \Exception("Can't find job with id ".$id);
        }
        $this->changeStatus($job, Job::STATUS_WAITING, Job::STATUS_PAUSED);
    }

    /**
     * Resume Job
     *
     * Given an id, if the job status is waiting, pauses it
     */
    public function resumeJob($id)
    {
        $job = $this->repo->find($id);

        if (null === $job){
            $this->logger->addError('[resumeJob] Can not find job with id '.$id);
            throw new \Exception("Can't find job with id ".$id);
        }
        $this->changeStatus($job, Job::STATUS_PAUSED, Job::STATUS_WAITING);      
    }

    /**
     * Cancel job
     *
     * Given an id, if the job status is paused or waiting, delete it. Throw exception otherwise
     */
    public function cancelJob($id)
    {
        $job = $this->repo->find($id);

        if (null === $job){
            $this->logger->addError('[cancelJob] Can not find job with id '.$id);
            throw new \Exception("Can't find job with id ".$id);
        }
        if ((Job::STATUS_WAITING !== $job->getStatus()) && (Job::STATUS_PAUSED !== $job->getStatus())){
            $this->logger->addError('[cancelJob] Trying to cancel job "'.$id.'" that is not paused or waiting');
            throw new \Exception("Trying to cancel job ".$id." that is not paused or waiting");
        }
        $this->dm->remove($job);
        $this->dm->flush();
    }

    public function deleteJob($id)
    {
        $job = $this->repo->find($id);

        if (null === $job){
            $this->logger->addError('[deleteJob] Can not find job with id '.$id);
            throw new \Exception("Can't find job with id ".$id);
        }
        if (Job::STATUS_EXECUTING === $job->getStatus()){
            $msg = sprintf('[deleteJob] Trying to delete job "%s" that has executing status. Given status is %s', $id, $job->getStatus());
            $this->logger->addError($msg);
            throw new \Exception($msg);
        }
        $this->dm->remove($job);
        $this->dm->flush();
    }

    public function updateJobPriority($id, $priority)
    {
        $job = $this->repo->find($id);

        if (null === $job){
            $this->logger->addError('[updateJobPriority] Can not find job with id '.$id);
            throw new \Exception("Can't find job with id ".$id);
        }
        
        $job->setPriority($priority);
        $this->dm->persist($job);
        $this->dm->flush();
    }
    
    /**
     * Get all jobs status
     */
    public function getAllJobsStatus()
    {
        return array(
                     'paused' => count($this->repo->findWithStatus(array(Job::STATUS_PAUSED))),
                     'waiting' => count($this->repo->findWithStatus(array(Job::STATUS_WAITING))),
                     'executing' => count($this->repo->findWithStatus(array(Job::STATUS_EXECUTING))),
                     'finished' => count($this->repo->findWithStatus(array(Job::STATUS_FINISHED))),
                     'error' => count($this->repo->findWithStatus(array(Job::STATUS_ERROR)))
                     );
    }
    
    /**
     * Get next job
     *
     * Returns the job in waiting status with higher priority (tie: timeini older)
     */
    public function getNextJob()
    {
        return $this->repo->findHigherPriorityWithStatus(array(Job::STATUS_WAITING));
    }

    /**
     * Exec next job
     */
    public function executeNextJob()
    {
        if("test" == $this->environment){
            return null;
        }

        $freeCpu = $this->cpuService->getFreeCpu();
        $nextJob = $this->getNextJob();
        if (($freeCpu) && ($nextJob) && ($this->cpuService->isActive($freeCpu))){
            $nextJob->setCpu($freeCpu);
            $nextJob->setTimestart(new \DateTime('now'));
            $nextJob->setStatus(Job::STATUS_EXECUTING);
            $this->dm->persist($nextJob);
            $this->dm->flush();
            $this->executeInBackground($nextJob);

            return $nextJob;
        }
        
        return null;
    }


    public function executeInBackground(Job $job)
    {

        $pb = new ProcessBuilder();
        // PHP wraps the process in "sh -c" by default, but we need to control
        // the process directly.
        /*
        if ( ! defined('PHP_WINDOWS_VERSION_MAJOR')) {
          $pb->add('exec');
        }
        */

        //TODO
        //$console = $this->getContainer()->getParameter('kernel.root_dir').'/console';
        $console = __DIR__ . '/../../../../app/console';

        $pb
          ->add('php')
          ->add($console)
          ->add(sprintf('--env=%s', $this->environment))
          ;

        if (false) {
          $pb->add('--verbose');
        }

        $pb
          ->add('pumukit:encoder:job')
          ->add($job->getId())
          ;

        $process = $pb->getProcess();

        $command = $process->getCommandLine();
        $this->logger->addInfo('[executeInBackground] CommandLine '.$command);
        shell_exec("nohup $command 1> /dev/null 2> /dev/null & echo $!");

        //$process->disableOutput();
        //$process->start();
        //$process->run();
        //dump($process->getOutput());
        //dump($process->getErrorOutput());
        //dump($process->getCommandLine());
    }

    public function execute(Job $job)
    {
        set_time_limit(0);

        $profile = $this->getProfile($job);
        $cpu = $this->cpuService->getCpuByName($job->getCpu());
        $commandLine = $this->renderBat($job);

        $this->mkdir(dirname($job->getPathEnd()));
        
        $executor = $this->getExecutor($profile['app'], $cpu);
        
        try{
            $out = $executor->execute($commandLine, $cpu);
            $job->setOutput($out);
            $duration = $this->inspectionService->getDuration($job->getPathEnd());
            $job->setNewDuration($duration);

            $this->logger->addInfo('[execute] cpu: '.serialize($cpu));
            $this->logger->addInfo('[execute] CommandLine: '.$commandLine);
            $this->logger->addInfo('[execute] profile.app: "'.$profile['app'].'"');
            $this->logger->addInfo('[execute] out: "'.$out.'"');
            $this->logger->addInfo('[execute] job duration: '.$job->getDuration());
            $this->logger->addInfo('[execute] duration: '.$duration);

            $this->searchError($profile['app'], $out, $job->getDuration(), $duration);

            $job->setTimeend(new \DateTime('now'));
            $job->setStatus(Job::STATUS_FINISHED);

            $track = $this->createTrackWithJob($job);
            $this->dispatch(true, $job, $track);
        }catch (\Exception $e){
            $job->setTimeend(new \DateTime('now'));
            $job->setStatus(Job::STATUS_ERROR);

            $job->setOutput($e->getMessage());
            $this->logger->addError('[execute] error job output: '.$e->getMessage());
            $this->dispatch(false, $job);
        }

        $this->dm->persist($job);
        $this->dm->flush();

        $this->executeNextJob();
    }


    /**
     * Throw a exception if error executing the job.
     */
    public function searchError($profile, $var, $duration_in, $duration_end)
    {
        $duration_conf = 25;
        if (($duration_in < $duration_end - $duration_conf ) || ($duration_in > $duration_end + $duration_conf )){
            throw new \Exception(sprintf("Final duration (%s) and initial duration (%s) are differents", $duration_in, $duration_end));
        }
        return true;
    }

    /**
     * Get bat auto
     *
     * Generates execution line replacing %1 %2 %3 by
     * in, out and cfg files
     *
     * @return string commandLine
     */
    public function renderBat(Job $job)
    {
        $profile = $this->getProfile($job);
        $mmobj = $this->getMultimediaObject($job);

        $vars = array();

        foreach ($mmobj->getTracks() as $track) {
            foreach($track->getTags() as $tag) {
                $vars['{{' . $tag . '}}'] = $track->getPath();
            }
        }
        
        $vars['{{input}}'] = $job->getPathIni();
        $vars['{{output}}'] = $job->getPathEnd();

        foreach(range(1, 9) as $identifier){
            $vars['{{temfile' . $identifier. '}}'] = $this->tmp_path . '/' . rand();
        }

        $commandLine = str_replace(array_keys($vars), array_values($vars), $profile['bat']);
        $this->logger->addInfo('[renderBat] CommandLine: '.$commandLine);
    
        $cpu = $this->cpuService->getCpuByName($job->getCpu());
        if(CpuService::TYPE_WINDOWS === $cpu['type']){
            // TODO - PATH UNIX TRANSCODER and PATH WIN TRANSCODER
        }
        
        return $commandLine;
    }


    /**
     * Change status of a given job
     */
    private function changeStatus(Job $job, $actualStatus, $newStatus)
    {
        if ($actualStatus === $job->getStatus()){
            $job->setStatus($newStatus);
            $this->dm->persist($job);
            $this->dm->flush();
        }
    }
    

    /**
     * Set path end auto
     */
    public function setPathEndAndExtensions($job)
    {
        if (!file_exists($job->getPathIni())) {
            $this->logger->addError('[setPathEndAndExtensions] Error input file does not exist when setting the path_end');
            throw new \Exception('Error input file does not exist when setting the path_end');
        }

        if (!$job->getMmId()) {
            $this->logger->addError('[setPathEndAndExtensions] Error getting multimedia object to set path_end.');
            throw new \Exception('Error getting multimedia object to set path_end.');
        }
   
        if (!$job->getProfile()) {
            $this->logger->addError('[setPathEndAndExtensions] Error with profile name to set path_end.');
            throw new \Exception('Error with profile name to set path_end.');
        }

        $profile = $this->getProfile($job);
        $mmobj = $this->getMultimediaObject($job);

        $extension = pathinfo($job->getPathIni(), PATHINFO_EXTENSION);        
        $pathEnd = $this->getPathEnd($profile, $mmobj->getSeries()->getId(), $job->getId(), $extension);

        $job->setPathEnd($pathEnd);
        $job->setExtIni($extension);
        $job->setExtEnd(pathinfo($pathEnd, PATHINFO_EXTENSION));

        $this->dm->persist($job);
        $this->dm->flush();
    }


    private function getPathEnd(array $profile, $dir, $file, $extension)
    {
        $finalExtension = isset($profile['extension'])?$profile['extension']:$extension;

        $tempDir = $profile['streamserver']['dir_out'] . '/' . $dir;

        $this->mkdir($tempDir);

        return realpath($tempDir) . '/' . $file . '.' . $finalExtension;      
    }


    public function createTrackWithJob($job)
    {                             
        $multimediaObject = $this->getMultimediaObject($job);
      
        return $this->createTrack($multimediaObject, $job->getPathEnd(), $job->getProfile(), $job->getLanguageId(), $job->getI18nDescription());
    }

    public function createTrackWithFile($pathFile, $profileName, MultimediaObject $multimediaObject, $language = null, $description = array())
    {
        $profile = $this->profileService->getProfile($profileName);

        $pathEnd = $this->getPathEnd($profile, 
                                     $multimediaObject->getSeries()->getId(), 
                                     pathinfo($pathFile, PATHINFO_FILENAME),
                                     pathinfo($pathFile, PATHINFO_EXTENSION));

        if (!copy($pathFile, $pathEnd)) {
          throw new \Exception("Error to copy file");
        }
        
        return $this->createTrack($multimediaObject, $pathEnd, $profileName, $language, $description);
    }

    public function createTrack(MultimediaObject $multimediaObject, $pathEnd, $profileName, $language = null, $description = array())
    {
        $profile = $this->profileService->getProfile($profileName);

        $track = new Track();
        $track->addTag('profile:' . $profileName);
        if ($profile['master']) $track->addTag('master');
        if ($profile['display']) $track->addTag('display');
        foreach(array_filter(preg_split('/[,\s]+/', $profile['tags'])) as $tag) {
            $track->addTag(trim($tag));
        }

        if (!empty($description)){
            $track->setI18nDescription($description);
        }
        $track->setLanguage($language);

        $track->setPath($pathEnd);
        if(isset($profile['streamserver']['url_out'])) {
          $track->setUrl(str_replace(realpath($profile['streamserver']['dir_out']), $profile['streamserver']['url_out'], $pathEnd));
        }

        $this->inspectionService->autocompleteTrack($track);

        $track->setOnlyAudio($track->getWidth() == 0);
        $track->setHide(false);

        $multimediaObject->addTrack($track);
     
        $this->dm->persist($multimediaObject);
        $this->dm->flush();
        
        return $track;
    }

    /**
     * Get jobs with multimedia object id
     *
     * @param string $mmId
     * @return ArrayCollection $jobs with mmId
     */
    public function getJobsByMultimediaObjectId($mmId)
    {
        return $this->repo->findByMultimediaObjectId($mmId);
    }

    /**
     * Get status error
     *
     * @return integer Job status error
     */
    public function getStatusError()
    {
        return Job::STATUS_ERROR;
    }

    /**
     * Retry job
     */
    public function retryJob($job)
    {
        if (Job::STATUS_ERROR !== $job->getStatus()){
            return false;
        }
        
        $mmobj = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->find($job->getMmId());

        $profile = $this->getProfile($job);
        $tempDir = $profile['streamserver']['dir_out'] . '/' . $mmobj->getSeries()->getId();
        
        $this->mkdir($tempDir);

        $job->setStatus(Job::STATUS_WAITING);
        $job->setPriority(2);
        $job->setTimeIni(new \DateTime('now'));
        $this->dm->persist($job);
        $this->dm->flush();

        $this->executeNextJob();

        return true;
    }

    private function getExecutor($app, $cpu)
    {
        $localhost = array('localhost', '127.0.0.1');
        $executor = (in_array($cpu['host'], $localhost)) ? new LocalExecutor() : new RemoteHTTPExecutor();
        return $executor;
    }

    private function getProfile($job)
    {
        return $this->profileService->getProfile($job->getProfile());
    }

    private function getMultimediaObject($job)
    {
        $multimediaObject = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->find($job->getMmId());
        
        if(!$multimediaObject) {
          $errorMsg = sprintf("[createTrackWithJob] Multimedia object %s not found when the job $s creates the track", $job->getId(), $job->getMmId());
          $this->logger->addError($errorMsg);
          throw new \Exception($errorMsg);
        }
        
        return $multimediaObject;
    }


    /**
     * Emit an event to notifiy finised job.
     */
    private function dispatch($success, Job $job, Track $track=null)
    {
        $multimediaObject = $this->getMultimediaObject($job);

        $event = new JobEvent($job, $track, $multimediaObject);
        $this->dispatcher->dispatch($success ? EncoderEvents::JOB_SUCCESS : EncoderEvents::JOB_ERROR, $event);
    }

    /**
     * Check for blocked jobs.
     */
    private function checkService()
    {
        $jobs = $this->repo->findWithStatus(array(Job::STATUS_EXECUTING));
        $yesterday = new \DateTime('-1 day'); 

        foreach($jobs as $job) {
          if($job->getTimestart() < $yesterday) {  
            $this->logger->addError(sprintf('[checkService] Job executing for a long time %s', $job-getId()));
          }
        }
    }

    /**
     * Get user email
     *
     * Gets the email of the user who executed the job
     */
    private function getUserEmail()
    {
        $email = null;
        if (null !== $token = $this->tokenStorage->getToken()) {
            if (is_object($user = $token->getUser())) {
                $email = $user->getEmail();
            }
        }

        return $email;
    }


    /**
     * 
     */
    private function mkdir($path)
    {
      $fs = new Filesystem();
      $fs->mkdir($path);
    }
}