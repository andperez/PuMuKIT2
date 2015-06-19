<?php

namespace Pumukit\OpencastBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;

use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\SchemaBundle\Document\Pic;
use Pumukit\OpencastBundle\Services\OpencastService;

/**
 * @Route("/admin")
 */
class MediaPackageController extends Controller
{
    private $dm = null;

    /**
     * @Route("/opencast/mediapackage", name="pumukitopencast")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        if(!$this->has('pumukit_opencast.client')) {
          throw $this->createNotFoundException('PumukitOpencastBundle not configured.');
        }

        $opencastClient = $this->get('pumukit_opencast.client');
        $repository_multimediaobjects = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');

        $limit = 10;
        $page =  $request->get("page", 1);
        $criteria = $this->getCriteria($request);


        list($total, $mediaPackages) = $opencastClient->getMediaPackages(
                (isset($criteria["name"])) ? $criteria["name"]->regex : 0,
                $limit,
                ($page -1) * $limit);

        $currentPageOpencastIds = array();
        foreach ($mediaPackages as $mediaPackage) {
            $currentPageOpencastIds[] = $mediaPackage["id"];
        }

        $adapter = new FixedAdapter($total, $mediaPackages);
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        $repo = $repository_multimediaobjects->createQueryBuilder()
          ->field("properties.opencast")->exists(true)
          ->field("properties.opencast")->in($currentPageOpencastIds)
          ->getQuery()
          ->execute();

        return array('mediaPackages' => $pagerfanta, 'multimediaObjects' => $repo, 'player' => $opencastClient->getPlayerUrl());
    }


    /**
     * @Route("/opencast/mediapackage/{id}", name="pumukitopencast_import")
     */
    public function importAction($id, Request $request)
    {
        $opencastService = $this->get('pumukit_opencast.import');
        $opencastService->importRecording($id);
        return $this->redirect($this->getRequest()->headers->get('referer'));
    }

    /**
     * Gets the criteria values
     */
    public function getCriteria($request)
    {
        $criteria = $request->get('criteria', array());


        if (array_key_exists('reset', $criteria)) {
            $this->get('session')->remove('admin/opencast/criteria');
        } elseif ($criteria) {
            $this->get('session')->set('admin/opencast/criteria', $criteria);
        }
        $criteria = $this->get('session')->get('admin/opencast/criteria', array());

        $new_criteria = array();

        foreach ($criteria as $property => $value) {
            //preg_match('/^\/.*?\/[imxlsu]*$/i', $e)
            if ('' !== $value) {
                $new_criteria[$property] = new \MongoRegex('/'.$value.'/i');
            }
        }

        return $new_criteria;
    }
}
