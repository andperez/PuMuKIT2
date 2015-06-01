<?php

namespace Pumukit\OaiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Doctrine\Common\Collections\Criteria;


class IndexController extends Controller
{

    /**
     * @Route("/oai.xml", defaults={"_format": "xml"}, name="pumukit_oai_index")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $verb = $request->query->get('verb');

        switch ($request->query->get('verb', 'vacio')) {
            case 'vacio':
                return $this->error('badVerb', 'Illegal OAI verb');
            case 'GetRecord':
                return $this->forward('PumukitOaiBundle:Index:getRecord', array("request" => $request));
            case 'Identify':
                return $this->forward('PumukitOaiBundle:Index:identify');
            case 'ListIdentifiers':
                return $this->forward('PumukitOaiBundle:Index:listIdentifiers', array("request" => $request));
            case 'ListRecords':
                return $this->forward('PumukitOaiBundle:Index:listRecords', array("request" => $request));
            case 'ListMetadataFormats':
                return $this->forward('PumukitOaiBundle:Index:listMetadataFormats', array("request" => $request));
            case 'ListSets':
                return $this->forward('PumukitOaiBundle:Index:listSets');
            default:
                return $this->error('badVerb', 'Illegal OAI verb');
        }
    }


    /*
     * Genera la salida de GetRecord
     */
    public function getRecordAction($request)
    {
        if($request->query->get('metadataPrefix', 'vacio') != 'oai_dc'){
            return $this->error('cannotDisseminateFormat', 'cannotDisseminateFormat');
        }

        $identifier = $request->query->get('identifier');

        $mmObjColl = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $mmObj = $mmObjColl->find(array('id'  => $identifier));

        if ($mmObj == null)
            return $this->error('idDoesNotExist', 'The value of the identifier argument is unknown or illegal in this repository');

        return $this->render('PumukitOaiBundle:Index:getRecord.xml.twig', array('multimediaObject' => $mmObj, 'identifier' => $identifier));
    }

    /*
     * Genera la salida del Identify
     */
    public function identifyAction()
    { 
        return $this->render('PumukitOaiBundle:Index:identify.xml.twig');
    }

    /*
     * Genera la salida de ListIdentifiers
     */
    public function listIdentifiersAction($request)
    {
        $from = $request->query->get('from');
        $until = $request->query->get('until');
        $set = $request->query->get('set');

        if($request->query->get('metadataPrefix', 'vacio') != 'oai_dc'){
            return $this->error('cannotDisseminateFormat', 'cannotDisseminateFormat');
        }

        $mmObjColl = $this->filter($request);

        if(count($mmObjColl) == 0){
            return $this->error('noRecordsMatch', 'The combination of the values of the from, until, and set arguments results in an empty list');
        }

        return $this->render('PumukitOaiBundle:Index:listIdentifiers.xml.twig', 
            array('multimediaObjects' => $mmObjColl, 'from' => $from, 'until' => $until, 'set' => $set));
    }

    /*
     * Genera la salida de listRecords
     */
    public function listRecordsAction($request)
    {
        $from = $request->query->get('from');
        $until = $request->query->get('until');
        $set = $request->query->get('set');

        if($request->query->get('metadataPrefix', 'vacio') != 'oai_dc'){
            return $this->error('cannotDisseminateFormat', 'cannotDisseminateFormat');
        }

        $mmObjColl = $this->filter($request);

        if(count($mmObjColl) == 0){
            return $this->error('noRecordsMatch', 'The combination of the values of the from, until, and set arguments results in an empty list');
        }

        return $this->render('PumukitOaiBundle:Index:listRecords.xml.twig', 
            array('multimediaObjects' => $mmObjColl, 'from' => $from, 'until' => $until, 'set' => $set));
    }

    /*
     * Genera la salida de listMetadataFormats
     */
    public function listMetadataFormatsAction($request)
    {
        $identifier = $request->query->get('identifier');

        $mmObjColl = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $mmObj = $mmObjColl->find(array('id'  => $identifier));

        if ($mmObj == null)
            return $this->error('idDoesNotExist', 'The value of the identifier argument is unknown or illegal in this repository');

        return $this->render('PumukitOaiBundle:Index:listMetadataFormats.xml.twig', array('identifier' => $identifier));
    }

    /*
     * Genera la salida de listSets
     */
    public function listSetsAction()
    {
        $allSeries = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Series');
        $allSeries = $allSeries->findAll();

        return $this->render('PumukitOaiBundle:Index:listSets.xml.twig', array('allSeries' => $allSeries));
    }

    /*
     * Genera el XML de error
     */
    protected function error($cod, $msg = '')
    {
        $this->cod = $cod;
        $this->msg = $msg;
        return $this->render('PumukitOaiBundle:Index:error.xml.twig', array('cod' => ($this->cod), 'msg' => ($this->msg)));
    }

    /*
     * Modifica el objeto criteria de entrada añadiendo filtros de fechas (until & from) y de set si están definidos en la URI
     */
    protected function filter($request){

        $repository_multimediaObjects = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $repository_series = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Series');
        $queryBuilder_multimediaObjects = $repository_multimediaObjects->createStandardQueryBuilder();
        $queryBuilder_series = $repository_series->createQueryBuilder();

        if($request->query->get('from')){
            $from = \DateTime::createFromFormat("Y/m/d", $request->query->get('from'));
            $queryBuilder_multimediaObjects->field('public_date')->gte($from);
        }

        if($request->query->get('until')){
            $until = \DateTime::createFromFormat("Y/m/d", $request->query->get('until'));
            $queryBuilder_multimediaObjects->field('public_date')->lte($until);
        }

        $objects = $queryBuilder_multimediaObjects->getQuery()->execute();

        if($request->query->get('set')){
            $queryBuilder_series->field('id')->equals($request->query->get('set'));
            $series = $queryBuilder_series->getQuery()->execute();

            foreach ($series as $serie) {
                $objects = $serie->getMultimediaObjects();
            }
        }

        return $objects;
    }
}

