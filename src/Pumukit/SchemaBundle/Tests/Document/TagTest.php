<?php

namespace Pumukit\SchemaBundle\Tests\Document;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\SchemaBundle\Document\Tag;

class TagTest extends WebTestCase
{
    private $dm;
    private $tagRepo;

    public function setUp()
    {
        $options = array('environment' => 'test');
        $kernel = static::createKernel($options);
        $kernel->boot();
        $this->dm = $kernel->getContainer()
      ->get('doctrine_mongodb')->getManager();
        $this->tagRepo = $this->dm
      ->getRepository('PumukitSchemaBundle:Tag');

        $this->tagService = $kernel->getContainer()->get('pumukitschema.tag');

        $this->dm->getDocumentCollection('PumukitSchemaBundle:Tag')
      ->remove(array());
    }
    
    public function testGetterAndSetter()
    {
        $title = 'title';
        $description = 'description';
        $slug = 'slug';
        $cod = 23;
        $metatag = true;
        $created = new \DateTime("now");
        $updated = new \DateTime("now");
        $display = true;
        $youtubeProperty = "w7dD-JJJytM&list=PLmXxqSJJq-yUfrjvKe5c5LX_1x7nGVF6c";
        $properties = array('youtube' => $youtubeProperty);

        $tag = new Tag($title);

        $tag->setTitle($title);
        $tag->setDescription($description);
        $tag->setSlug($slug);
        $tag->setCod($cod);
        $tag->setMetatag($metatag);
        $tag->setCreated($created);
        $tag->setUpdated($updated);
        $tag->setDisplay($display);
        $tag->setProperties($properties);

        $tag_parent = new Tag("parent");
        $tag->setParent($tag_parent);

        $this->assertEquals($title, $tag->getTitle());
        $this->assertEquals($description, $tag->getDescription());
        $this->assertEquals($slug, $tag->getSlug());
        $this->assertEquals($cod, $tag->getCod());
        $this->assertEquals($metatag, $tag->getMetatag());
        $this->assertEquals($created, $tag->getCreated());
        $this->assertEquals($updated, $tag->getUpdated());
        $this->assertEquals($tag_parent, $tag->getParent());
        $this->assertEquals($display, $tag->getDisplay());
        $this->assertEquals($properties, $tag->getProperties());
        $this->assertEquals(null, $tag->getLockTime());

        $this->assertEquals('', $tag->getTitle('fr'));
        $this->assertEquals('', $tag->getDescription('fr'));

        $titleEs = 'título';
        $titleArray = array('en' => $title, 'es' => $titleEs);
        $descriptionEs = 'descripción';
        $descriptionArray = array('en' => $description, 'es' => $descriptionEs);

        $tag->setI18nTitle($titleArray);
        $tag->setI18nDescription($descriptionArray);

        $this->assertEquals($titleArray, $tag->getI18nTitle());
        $this->assertEquals($descriptionArray, $tag->getI18nDescription());

        $this->assertEquals($tag->getTitle(), $tag->__toString());

        $testProperty = 'test property';
        $tag->setProperty('test', $testProperty);
        $this->assertEquals($youtubeProperty, $tag->getProperty('youtube'));
        $this->assertEquals($testProperty, $tag->getProperty('test'));

        $testProperty = null;
        $tag->setProperty('test', $testProperty);
        $this->assertEquals($testProperty, $tag->getProperty('test'));
    }

    public function testNumberMultimediaObjects()
    {
        $tag = new Tag();
        $this->assertEquals(0, $tag->getNumberMultimediaObjects());

        $tag->increaseNumberMultimediaObjects();
        $this->assertEquals(1, $tag->getNumberMultimediaObjects());

        $tag->increaseNumberMultimediaObjects();
        $this->assertEquals(2, $tag->getNumberMultimediaObjects());

        $tag->decreaseNumberMultimediaObjects();
        $this->assertEquals(1, $tag->getNumberMultimediaObjects());

        $tag->decreaseNumberMultimediaObjects();
        $this->assertEquals(0, $tag->getNumberMultimediaObjects());

        $count = 5;
        $tag->setNumberMultimediaObjects($count);
        $this->assertEquals(5, $tag->getNumberMultimediaObjects());
    }

    public function testChildren()
    {

        $tag_parent = new Tag("tag_parent");
        $tag_child = new Tag("tag_child");
        $tag_parent->setCod('Parent');
        $tag_child->setCod('ParentChild');
        $tag_grandchild = new Tag("tag_grandchild");
        $tag_grandchild->setCod('GrandChild');
        $this->dm->persist( $tag_parent );
        $this->dm->persist( $tag_child );
        $this->dm->persist( $tag_grandchild );
        $this->dm->flush();
 
        $this->assertEquals(null, $tag_parent->getParent());
        $this->assertFalse( $tag_parent->isChildOf( $tag_child ) );
        $this->assertFalse( $tag_child->isChildOf( $tag_child ) );
        $this->assertFalse( $tag_parent->isDescendantOf( $tag_child ) );
        $this->assertFalse( $tag_child->isDescendantOf( $tag_child ) );
        $this->assertFalse( $tag_parent->isDescendantOfByCod( $tag_child->getCod() ) );
        $this->assertFalse( $tag_child->isDescendantOfByCod( $tag_child->getCod() ) );


        $tag_child->setParent( $tag_parent );
        $tag_grandchild->setParent( $tag_child );
        $this->dm->persist( $tag_child );
        $this->dm->persist( $tag_parent );
        $this->dm->flush();


        $this->assertEquals('Parent|ParentChild|GrandChild|', $tag_grandchild->getPath() );
        $this->assertEquals( $tag_parent, $tag_child->getParent() );
        $this->assertTrue( $tag_child->isChildOf( $tag_parent ) );
        $this->assertTrue( $tag_grandchild->isDescendantOf( $tag_parent ) );        
        $this->assertTrue( $tag_child->isDescendantOfByCod( $tag_parent->getCod() ) );
        $this->assertTrue( $tag_grandchild->isDescendantOfByCod( $tag_parent->getCod() ) );

        $this->assertFalse( $tag_grandchild->isChildOf( $tag_parent ) );
        $this->assertFalse( $tag_parent->isChildOf( $tag_child ) );
        $this->assertFalse( $tag_parent->isDescendantOf( $tag_child ) );        
        $this->assertFalse( $tag_parent->isDescendantOfByCod( $tag_child->getCod() ) );
    }
}
