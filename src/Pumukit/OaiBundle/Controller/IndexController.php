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
        switch ($request->query->get('verb', 'vacio')) {
            case 'vacio':
                throw $this->createNotFoundException('Illegal OAI verb');
            case 'Identify':
                $this->forward('OaiBundle:Index:identify', array('verb'=>'Identify'));
            break;
            case 'ListMetadataFormats':
                $this->forward('OaiBundle:Index:listMetadataFormats', array('verb'=>'ListMetadataFormats'));
            break;
            case 'ListSets':
                $this->forward('OaiBundle:Index:listSets', array('verb'=>'ListSets'));
            break;
            case 'ListIdentifiers':
                $this->forward('OaiBundle:Index:listIdentifiers', array('verb'=>'ListIdentifiers'));
            break;
            case 'ListRecords':
                $this->forward('OaiBundle:Index:listRecords', array('verb'=>'ListRecords'));
            break;
            case 'GetRecord':
                $this->forward('OaiBundle:Index:getRecord', array('verb'=>'GetRecord'));
            break;
            default:
                throw $this->createNotFoundException('Illegal OAI verb');
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
}
