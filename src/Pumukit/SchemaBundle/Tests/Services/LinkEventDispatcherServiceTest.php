<?php

namespace Pumukit\SchemaBundle\Tests\Services;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Link;
use Pumukit\SchemaBundle\Event\SchemaEvents;
use Pumukit\SchemaBundle\Event\LinkEvent;
use Pumukit\SchemaBundle\Services\LinkEventDispatcherService;

class LinkEventDispatcherServiceTest extends WebTestCase
{
    const EMPTY_TITLE = 'EMTPY TITLE';
    const EMPTY_URL = 'EMTPY URL';

    private $linkDispatcher;

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
        MockUpLinkListener::$called = false;
        MockUpLinkListener::$title = LinkEventDispatcherServiceTest::EMPTY_TITLE;
        MockUpLinkListener::$url = LinkEventDispatcherServiceTest::EMPTY_URL;

        $this->linkDispatcher = new LinkEventDispatcherService($this->dispatcher);
    }

    public function testDispatchCreate()
    {
        $this->dispatcher->addListener(SchemaEvents::LINK_CREATE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof LinkEvent);
                                           $this->assertEquals(SchemaEvents::LINK_CREATE, $name);

                                           $multimediaObject = $event->getMultimediaObject();
                                           $link = $event->getLink();

                                           MockUpLinkListener::$called = true;
                                           MockUpLinkListener::$title = $multimediaObject->getTitle();
                                           MockUpLinkListener::$url = $link->getUrl();        
                                       });

        $this->assertFalse(MockUpLinkListener::$called);
        $this->assertEquals(LinkEventDispatcherServiceTest::EMPTY_TITLE, MockUpLinkListener::$title);
        $this->assertEquals(LinkEventDispatcherServiceTest::EMPTY_URL, MockUpLinkListener::$url);

        $title = 'test title';
        $url = 'http://testlink.com';

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle($title);

        $link = new Link();
        $link->setUrl($url);

        $this->linkDispatcher->dispatchCreate($multimediaObject, $link);

        $this->assertTrue(MockUpLinkListener::$called);
        $this->assertEquals($title, MockUpLinkListener::$title);
        $this->assertEquals($url, MockUpLinkListener::$url);
    }

    public function testDispatchUpdate()
    {
        $this->dispatcher->addListener(SchemaEvents::LINK_UPDATE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof LinkEvent);
                                           $this->assertEquals(SchemaEvents::LINK_UPDATE, $name);

                                           $multimediaObject = $event->getMultimediaObject();
                                           $link = $event->getLink();

                                           MockUpLinkListener::$called = true;
                                           MockUpLinkListener::$title = $multimediaObject->getTitle();
                                           MockUpLinkListener::$url = $link->getUrl();        
                                       });

        $this->assertFalse(MockUpLinkListener::$called);
        $this->assertEquals(LinkEventDispatcherServiceTest::EMPTY_TITLE, MockUpLinkListener::$title);
        $this->assertEquals(LinkEventDispatcherServiceTest::EMPTY_URL, MockUpLinkListener::$url);

        $title = 'test title';
        $url = 'http://testlink.com';

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle($title);

        $link = new Link();
        $link->setUrl($url);

        $updateUrl = 'http://testlinkupdate.com';
        $link->setUrl($updateUrl);

        $this->linkDispatcher->dispatchUpdate($multimediaObject, $link);

        $this->assertTrue(MockUpLinkListener::$called);
        $this->assertEquals($title, MockUpLinkListener::$title);
        $this->assertEquals($updateUrl, MockUpLinkListener::$url);
    }

    public function testDispatchDelete()
    {
        $this->dispatcher->addListener(SchemaEvents::LINK_DELETE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof LinkEvent);
                                           $this->assertEquals(SchemaEvents::LINK_DELETE, $name);

                                           $multimediaObject = $event->getMultimediaObject();
                                           $link = $event->getLink();

                                           MockUpLinkListener::$called = true;
                                           MockUpLinkListener::$title = $multimediaObject->getTitle();
                                           MockUpLinkListener::$url = $link->getUrl();        
                                       });

        $this->assertFalse(MockUpLinkListener::$called);
        $this->assertEquals(LinkEventDispatcherServiceTest::EMPTY_TITLE, MockUpLinkListener::$title);
        $this->assertEquals(LinkEventDispatcherServiceTest::EMPTY_URL, MockUpLinkListener::$url);

        $title = 'test title';
        $url = 'http://testlink.com';

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle($title);

        $link = new Link();
        $link->setUrl($url);

        $this->linkDispatcher->dispatchDelete($multimediaObject, $link);

        $this->assertTrue(MockUpLinkListener::$called);
        $this->assertEquals($title, MockUpLinkListener::$title);
        $this->assertEquals($url, MockUpLinkListener::$url);
    }
}

class MockUpLinkListener
{
    static public $called = false;
    static public $title = LinkEventDispatcherServiceTest::EMPTY_TITLE;
    static public $url = LinkEventDispatcherServiceTest::EMPTY_URL;
}