<?php

namespace Pumukit\SchemaBundle\Tests\Services;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\SchemaBundle\Event\SchemaEvents;
use Pumukit\SchemaBundle\Event\TrackEvent;
use Pumukit\SchemaBundle\Services\TrackEventDispatcherService;

class TrackEventDispatcherServiceTest extends WebTestCase
{
    const EMPTY_TITLE = 'EMTPY TITLE';
    const EMPTY_URL = 'EMTPY URL';

    private $trackDispatcher;

    public function __construct()
    {
        $options = array('environment' => 'test');
        $kernel = static::createKernel($options);
        $kernel->boot();

        $this->dispatcher = $kernel->getContainer()
          ->get('event_dispatcher');
    }

    public function setUp()
    {
        MockUpTrackListener::$called = false;
        MockUpTrackListener::$title = TrackEventDispatcherServiceTest::EMPTY_TITLE;
        MockUpTrackListener::$url = TrackEventDispatcherServiceTest::EMPTY_URL;

        $this->trackDispatcher = new TrackEventDispatcherService($this->dispatcher);
    }

    public function testDispatchCreate()
    {
        $this->dispatcher->addListener(SchemaEvents::TRACK_CREATE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof TrackEvent);
                                           $this->assertEquals(SchemaEvents::TRACK_CREATE, $name);

                                           $multimediaObject = $event->getMultimediaObject();
                                           $track = $event->getTrack();

                                           MockUpTrackListener::$called = true;
                                           MockUpTrackListener::$title = $multimediaObject->getTitle();
                                           MockUpTrackListener::$url = $track->getUrl();        
                                       });

        $this->assertFalse(MockUpTrackListener::$called);
        $this->assertEquals(TrackEventDispatcherServiceTest::EMPTY_TITLE, MockUpTrackListener::$title);
        $this->assertEquals(TrackEventDispatcherServiceTest::EMPTY_URL, MockUpTrackListener::$url);

        $title = 'test title';
        $url = 'http://testtrack.com';

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle($title);

        $track = new Track();
        $track->setUrl($url);

        $this->trackDispatcher->dispatchCreate($multimediaObject, $track);

        $this->assertTrue(MockUpTrackListener::$called);
        $this->assertEquals($title, MockUpTrackListener::$title);
        $this->assertEquals($url, MockUpTrackListener::$url);
    }

    public function testDispatchUpdate()
    {
        $this->dispatcher->addListener(SchemaEvents::TRACK_UPDATE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof TrackEvent);
                                           $this->assertEquals(SchemaEvents::TRACK_UPDATE, $name);

                                           $multimediaObject = $event->getMultimediaObject();
                                           $track = $event->getTrack();

                                           MockUpTrackListener::$called = true;
                                           MockUpTrackListener::$title = $multimediaObject->getTitle();
                                           MockUpTrackListener::$url = $track->getUrl();        
                                       });

        $this->assertFalse(MockUpTrackListener::$called);
        $this->assertEquals(TrackEventDispatcherServiceTest::EMPTY_TITLE, MockUpTrackListener::$title);
        $this->assertEquals(TrackEventDispatcherServiceTest::EMPTY_URL, MockUpTrackListener::$url);

        $title = 'test title';
        $url = 'http://testtrack.com';

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle($title);

        $track = new Track();
        $track->setUrl($url);

        $updateUrl = 'http://testtrackupdate.com';
        $track->setUrl($updateUrl);

        $this->trackDispatcher->dispatchUpdate($multimediaObject, $track);

        $this->assertTrue(MockUpTrackListener::$called);
        $this->assertEquals($title, MockUpTrackListener::$title);
        $this->assertEquals($updateUrl, MockUpTrackListener::$url);
    }

    public function testDispatchDelete()
    {
        $this->dispatcher->addListener(SchemaEvents::TRACK_DELETE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof TrackEvent);
                                           $this->assertEquals(SchemaEvents::TRACK_DELETE, $name);

                                           $multimediaObject = $event->getMultimediaObject();
                                           $track = $event->getTrack();

                                           MockUpTrackListener::$called = true;
                                           MockUpTrackListener::$title = $multimediaObject->getTitle();
                                           MockUpTrackListener::$url = $track->getUrl();        
                                       });

        $this->assertFalse(MockUpTrackListener::$called);
        $this->assertEquals(TrackEventDispatcherServiceTest::EMPTY_TITLE, MockUpTrackListener::$title);
        $this->assertEquals(TrackEventDispatcherServiceTest::EMPTY_URL, MockUpTrackListener::$url);

        $title = 'test title';
        $url = 'http://testtrack.com';

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle($title);

        $track = new Track();
        $track->setUrl($url);

        $this->trackDispatcher->dispatchDelete($multimediaObject, $track);

        $this->assertTrue(MockUpTrackListener::$called);
        $this->assertEquals($title, MockUpTrackListener::$title);
        $this->assertEquals($url, MockUpTrackListener::$url);
    }
}

class MockUpTrackListener
{
    static public $called = false;
    static public $title = TrackEventDispatcherServiceTest::EMPTY_TITLE;
    static public $url = TrackEventDispatcherServiceTest::EMPTY_URL;
}