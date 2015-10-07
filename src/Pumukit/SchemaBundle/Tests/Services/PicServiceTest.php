<?php

namespace Pumukit\SchemaBundle\Tests\Services;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\SchemaBundle\Services\PicService;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Pic;
use Pumukit\SchemaBundle\Document\Track;

class PicServiceTest extends WebTestCase
{
    private $dm;
    private $factoryService;
    private $picService;
    private $context;
    private $defaultSeriesPic = '/images/series.jpg';
    private $defaultVideoPic = '/images/video.jpg';
    private $defaultAudioHDPic = '/images/audio_hd.jpg';
    private $defaultAudioSDPic = '/images/audio_sd.jpg';
    private $localhost = 'http://localhost';

    public function __construct()
    {
        $options = array('environment' => 'test');
        $kernel = static::createKernel($options);
        $kernel->boot();

        $this->dm = $kernel->getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->factoryService = $kernel->getContainer()->get('pumukitschema.factory');
        $this->context = $kernel->getContainer()->get('router.request_context');
    }

    public function setUp()
    {
        $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject')->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Series')->remove(array());
        $this->dm->flush();

        $this->picService = new PicService($this->context, $this->defaultSeriesPic, $this->defaultVideoPic, $this->defaultAudioHDPic, $this->defaultAudioSDPic);
    }

    public function testGetFirstUrlPic()
    {
        // SERIES SECTION
        $series = $this->factoryService->createSeries();

        $absolute = false;
        $this->assertEquals($this->defaultSeriesPic, $this->picService->getFirstUrlPic($series, $absolute));

        $absolute = true;
        $this->assertEquals($this->localhost.$this->defaultSeriesPic, $this->picService->getFirstUrlPic($series, $absolute));

        $seriesUrl1 = '/uploads/series1.jpg';
        $seriesPic1 = new Pic();
        $seriesPic1->setUrl($seriesUrl1);

        $series->addPic($seriesPic1);

        $this->dm->persist($series);
        $this->dm->flush();

        $this->assertEquals($seriesUrl1, $this->picService->getFirstUrlPic($series));

        $seriesUrl2 = '/uploads/series2.jpg';
        $seriesPic2 = new Pic();
        $seriesPic2->setUrl($seriesUrl2);

        $series->addPic($seriesPic2);

        $this->dm->persist($series);

        $series->upPicById($seriesPic2->getId());

        $this->dm->persist($series);
        $this->dm->flush();

        $this->assertEquals($seriesUrl2, $this->picService->getFirstUrlPic($series));

        // MULTIMEDIA OBJECT SECTION
        // Workaround for detached Series document
        $this->dm->clear(get_class($series));
        $series = $this->dm->find('PumukitSchemaBundle:Series', $series->getId());

        $mm = $this->factoryService->createMultimediaObject($series);
        $mm->setSeries($series);
        $this->dm->persist($mm);
        $this->dm->flush();

        $track = new Track();
        $track->setOnlyAudio(false);
        $mm->addTrack($track);

        $this->dm->persist($mm);
        $this->dm->flush();

        $absolute = false;
        $this->assertEquals($this->defaultVideoPic, $this->picService->getFirstUrlPic($mm, $absolute));

        $absolute = true;
        $this->assertEquals($this->localhost.$this->defaultVideoPic, $this->picService->getFirstUrlPic($mm, $absolute));

        $track->setOnlyAudio(true);
        $this->dm->persist($mm);
        $this->dm->flush();

        $absolute = false;
        $hd = true;
        $this->assertEquals($this->defaultAudioHDPic, $this->picService->getFirstUrlPic($mm, $absolute, $hd));
        $hd = false;
        $this->assertEquals($this->defaultAudioSDPic, $this->picService->getFirstUrlPic($mm, $absolute, $hd));

        $absolute = true;
        $hd = true;
        $this->assertEquals($this->localhost.$this->defaultAudioHDPic, $this->picService->getFirstUrlPic($mm, $absolute, $hd));
        $hd = false;
        $this->assertEquals($this->localhost.$this->defaultAudioSDPic, $this->picService->getFirstUrlPic($mm, $absolute, $hd));

        $mmUrl1 = '/uploads/video1.jpg';
        $mmPic1 = new Pic();
        $mmPic1->setUrl($mmUrl1);

        $mm->addPic($mmPic1);

        $this->dm->persist($mm);
        $this->dm->flush();

        $this->assertEquals($mmUrl1, $this->picService->getFirstUrlPic($mm));

        $absolute = true;
        $this->assertEquals($this->localhost.$mmUrl1, $this->picService->getFirstUrlPic($mm, $absolute));

        $mmUrl2 = '/uploads/video2.jpg';
        $mmPic2 = new Pic();
        $mmPic2->setUrl($mmUrl2);

        $mm->addPic($mmPic2);

        $this->dm->persist($mm);

        $mm->upPicById($mmPic2->getId());

        $this->dm->persist($mm);
        $this->dm->flush();

        $this->assertEquals($mmUrl2, $this->picService->getFirstUrlPic($mm));

        $absolute = true;
        $this->assertEquals($this->localhost.$mmUrl2, $this->picService->getFirstUrlPic($mm, $absolute));
    }

    public function testGetDefaultUrlPicForObject()
    {
        $pic = new Pic();

        $absolute = false;
        $this->assertEquals($this->defaultVideoPic, $this->picService->getDefaultUrlPicForObject($pic, $absolute));

        $absolute = true;
        $this->assertEquals($this->localhost.$this->defaultVideoPic, $this->picService->getDefaultUrlPicForObject($pic, $absolute));
    }
}