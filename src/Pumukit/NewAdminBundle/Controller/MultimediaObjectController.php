<?php

namespace Pumukit\NewAdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;
use Pagerfanta\Pagerfanta;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\NewAdminBundle\Form\Type\MultimediaObjectMetaType;
use Pumukit\NewAdminBundle\Form\Type\MultimediaObjectPubType;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Pumukit\SchemaBundle\Event\SchemaEvents;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class MultimediaObjectController extends SortableAdminController
{
    /**
     * Overwrite to search criteria with date
     * @Template
     */
    public function indexAction(Request $request)
    {
        $config = $this->getConfiguration();
        $criteria = $this->getCriteria($config);
        $resources = $this->getResources($request, $config, $criteria);

        $factoryService = $this->get('pumukitschema.factory');

        $sessionId = $this->get('session')->get('admin/series/id', null);
        $series = $factoryService->findSeriesById($request->query->get('id'), $sessionId);
        if(!$series) throw $this->createNotFoundException();

        $this->get('session')->set('admin/series/id', $series->getId());

        if($request->query->has('mmid')) {
            $this->get('session')->set('admin/mms/id', $request->query->get('mmid'));
        }

        $mms = $this->getListMultimediaObjects($series);

        $update_session = true;
        foreach($mms as $mm) {
            if($mm->getId() == $this->get('session')->get('admin/mms/id')){
                $update_session = false;
            }
        }
 
        if($update_session){
            $this->get('session')->remove('admin/mms/id');
        }

        return array(
                     'series' => $series,
                     'mms' => $mms
                     );
    }

    /**
     * Create new resource
     */
    public function createAction(Request $request)
    {
       $config = $this->getConfiguration();
       $pluralName = $config->getPluralResourceName();

       $factoryService = $this->get('pumukitschema.factory');

       $sessionId = $this->get('session')->get('admin/series/id', null);
       $series = $factoryService->findSeriesById($request->get('id'), $sessionId);
       $this->get('session')->set('admin/series/id', $series->getId());

       $mmobj = $factoryService->createMultimediaObject($series);

       $this->get('session')->set('admin/mms/id', $mmobj->getId());

       return new JsonResponse(array(
				     'seriesId' => $series->getId(),
				     'mmId' => $mmobj->getId()
				     ));
    }

    /**
     * Overwrite to update the session.
     * @Template
     */
    public function showAction(Request $request)
    {
      $config = $this->getConfiguration();
      $data = $this->findOr404($request);

      $this->get('session')->set('admin/mms/id', $data->getId());

      $roles = $this->get('pumukitschema.factory')->getRoles();

      return array(
                   'mm' => $data,
                   'roles' => $roles
                   );
    }

    /**
     * Display the form for editing or update the resource.
     * @Template
     */
    public function editAction(Request $request)
    {
        $config = $this->getConfiguration();

        $factoryService = $this->get('pumukitschema.factory');
        
        $roles = $factoryService->getRoles();
        if (null === $roles){
            throw new \Exception('Not found any role.');
        }

        $sessionId = $this->get('session')->get('admin/series/id', null);
        $series = $factoryService->findSeriesById($request->get('seriesId'), $sessionId);

        if (null === $series){
            throw new \Exception('Series with id '.$request->get('seriesId').' or with session id '.$sessionId.' not found.');
        }
        $this->get('session')->set('admin/series/id', $series->getId());

        $parentTags = $factoryService->getParentTags();

        $resource = $this->findOr404($request);
        $translator = $this->get('translator');
        $locale = $request->getLocale();
        $formMeta = $this->createForm(new MultimediaObjectMetaType($translator, $locale), $resource);
        $formPub = $this->createForm(new MultimediaObjectPubType($translator, $locale), $resource);

        $pubChannelsTags = $factoryService->getTagsByCod('PUBCHANNELS', true);
        $pubDecisionsTags = $factoryService->getTagsByCod('PUBDECISIONS', true);

        $jobs = $this->get('pumukitencoder.job')->getJobsByMultimediaObjectId($resource->getId());

        $notMasterProfiles = $this->get('pumukitencoder.profile')->getProfiles(null, true, false);

        $template = $resource->isPrototype() ? '_template' : '';

        return array(
                     'mm'            => $resource,
                     'form_meta'     => $formMeta->createView(),
                     'form_pub'      => $formPub->createView(),
                     'series'        => $series,
                     'roles'         => $roles,
                     'pub_channels'  => $pubChannelsTags,
                     'pub_decisions' => $pubDecisionsTags,
                     'parent_tags'   => $parentTags,
                     'jobs'          => $jobs,
                     'not_master_profiles' => $notMasterProfiles,
                     'template' => $template
                     );
    }

    /**
     * Display the form for editing or update the resource.
     */
    public function updatemetaAction(Request $request)
    {
        $config = $this->getConfiguration();

        $factoryService = $this->get('pumukitschema.factory');

        $roles = $factoryService->getRoles();
        if (null === $roles){
            throw new \Exception('Not found any role.');
        }

        $sessionId = $this->get('session')->get('admin/series/id', null);
        $series = $factoryService->findSeriesById(null, $sessionId);
        if (null === $series){
            throw new \Exception('Series with id '.$request->get('id').' or with session id '.$sessionId.' not found.');
        }
        $this->get('session')->set('admin/series/id', $series->getId());

        $parentTags = $factoryService->getParentTags();

        $resource = $this->findOr404($request);
        $translator = $this->get('translator');
        $locale = $request->getLocale();
        $formMeta = $this->createForm(new MultimediaObjectMetaType($translator, $locale), $resource);
        $formPub = $this->createForm(new MultimediaObjectPubType($translator, $locale), $resource);

        $pubChannelsTags = $factoryService->getTagsByCod('PUBCHANNELS', true);
        $pubDecisionsTags = $factoryService->getTagsByCod('PUBDECISIONS', true);

        $method = $request->getMethod();
        if (in_array($method, array('POST', 'PUT', 'PATCH')) &&
            $formMeta->submit($request, !$request->isMethod('PATCH'))->isValid()) {
          $this->domainManager->update($resource);

          if ($config->isApiRequest()) {
            return $this->handleView($this->view($formMeta));
          }
          
          /*
            $criteria = $this->getCriteria($config);
            $resources = $this->getResources($request, $config, $criteria);
          */
          
          $mms = $this->getListMultimediaObjects($series);

          return $this->render('PumukitNewAdminBundle:MultimediaObject:list.html.twig',
                               array(
                                     'series' => $series,
                                     'mms' => $mms
                                     )
                               );         
        }

        if ($config->isApiRequest()) {
            return $this->handleView($this->view($formMeta));
        }

        return $this->render('PumukitNewAdminBundle:MultimediaObject:edit.html.twig',
                             array(
                                   'mm'            => $resource,
                                   'form_meta'     => $formMeta->createView(),
                                   'form_pub'      => $formPub->createView(),
                                   'series'        => $series,
                                   'roles'         => $roles,
                                   'pub_channels'  => $pubChannelsTags,
                                   'pub_decisions' => $pubDecisionsTags,
                                   'parent_tags'   => $parentTags
                                   )
                             );
    }
    
    /**
     * Display the form for editing or update the resource.
     */
    public function updatepubAction(Request $request)
    {
        $config = $this->getConfiguration();

        $factoryService = $this->get('pumukitschema.factory');

        $sessionId = $this->get('session')->get('admin/series/id', null);
        $series = $factoryService->findSeriesById(null, $sessionId);
        if (null === $series){
            throw new \Exception('Series with id '.$request->get('id').' or with session id '.$sessionId.' not found.');
        }
        $this->get('session')->set('admin/series/id', $series->getId());

        $parentTags = $factoryService->getParentTags();

        $resource = $this->findOr404($request);
        $translator = $this->get('translator');
        $locale = $request->getLocale();
        $formMeta = $this->createForm(new MultimediaObjectMetaType($translator, $locale), $resource);
        $formPub = $this->createForm(new MultimediaObjectPubType($translator, $locale), $resource);

        $pubChannelsTags = $factoryService->getTagsByCod('PUBCHANNELS', true);
        $pubDecisionsTags = $factoryService->getTagsByCod('PUBDECISIONS', true);

        $method = $request->getMethod();
        if (in_array($method, array('POST', 'PUT', 'PATCH')) &&
            $formPub->submit($request, !$request->isMethod('PATCH'))->isValid()) {
            $resource = $this->updateTags($request->get('pub_channels', null), "PUCH", $resource);
            $resource = $this->updateTags($request->get('pub_decisions', null), "PUDE", $resource);

            $this->domainManager->update($resource);

            $this->dispatchUpdate($resource);

            if ($config->isApiRequest()) {
                return $this->handleView($this->view($formPub));
            }

            $mms = $this->getListMultimediaObjects($series);

            return $this->render('PumukitNewAdminBundle:MultimediaObject:list.html.twig',
                                 array(
                                       'series' => $series,
                                       'mms' => $mms
                                       )
                                 );
        }

        if ($config->isApiRequest()) {
            return $this->handleView($this->view($formPub));
        }

        $roles = $factoryService->getRoles();
        if (null === $roles){
            throw new \Exception('Not found any role.');
        }

        return $this->render('PumukitNewAdminBundle:MultimediaObject:edit.html.twig',
                             array(
                                   'mm'            => $resource,
                                   'form_meta'     => $formMeta->createView(),
                                   'form_pub'      => $formPub->createView(),
                                   'series'        => $series,
                                   'roles'         => $roles,
                                   'pub_channels'  => $pubChannelsTags,
                                   'pub_decisions' => $pubDecisionsTags,
                                   'parent_tags'   => $parentTags
                                   )
                             );
    }

    /**
     * 
     */
    public function getChildrenTagAction(Tag $tag, Request $request)
    {
        $config = $this->getConfiguration();

        $view = $this
          ->view()
          ->setTemplate($config->getTemplate('listtagsajax.html'))
          ->setData(array(
                          'nodes' => $tag->getChildren(),
                          'parent' => $tag->getId(),
                          'mmId' => $request->get('mm_id'),
                          'block_tag' => $request->get('tag_id'),
                          ))
          ;

        return $this->handleView($view);
    }

    /**
     * Add Tag
     */
    public function addTagAction(Request $request)
    {
        $config = $this->getConfiguration();

        $resource = $this->findOr404($request);

        $tagService = $this->get('pumukitschema.tag');

        try{
            $addedTags = $tagService->addTagToMultimediaObject($resource, $request->get('tagId'));
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }

        $json = array('added' => array(), 'recommended' => array());
        foreach($addedTags as $n){
            $json['added'][] = array(
                                     'id' => $n->getId(),
                                     'cod' => $n->getCod(),
                                     'name' => $n->getTitle(),
                                     'group' => $n->getPath()
                                     );
        }

        return new JsonResponse($json);
    }

    /**
     * Delete Tag
     */
    public function deleteTagAction(Request $request)
    {
        $config = $this->getConfiguration();

        $resource = $this->findOr404($request);

        $tagService = $this->get('pumukitschema.tag');

        try{
            $deletedTags = $tagService->removeTagFromMultimediaObject($resource, $request->get('tagId'));
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }

        $json = array('deleted' => array(), 'recommended' => array());
        foreach($deletedTags as $n){
            $json['deleted'][] = array(
                                       'id' => $n->getId(),
                                       'cod' => $n->getCod(),
                                       'name' => $n->getTitle(),
                                       'group' => $n->getPath()
                                       );
        }

        return new JsonResponse($json);
    }

    /**
     * Update Tags in Multimedia Object from form
     */
    private function updateTags($checkedTags, $codStart, $resource)
    {
        if (null !== $checkedTags) {
          foreach ($resource->getTags() as $tag) {
              if ((0 == strpos($tag->getCod(), $codStart)) && (false !== strpos($tag->getCod(), $codStart)) && (!in_array($tag->getCod(), $checkedTags))) {
                  $resource->removeTag($tag);
              }
          }
          foreach ($checkedTags as $cod => $checked) {
            $tag = $this->get('pumukitschema.factory')->getTagsByCod($cod, false);
              $resource->addTag($tag);
          }
        } else {
            foreach ($resource->getTags() as $tag) {
                if ((0 == strpos($tag->getCod(), $codStart)) && (false !== strpos($tag->getCod(), $codStart))) {
                    $resource->removeTag($tag);
                }
            }
        }

        return $resource;
    }

    /**
     * Get the view list of multimedia objects
     * belonging to a series
     */
    private function getListMultimediaObjects(Series $series, $newMultimediaObjectId=null)
    {
        $session = $this->get('session');
        $page = $session->get('admin/mms/page', 1);
        $maxPerPage = $session->get('admin/mms/paginate', 10);

        $sorting = array("rank" => "asc");
        $mmsQueryBuilder = $this
          ->get('doctrine_mongodb.odm.document_manager')
          ->getRepository('PumukitSchemaBundle:MultimediaObject')
          ->getQueryBuilderOrderedBy($series, $sorting);

        $adapter = new DoctrineODMMongoDBAdapter($mmsQueryBuilder);
        $mms = new Pagerfanta($adapter);

        $mms
          ->setMaxPerPage($maxPerPage)
          ->setNormalizeOutOfRangePages(true);

        /*
          NOTE: Multimedia Objects are sorted by ascending rank.
          A new MultimediaObject is created with last rank,
          so it will be at the end of the list.
          We update the page if a new page is created to show the
          the new MultimediaObject in new last page.
        */
        if ($newMultimediaObjectId && (($mms->getNbResults()/$maxPerPage) > $page)) {
            $page = $mms->getNbPages();
            $session->set('admin/mms/page', $page);
        }
        $mms->setCurrentPage($page);

        return $mms;
    }

    /**
     * {@inheritdoc}
     */
    public function bottomAction(Request $request)
    {
        $config = $this->getConfiguration();
        $resource = $this->findOr404($request);

        //rank zero is the template
        $new_rank = 1;
        $resource->setRank($new_rank);
        $this->domainManager->update($resource);

        $this->addFlash('success', 'up');

        return $this->redirectToRoute(
            $config->getRedirectRoute('index'),
            $config->getRedirectParameters()
        );
    }

    /**
     * Delete action
     * Overwrite to pass series parameter
     */
    public function deleteAction(Request $request)
    {
        $resource = $this->findOr404($request);
        $resourceId = $resource->getId();
        $seriesId = $resource->getSeries()->getId();

        $this->get('pumukitschema.factory')->deleteResource($resource);
        if ($resourceId === $this->get('session')->get('admin/mms/id')){
            $this->get('session')->remove('admin/mms/id');
        }

        return $this->redirect($this->generateUrl('pumukitnewadmin_mms_list', 
                                                  array('seriesId' => $seriesId)));
    }

    /**
     * Clone action
     */
    public function cloneAction(Request $request)
    {
        $resource = $this->findOr404($request);
        $resourceId = $resource->getId();
        $seriesId = $resource->getSeries()->getId();

        $this->get('pumukitschema.factory')->cloneMultimediaObject($resource);

        return $this->redirect($this->generateUrl('pumukitnewadmin_mms_list', 
                                                  array('seriesId' => $seriesId)));
    }

    /**
     * Batch invert announce selected
     */
    public function invertAnnounceAction(Request $request)
    {
        $ids = $this->getRequest()->get('ids');

        if ('string' === gettype($ids)){
            $ids = json_decode($ids, true);
        }

        $tagService = $this->get('pumukitschema.tag');
        $tagNew = $this->get('doctrine_mongodb.odm.document_manager')
          ->getRepository('PumukitSchemaBundle:Tag')->findOneByCod('PUDENEW');
        foreach ($ids as $id){
            $resource = $this->find($id);
            if ($resource->containsTagWithCod('PUDENEW')){
                $addedTags = $tagService->removeTagFromMultimediaObject($resource, $tagNew->getId());
            }else{
                $addedTags = $tagService->addTagToMultimediaObject($resource, $tagNew->getId());
            }
        }

        return $this->redirect($this->generateUrl('pumukitnewadmin_mms_list'));
    }

    /**
     * List action
     * Overwrite to pass series parameter
     */
    public function listAction(Request $request)
    {
        $config = $this->getConfiguration();
        $criteria = $this->getCriteria($config);
        $resources = $this->getResources($request, $config, $criteria);
        $factoryService = $this->get('pumukitschema.factory');
        $seriesId = $request->get('seriesId', null);
        $sessionId = $this->get('session')->get('admin/series/id', null);
        $series = $factoryService->findSeriesById($seriesId, $sessionId);

        $mms = $this->getListMultimediaObjects($series, $request->get('newMmId', null));

        $update_session = true;
        foreach($mms as $mm) {
            if($mm->getId() == $this->get('session')->get('admin/mms/id')){
                $update_session = false;
            }
        }
 
        if($update_session){
            $this->get('session')->remove('admin/mms/id');
        }

        return $this->render('PumukitNewAdminBundle:MultimediaObject:list.html.twig',
                             array(
                                   'series' => $series,
                                   'mms' => $mms
                                   )
                             );
    }

    /**
     * Cut multimedia objects
     */
    public function cutAction(Request $request)
    {
        $ids = $this->getRequest()->get('ids');
        if ('string' === gettype($ids)){
            $ids = json_decode($ids, true);
        }
        $this->get('session')->set('admin/mms/cut', $ids);

        return new JsonResponse($ids);
    }

    /**
     * Paste multimedia objects
     */
    public function pasteAction(Request $request)
    {
        if (!($this->get('session')->has('admin/mms/cut'))){
            throw new \Exception('Not found any multimedia object to paste.');
        }

        $ids = $this->get('session')->get('admin/mms/cut');

        $factoryService = $this->get('pumukitschema.factory');
        $seriesId = $request->get('seriesId', null);
        $sessionId = $this->get('session')->get('admin/series/id', null);
        $series = $factoryService->findSeriesById($seriesId, $sessionId);

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        foreach($ids as $id){
            $multimediaObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')
              ->find($id);
            $mmSeriesId = $multimediaObject->getSeries()->getId();
            if ($id === $this->get('session')->get('admin/mms/id')){
                $this->get('session')->remove('admin/mms/id');
            }
            $multimediaObject->setSeries($series);
            $dm->persist($multimediaObject);
        }
        $dm->persist($series);
        $dm->flush();

        $this->get('session')->remove('admin/mms/cut');

        return $this->redirect($this->generateUrl('pumukitnewadmin_mms_list'));
    }


    /**
     * Reorder multimedia objects
     */
    public function reorderAction(Request $request)
    {
        $factoryService = $this->get('pumukitschema.factory');
        $sessionId = $this->get('session')->get('admin/series/id', null);
        $series = $factoryService->findSeriesById($request->get('id'), $sessionId);

        $sorting = array($request->get("fieldName", "rank") => $request->get("order", 1));

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $mms = $dm
          ->getRepository('PumukitSchemaBundle:MultimediaObject')
          ->findOrderedBy($series, $sorting);

        $rank = 1;
        foreach($mms as $mm){
          $mm->setRank($rank++);
          $dm->persist($mm);
        }
        $dm->flush();

        return $this->redirect($this->generateUrl('pumukitnewadmin_mms_list'));      
    }

    /**
     * Sync tags for all multimedia objects of a series
     */
    public function syncTagsAction(Request $request)
    {
        $multimediaObjectRepo = $this->get('doctrine_mongodb.odm.document_manager')
          ->getRepository('PumukitSchemaBundle:MultimediaObject');
        $multimediaObject = $multimediaObjectRepo
          ->find($request->query->get('id'));

        if(!$multimediaObject)
          return new JsonResponse("Not Found", 404);

        $mms = $multimediaObjectRepo->findBySeries($multimediaObject->getSeries())->toArray();
        $tags = $multimediaObject->getTags()->toArray();
        $this->get('pumukitschema.tag')->resetTags($mms, $tags);
        
        return new JsonResponse("");
    }

    private function dispatchUpdate($multimediaObject)
    {
        $event = new MultimediaObjectEvent($multimediaObject);
        $this->get('event_dispatcher')->dispatch(SchemaEvents::MULTIMEDIAOBJECT_UPDATE, $event);
    }
}
