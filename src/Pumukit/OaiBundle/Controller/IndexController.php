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
                return array($this->forward('PumukitOaiBundle:Index:error'), 'verb' => 'badVerb', 'msg' => 'Illegal OAI verb');
            case 'Identify':
                return array($this->forward('PumukitOaiBundle:Index:identify'), 'verb' => $verb);
            case 'ListMetadataFormats':
                return array($this->forward('PumukitOaiBundle:Index:listMetadataFormats'), 'verb' => $verb);
            case 'ListSets':
                return array($this->forward('PumukitOaiBundle:Index:listSets'), 'verb' => $verb);
            case 'ListIdentifiers':
                return array($this->forward('PumukitOaiBundle:Index:listIdentifiers'), 'verb' => $verb);
            case 'ListRecords':
                return array($this->forward('PumukitOaiBundle:Index:listRecords'), 'verb' => $verb);
            case 'GetRecord':
                return array($this->forward('PumukitOaiBundle:Index:getRecord'), 'verb' => $verb);
            default:
                return array($this->forward('PumukitOaiBundle:Index:error'), 'verb' => 'badVerb', 'msg' => 'Illegal OAI verb');
        }
    }

    
    public function identifyAction()
    { 
    }


    public function listMetadataFormatsAction()
    {
    }


    public function listSetsAction()
    {
    }


    public function listIdentifiersAction()
    {
    }


    public function listRecordsAction()
    {
    }


    public function getRecordAction()
    {
    }

    /*protected function errorAction($cod, $msg = '')
    {
        $this->cod = $cod;
        $this->msg = $msg;
        return array('cod' => ($this->cod), 'msg' => ($this->msg));
    }*/
}

