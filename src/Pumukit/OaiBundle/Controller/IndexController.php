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
                return $this->error('badVerb', 'Illegal OAI verb');
        }
    }

    
    public function identifyAction()
    { 
        $test = 1;
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
        if ($this->request->query->get('metadataPrefix', 'vacio') != 'oai_dc'){
            return $this->error('cannotDisseminateFormat', 'cannotDisseminateFormat');
        }
    }

    protected function error($cod, $msg = '')
    {
        $this->cod = $cod;
        $this->msg = $msg;
        return $this->render('PumukitOaiBundle:Index:error.xml.twig', array('cod' => ($this->cod), 'msg' => ($this->msg)));
    }
}

