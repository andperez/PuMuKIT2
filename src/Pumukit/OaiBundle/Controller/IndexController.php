<?php

namespace Pumukit\OaiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
                return $this->forward('PumukitOaiBundle:Index:listIdentifiers');
            case 'ListRecords':
                return $this->forward('PumukitOaiBundle:Index:listRecords');
            case 'ListMetadataFormats':
                return $this->forward('PumukitOaiBundle:Index:listMetadataFormats');
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

        return $this->render('PumukitOaiBundle:Index:getRecord.xml.twig');
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
    public function listIdentifiersAction()
    {
        if($request->query->get('metadataPrefix', 'vacio') != 'oai_dc'){
            return $this->error('cannotDisseminateFormat', 'cannotDisseminateFormat');
        }

        $mmObjColl = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $mmObjColl = $mmObjColl->findAll();

        return $this->render('PumukitOaiBundle:Index:listIdentifiers.xml.twig', array('multimediaObjects' => $mmObjColl));
    }

    /*
     * Genera la salida de listRecords
     */
    public function listRecordsAction()
    {
        if($request->query->get('metadataPrefix', 'vacio') != 'oai_dc'){
            return $this->error('cannotDisseminateFormat', 'cannotDisseminateFormat');
        }
        
        $mmObjColl = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $mmObjColl = $mmObjColl->findAll();

        return $this->render('PumukitOaiBundle:Index:listRecords.xml.twig', array('multimediaObjects' => $mmObjColl));
    }

    /*
     * Genera la salida de listMetadataFormats
     */
    public function listMetadataFormatsAction()
    {
        return $this->render('PumukitOaiBundle:Index:listMetadataFormats.xml.twig');
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
}

