<?php

namespace Pumukit\WorkflowBundle\Tests\EventListener;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\EncoderBundle\Services\PicExtractorService;
use Pumukit\SchemaBundle\Services\MultimediaObjectPicService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\SchemaBundle\Document\Pic;
use Pumukit\WorkflowBundle\EventListener\PicExtractorListener;

class PicExtractorListenerTest extends WebTestCase
{
    private $dm;
    private $repo;
    private $logger;
    private $picExtractorListener;
    private $videoPath;
    private $factoryService;
    private $mmsPicService;
    private $picExtractorService;
    private $autoExtractPic = true;

    public function __construct()
    {
        $options = array('environment' => 'test');
        $kernel = static::createKernel($options);
        $kernel->boot();
        $this->dm = $kernel->getContainer()->get('doctrine_mongodb')->getManager();
        $this->repo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->logger = $kernel->getContainer()->get('logger');
        $this->videoPath = realpath(__DIR__ . '/../Resources/data/track.mp4');
        $this->factoryService = $kernel->getContainer()->get('pumukitschema.factory');
        $this->mmsPicService = $kernel->getContainer()->get('pumukitschema.mmspic');
        $this->picExtractorService = $kernel->getContainer()->get('pumukitencoder.picextractor');
    }

    public function setUp()
    {
        $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Series')
            ->remove(array());
        $mmsPicService = $this->getMockBuilder('Pumukit\SchemaBundle\Services\MultimediaObjectPicService')
            ->disableOriginalConstructor()
            ->getMock();
        $mmsPicService->expects($this->any())
            ->method('addPicFile')
            ->will($this->returnValue('multimedia object'));
        $picExtractorService = $this->getMockBuilder('Pumukit\EncoderBundle\Services\PicExtractorService')
            ->disableOriginalConstructor()
            ->getMock();
        $picExtractorService->expects($this->any())
            ->method('extractPic')
            ->will($this->returnValue('success'));
        $this->picExtractorListener = new PicExtractorListener($this->dm, $mmsPicService, $picExtractorService, $this->logger, $this->autoExtractPic);
    }

    public function testGeneratePicFromVideo()
    {
        $series = $this->factoryService->createSeries();
        $mm = $this->factoryService->createMultimediaObject($series);

        $track = new Track();
        $track->addTag("master");
        $track->setPath($this->videoPath);
        $track->setOnlyAudio(false);
        $track->setWidth(640);
        $track->setHeight(480);

        $mm->addTrack($track);

        $this->dm->persist($mm);
        $this->dm->flush();

        $this->assertTrue($mm->getPics()->isEmpty());
        $this->assertEquals(0, count($mm->getPics()->toArray()));
        $this->assertTrue($this->invokeMethod($this->picExtractorListener, 'generatePic', array($mm, $track)));

        $pic = new Pic();
        $mm->addPic($pic);

        $this->dm->persist($mm);
        $this->dm->flush();

        $this->assertFalse($mm->getPics()->isEmpty());
        $this->assertEquals(1, count($mm->getPics()->toArray()));
        $this->assertFalse($this->invokeMethod($this->picExtractorListener, 'generatePic', array($mm, $track)));
    }

    public function testAddDefaultAudioPic()
    {
        // TODO: Remove this line when adding final default audio image
        $this->markTestSkipped('S');

        $series = $this->factoryService->createSeries();
        $mm = $this->factoryService->createMultimediaObject($series);

        $track = new Track();
        $track->addTag("master");
        $track->setPath($this->videoPath);
        $track->setOnlyAudio(true);
        $track->setWidth(640);
        $track->setHeight(480);

        $mm->addTrack($track);

        $this->dm->persist($mm);
        $this->dm->flush();

        $this->assertTrue($mm->getPics()->isEmpty());
        $this->assertEquals(0, count($mm->getPics()->toArray()));

        $this->assertTrue($this->invokeMethod($this->picExtractorListener, 'generatePic', array($mm, $track)));

        $pic = new Pic();
        $mm->addPic($pic);

        $this->dm->persist($mm);
        $this->dm->flush();

        $this->assertFalse($mm->getPics()->isEmpty());
        $this->assertEquals(1, count($mm->getPics()->toArray()));
        $this->assertFalse($this->invokeMethod($this->picExtractorListener, 'generatePic', array($mm, $track)));
    }

    public function testPicExtractorVideoError()
    {
        $mmsPicService = $this->getMockBuilder('Pumukit\SchemaBundle\Services\MultimediaObjectPicService')
            ->disableOriginalConstructor()
            ->getMock();
        $mmsPicService->expects($this->any())
            ->method('addPicFile')
            ->will($this->returnValue('multimedia object'));
        $picExtractorService = $this->getMockBuilder('Pumukit\EncoderBundle\Services\PicExtractorService')
            ->disableOriginalConstructor()
            ->getMock();
        $picExtractorService->expects($this->any())
            ->method('extractPic')
            ->will($this->returnValue('Error'));
        $picExtractorListener = new PicExtractorListener($this->dm, $mmsPicService, $picExtractorService, $this->logger, $this->autoExtractPic);

        $series = $this->factoryService->createSeries();
        $mm = $this->factoryService->createMultimediaObject($series);

        $track = new Track();
        $track->addTag("master");
        $track->setPath($this->videoPath);
        $track->setOnlyAudio(false);
        $track->setWidth(640);
        $track->setHeight(480);

        $mm->addTrack($track);

        $this->dm->persist($mm);
        $this->dm->flush();

        $this->assertTrue($mm->getPics()->isEmpty());
        $this->assertEquals(0, count($mm->getPics()->toArray()));
        $this->assertFalse($this->invokeMethod($picExtractorListener, 'generatePic', array($mm, $track)));
    }

    public function testPicExtractorAudioError()
    {
        // TODO: Remove this line when adding final default audio image
        $this->markTestSkipped('S');

        $mmsPicService = $this->getMockBuilder('Pumukit\SchemaBundle\Services\MultimediaObjectPicService')
            ->disableOriginalConstructor()
            ->getMock();
        $mmsPicService->expects($this->any())
            ->method('addPicFile')
            ->will($this->returnValue(null));
        $picExtractorService = $this->getMockBuilder('Pumukit\EncoderBundle\Services\PicExtractorService')
            ->disableOriginalConstructor()
            ->getMock();
        $picExtractorService->expects($this->any())
            ->method('extractPic')
            ->will($this->returnValue('success'));
        $picExtractorListener = new PicExtractorListener($this->dm, $mmsPicService, $picExtractorService, $this->logger, $this->autoExtractPic);

        $series = $this->factoryService->createSeries();
        $mm = $this->factoryService->createMultimediaObject($series);

        $track = new Track();
        $track->addTag("master");
        $track->setPath($this->videoPath);
        $track->setOnlyAudio(true);
        $track->setWidth(640);
        $track->setHeight(480);

        $mm->addTrack($track);

        $this->dm->persist($mm);
        $this->dm->flush();

        $this->assertTrue($mm->getPics()->isEmpty());
        $this->assertEquals(0, count($mm->getPics()->toArray()));
        $this->assertFalse($this->invokeMethod($picExtractorListener, 'generatePic', array($mm, $track)));
    }

    private function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}