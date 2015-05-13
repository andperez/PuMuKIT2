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
                return $this->errorAction('badVerb', 'Illegal OAI verb');
            case 'Identify':
                return $this->forward('PumukitOaiBundle:Index:identify');
            case 'ListMetadataFormats':
                return $this->forward('PumukitOaiBundle:Index:listMetadataFormats');
            case 'ListSets':
                return $this->forward('PumukitOaiBundle:Index:listSets');
            case 'ListIdentifiers':
                return $this->forward('PumukitOaiBundle:Index:listIdentifiers');
            case 'ListRecords':
                return $this->forward('PumukitOaiBundle:Index:listRecords');
            case 'GetRecord':
                return $this->forward('PumukitOaiBundle:Index:getRecord');
            default:
                return $this->errorAction('badVerb', 'Illegal OAI verb');
        }

        return array('verb' => $verb);
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
}
