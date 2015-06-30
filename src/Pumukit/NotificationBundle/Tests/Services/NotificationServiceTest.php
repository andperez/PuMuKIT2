<?php

namespace Pumukit\NotificationBundle\Tests\Services;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\EncoderBundle\Document\Job;
use Pumukit\EncoderBundle\Services\JobService;
use Pumukit\EncoderBundle\Services\ProfileService;
use Pumukit\EncoderBundle\Services\CpuService;
use Pumukit\EncoderBundle\Event\JobEvent;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Track;

class NotificationServiceTest extends WebTestCase
{
    private $dm;
    private $repo;

    public function __construct()
    {
        $options = array('environment' => 'test');
        $kernel = static::createKernel($options);
        $kernel->boot();

        $this->dm = $kernel->getContainer()
          ->get('doctrine_mongodb')->getManager();
        $this->repo = $this->dm
          ->getRepository('PumukitEncoderBundle:Job');
    }

    public function setUp()
    {
        $this->dm->getDocumentCollection('PumukitEncoderBundle:Job')->remove(array());
        $this->dm->flush();
    }

    public function testSendEmail()
    {
        $this->markTestSkipped('S');

        $multimediaObject= $this->createNewMultimediaObjectWithTrack();

        $job = $this->createNewJob(Job::STATUS_WAITING, $multimediaObject);

        $job->setStatus(Job::STATUS_FINISHED);
        $this->dm->persist($job);
        $this->dm->flush();

        $event = new JobEvent($job);
        $this->notificationService->onJobSuccess($event);

        $this->assertEquals(1, count($this->repo->findAll()));
    }


    private function createNewJob($status = null, $multimediaObject)
    {
        $job = new Job();
        if (null !== $status){
            $job->setStatus($status);
        }
        $job->setMmId($multimediaObject->getId());
        $job->setTimeini(new \DateTime('now'));
        $job->setTimestart(new \DateTime('now'));
        $job->setDuration(60);
        $job->setNewDuration(65);
        $job->setProfile('master_copy');
        $job->setCpu('cpu_local');
        $job->setOutput('OK');
        $job->setEmail('test@test.com');
        $job->setPathIni('pathini/to/track.mp4');
        $job->setPathEnd('pathend/to/track.mp4');
        $this->dm->persist($job);
        $this->dm->flush();

        return $job;
    }

    private function createNewMultimediaObjectWithTrack()
    {
        $track = new Track();
        $track->addTag('profile:master');
        $track->setPath('path/to/track.mp4');

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle('MultimediaObject test');
        $multimediaObject->addTrack($track);

        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }
}