<?php

namespace Pumukit\OaiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;


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
                return $this->forward('PumukitOaiBundle:Index:listSets', array("request" => $request));
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
        $pag = 2;
        $from = $request->query->get('from');
        $until = $request->query->get('until');
        $set = $request->query->get('set');
        $resumptionToken = $request->query->get('resumptionToken');

        if($request->query->get('metadataPrefix', 'vacio') != 'oai_dc'){
            return $this->error('cannotDisseminateFormat', 'cannotDisseminateFormat');
        }

        $token = $this->validateToken($resumptionToken);
        if($token['pag'] != null){
            $pag = $token['pag'];
        }

        $mmObjColl = $this->filter($request, $pag);

        if(count($mmObjColl) == 0){
            return $this->error('noRecordsMatch', 'The combination of the values of the from, until, and set arguments results in an empty list');
        }

        if((($resumptionToken > ceil(count($mmObjColl)/10)) or ($resumptionToken < 1)) and $resumptionToken != null){
            return $this->error('badResumptionToken', 'The value of the resumptionToken argument is invalid or expired');
        }

        if($pag >= ceil(count($mmObjColl)/10)) {
            $pag = ceil(count($mmObjColl)/10);
        }

        return $this->render('PumukitOaiBundle:Index:listIdentifiers.xml.twig', 
            array('multimediaObjects' => $mmObjColl, 'from' => $from, 'until' => $until, 'set' => $set, 'pag' => $pag));
    }

    /*
     * Genera la salida de listRecords
     */
    public function listRecordsAction($request)
    {
        $pag = 2;
        $from = $request->query->get('from');
        $until = $request->query->get('until');
        $set = $request->query->get('set');
        $resumptionToken = $request->query->get('resumptionToken');

        if($request->query->get('metadataPrefix', 'vacio') != 'oai_dc'){
            return $this->error('cannotDisseminateFormat', 'cannotDisseminateFormat');
        }

        $token = $this->validateToken($resumptionToken);
        if($token['pag'] != null){
            $pag = $token['pag'];
        }

        $mmObjColl = $this->filter($request, $pag);

        if(count($mmObjColl) == 0){
            return $this->error('noRecordsMatch', 'The combination of the values of the from, until, and set arguments results in an empty list');
        }

        if((($resumptionToken > ceil(count($mmObjColl)/10)) or ($resumptionToken < 1)) and $resumptionToken != null){
            return $this->error('badResumptionToken', 'The value of the resumptionToken argument is invalid or expired');
        }

        if($pag >= ceil(count($mmObjColl)/10)) {
            $pag = ceil(count($mmObjColl)/10);
        }

        return $this->render('PumukitOaiBundle:Index:listRecords.xml.twig', 
            array('multimediaObjects' => $mmObjColl, 'from' => $from, 'until' => $until, 'set' => $set, 'pag' => $pag));
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
    public function listSetsAction($request)
    {
        $pag = 2;
        $resumptionToken = $request->query->get('resumptionToken');

        $token = $this->validateToken($resumptionToken);
        if($token['error'] == true){
            return $this->error('badResumptionToken', 'The value of the resumptionToken argument is invalid or expired');
        }

        if($token['pag'] != null){
            $pag = $token['pag'];
        }

        $allSeries = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Series');
        $allSeries = $allSeries->createQueryBuilder()->limit(10)->skip(10*($pag-2));
        $allSeries = $allSeries->getQuery()->execute();

        if((($resumptionToken > ceil(count($allSeries)/10)) or ($resumptionToken < 1)) and $resumptionToken != null){
            return $this->error('badResumptionToken', 'The value of the resumptionToken argument is invalid or expired');
        }

        if($pag >= ceil(count($allSeries)/10)) {
            $pag = ceil(count($allSeries)/10);
        }

        return $this->render('PumukitOaiBundle:Index:listSets.xml.twig', array('allSeries' => $allSeries, 'pag' => $pag));
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
    protected function filter($request, $pag)
    {
        $repository_multimediaObjects = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $repository_series = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Series');
        
        $queryBuilder_multimediaObjects = $repository_multimediaObjects->createStandardQueryBuilder()->limit(10)->skip(10*($pag-2));
        $queryBuilder_series = $repository_series->createQueryBuilder();

        if($request->query->get('from')){
            $from = \DateTime::createFromFormat("Y/m/d", $request->query->get('from'));
            $queryBuilder_multimediaObjects->field('public_date')->gte($from);
        }

        if($request->query->get('until')){
            $until = \DateTime::createFromFormat("Y/m/d", $request->query->get('until'));
            $queryBuilder_multimediaObjects->field('public_date')->lte($until);
        }

        if($request->query->get('set')){
            $set = $request->query->get('set');
            $series = $repository_series->find(array('id'  => $set));
            $queryBuilder_multimediaObjects->field('series')->references($series);
        }

        $objects = $queryBuilder_multimediaObjects->getQuery()->execute();

        return $objects;
    }


    /*
     * Valida si el token pasado en resumptionToken es correcto
     */
    protected function validateToken($resumptionToken)
    {
        if($resumptionToken != null){
            $error = false;
            return array('pag' => (((int)$resumptionToken)+1), 'error' => $error);
        }
    }
}

