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
            default:
                throw $this->createNotFoundException('Illegal OAI verb');
        }
    }

    /**
     * @Route("identify/oai.xml", defaults={"_format": "xml"}, name="pumukit_oai_identify")
     * @Template()
     */
    public function identifyAction()
    {
    }

    /**
     * @Route("listmetadataformats/oai.xml", defaults={"_format": "xml"}, name="pumukit_oai_listmetadataformats")
     * @Template()
     */
    public function listMetadataFormatsAction()
    {
    }

    /**
     * @Route("listsets/oai.xml", defaults={"_format": "xml"}, name="pumukit_oai_listsets")
     * @Template()
     */
    public function listSetsAction()
    {
    }

    /**
     * @Route("listidentifiers/oai.xml", defaults={"_format": "xml"}, name="pumukit_oai_listidentifiers")
     * @Template()
     */
    public function listIdentifiersAction()
    {
    }

    /**
     * @Route("listrecords/oai.xml", defaults={"_format": "xml"}, name="pumukit_oai_listrecords")
     * @Template()
     */
    public function listRecordsAction()
    {
    }

    /**
     * @Route("getrecord/oai.xml", defaults={"_format": "xml"}, name="pumukit_oai_getrecord")
     * @Template()
     */
    public function getRecordAction()
    {
    }
}
