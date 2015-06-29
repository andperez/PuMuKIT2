<?php

namespace Pumukit\OaiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use SimpleXMLElement;


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
                return $this->forward('PumukitOaiBundle:Index:list', array("request" => $request));
            case 'ListRecords':
                return $this->forward('PumukitOaiBundle:Index:list', array("request" => $request));
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
        $object = $mmObjColl->find(array('id'  => $identifier));

        if ($object == null)
            return $this->error('idDoesNotExist', 'The value of the identifier argument is unknown or illegal in this repository');
        
        $XML = new SimpleXMLExtended("<OAI-PMH></OAI-PMH>");
        $XML->addAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
        $XML->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $XML->addAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
        $XMLresponseDate = $XML->addChild('responseDate', date("D M d, Y G:i"));
        $XMLrequest = $XML->addChild('request', $this->generateUrl('pumukit_oai_index'));
        $XMLrequest->addAttribute('verb', 'GetRecord');
        $XMLrequest->addAttribute('identifier', $identifier);
        $XMLrequest->addAttribute('metadataPrefix', 'oai_dc');
        $XMLgetRecord = $XML->addChild('GetRecord');
        $XMLrecord = $XMLgetRecord->addChild('record');
        $XMLheader = $XMLrecord->addChild('header');
        $XMLidentifier = $XMLheader->addChild('identifier');
        $XMLidentifier->addCDATA($object->getId());
        $XMLheader->addChild('datestamp', $object->getPublicDate()->format('Y-m-d'));
        $XMLsetSpec = $XMLheader->addChild('setSpec');
        $XMLsetSpec->addCDATA($object->getSeries()->getId());
        $XMLmetadata = $XMLrecord->addChild('metadata');
        $XMLoai_dc = $XMLmetadata->addChild('oai_dc:dc');
        $XMLoai_dc->addAttribute('xmlns:oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $XMLoai_dc->addAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $XMLoai_dc->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $XMLoai_dc->addAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd');
        $XMLtitle = $XMLoai_dc->addChild('dc:title');
        $XMLtitle->addCDATA($object->getTitle());
        $XMLdescription = $XMLoai_dc->addChild('dc:description');
        $XMLdescription->addCDATA($object->getDescription());
        $XMLoai_dc->addChild('dc:date', $object->getPublicDate()->format('Y-m-d'));
        $XMLiden = $XMLoai_dc->addChild('dc:identifier');
        $XMLiden->addAttribute('xsi:type', 'dcterms:URI');
        $XMLiden->addAttribute('id', 'uid');
        foreach($object->getTracks() as $track){
            $XMLtype = $XMLoai_dc->addChild('dc:type');
            $XMLtype->addCDATA($track->getMimeType());
            $XMLoai_dc->addChild('dc:format');
        }
        foreach($object->getTags() as $tag){
            $XMLsubject = $XMLoai_dc->addChild('dc:subject');
            $XMLsubject->addCDATA($tag->getTitle());
        }
        $XMLcreator = $XMLoai_dc->addChild('dc:creator');
        $XMLcreator->addCDATA('');
        $XMLpublisher = $XMLoai_dc->addChild('dc:publisher');
        $XMLpublisher->addCDATA('');
        $XMLoai_dc->addChild('dc:language', $object->getLocale());
        $XMLrights = $XMLoai_dc->addChild('dc:rights');
        $XMLrights->addCDATA('');

        return new Response($XML->asXML(), 200, array('Content-Type' => 'text/xml'));
    }

    /*
     * Genera la salida del Identify
     */
    public function identifyAction()
    { 
        $request = "<request>" . $this->generateUrl('pumukit_oai_index') . "</request>";
        $XMLrequest = new SimpleXMLExtended($request);
        $XMLrequest->addAttribute('verb', 'Identify');

        $XMLidentify = new SimpleXMLExtended("<Identify></Identify>");
        $XMLidentify->addChild('repositoryName');
        $XMLidentify->addChild('baseURL', $this->generateUrl('pumukit_oai_index'));
        $XMLidentify->addChild('protocolVersion', '2.0');
        $XMLidentify->addChild('adminEmail');
        $XMLidentify->addChild('earliestDatestamp', '1990-02-01T12:00:00Z');
        $XMLidentify->addChild('deletedRecord', 'no');
        $XMLidentify->addChild('granularity', 'YYYY-MM-DDThh:mm:ssZ');

        return $XMLidentify->loadXMLVerb($XMLrequest,$XMLidentify);
    }

    /*
     * Genera la salida de ListIdentifiers o ListRecords
     */
    public function listAction($request)
    {
        $pag = 2;
        $verb = $request->query->get('verb');
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

        $request = "<request>" . $this->generateUrl('pumukit_oai_index') . "</request>";
        $XMLrequest = new SimpleXMLExtended($request);
        $XMLrequest->addAttribute('metadataPrefix', 'oai_dc');
        $XMLrequest->addAttribute('from', $from);
        $XMLrequest->addAttribute('until', $until);
        $XMLrequest->addAttribute('set', $set);
        if($verb == "ListIdentifiers"){
            $XMLrequest->addAttribute('verb', 'ListIdentifiers');
            $XMLlistIdentifiers = new SimpleXMLExtended("<ListIdentifiers></ListIdentifiers>");
            foreach($mmObjColl as $object){
                $XMLheader = $XMLlistIdentifiers->addChild('header');
                $XMLidentifier = $XMLheader->addChild('identifier');
                $XMLidentifier->addCDATA($object->getId());
                $XMLheader->addChild('datestamp', $object->getPublicDate()->format('Y-m-d'));
                $XMLsetSpec = $XMLheader->addChild('setSpec');
                $XMLsetSpec->addCDATA($object->getSeries()->getId());
            }
            $XMLresumptionToken = $XMLlistIdentifiers->addChild('resumptionToken', $pag);
            $XMLresumptionToken->addAttribute('expirationDate', '2002-06-01T23:20:00Z');
            $XMLresumptionToken->addAttribute('completeListSize', count($mmObjColl));
            $XMLresumptionToken->addAttribute('cursor', '0');

            return $XMLlistIdentifiers->loadXMLVerb($XMLrequest,$XMLlistIdentifiers);
        }

        else{
            $XMLrequest->addAttribute('verb', 'ListRecords');
            $XMLlistRecords = new SimpleXMLExtended("<ListRecords></ListRecords>");
            foreach($mmObjColl as $object){
                $XMLrecord = $XMLlistRecords->addChild('record');
                $XMLheader = $XMLrecord->addChild('header');
                $XMLidentifier = $XMLheader->addChild('identifier');
                $XMLidentifier->addCDATA($object->getId());
                $XMLheader->addChild('datestamp', $object->getPublicDate()->format('Y-m-d'));
                $XMLsetSpec = $XMLheader->addChild('setSpec');
                $XMLsetSpec->addCDATA($object->getSeries()->getId());
                $XMLmetadata = $XMLrecord->addChild('metadata');
                $XMLoai_dc = $XMLmetadata->addChild('oai_dc:dc');
                $XMLoai_dc->addAttribute('xmlns:oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
                $XMLoai_dc->addAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
                $XMLoai_dc->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
                $XMLoai_dc->addAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd');
                $XMLtitle = $XMLoai_dc->addChild('dc:title');
                $XMLtitle->addCDATA($object->getTitle());
                $XMLdescription = $XMLoai_dc->addChild('dc:description');
                $XMLdescription->addCDATA($object->getDescription());
                $XMLoai_dc->addChild('dc:date', $object->getPublicDate()->format('Y-m-d'));
                $XMLiden = $XMLoai_dc->addChild('dc:identifier');
                $XMLiden->addAttribute('xsi:type', 'dcterms:URI');
                $XMLiden->addAttribute('id', 'uid');
                foreach($object->getTracks() as $track){
                    $XMLtype = $XMLoai_dc->addChild('dc:type');
                    $XMLtype->addCDATA($track->getMimeType());
                    $XMLoai_dc->addChild('dc:format');
                }
                foreach($object->getTags() as $tag){
                    $XMLsubject = $XMLoai_dc->addChild('dc:subject');
                    $XMLsubject->addCDATA($tag->getTitle());
                }
                $XMLcreator = $XMLoai_dc->addChild('dc:creator');
                $XMLcreator->addCDATA('');
                $XMLpublisher = $XMLoai_dc->addChild('dc:publisher');
                $XMLpublisher->addCDATA('');
                $XMLoai_dc->addChild('dc:language', $object->getLocale());
                $XMLrights = $XMLoai_dc->addChild('dc:rights');
                $XMLrights->addCDATA('');
            }
            $XMLresumptionToken = $XMLlistRecords->addChild('resumptionToken', $pag);
            $XMLresumptionToken->addAttribute('expirationDate', '2002-06-01T23:20:00Z');
            $XMLresumptionToken->addAttribute('completeListSize', count($mmObjColl));
            $XMLresumptionToken->addAttribute('cursor', '0');

            return $XMLlistRecords->loadXMLVerb($XMLrequest,$XMLlistRecords);
        }
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

        $request = "<request>" . $this->generateUrl('pumukit_oai_index') . "</request>";
        $XMLrequest = new SimpleXMLExtended($request);
        $XMLrequest->addAttribute('verb', 'ListMetadataFormats');
        $XMLrequest->addAttribute('identifier', $identifier);

        $XMLlistMetadataFormats = new SimpleXMLExtended("<ListMetadataFormats></ListMetadataFormats>");
        $XMLmetadataFormat = $XMLlistMetadataFormats->addChild('metadataFormat');
        $XMLmetadataFormat->addChild('metadataPrefix', 'oai_dc');
        $XMLmetadataFormat->addChild('schema', 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd');
        $XMLmetadataFormat->addChild('metadataNamespace', 'http://www.openarchives.org/OAI/2.0/oai_dc/');

        return $XMLlistMetadataFormats->loadXMLVerb($XMLrequest,$XMLlistMetadataFormats);
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

        $XML = new SimpleXMLExtended("<OAI-PMH></OAI-PMH>");
        $XML->addAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
        $XML->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $XML->addAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
        $XMLresponseDate = $XML->addChild('responseDate', date("D M d, Y G:i"));
        $XMLrequest = $XML->addChild('request', $this->generateUrl('pumukit_oai_index'));
        $XMLrequest->addAttribute('verb', 'ListSets');
        $XMLlistSets = $XML->addChild('ListSets');
        foreach($allSeries as $series){
            $XMLset = $XMLlistSets->addChild('set');
            $XMLsetSpec = $XMLset->addChild('setSpec');
            $XMLsetSpec->addCDATA($series->getId());
            $XMLsetName = $XMLset->addChild('setName');
            $XMLsetName->addCDATA($series->getTitle());
        }
        $XMLresumptionToken = $XMLlistSets->addChild('resumptionToken', $pag);
        $XMLresumptionToken->addAttribute('expirationDate', '2002-06-01T23:20:00Z');
        $XMLresumptionToken->addAttribute('completeListSize', count($allSeries));
        $XMLresumptionToken->addAttribute('cursor', '0');

        return new Response($XML->asXML(), 200, array('Content-Type' => 'text/xml'));
    }

    /*
     * Genera el XML de error
     */
    protected function error($cod, $msg = '')
    {
        $this->cod = $cod;
        $this->msg = $msg;
        
        $XML = new SimpleXMLExtended("<OAI-PMH></OAI-PMH>");
        $XML->addAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
        $XML->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $XML->addAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
        $XMLresponseDate = $XML->addChild('responseDate', date("D M d, Y G:i"));
        $XMLrequest = $XML->addChild('request', $this->generateUrl('pumukit_oai_index'));
        $XMLerror = $XML->addChild('error', $this->msg);
        $XMLerror->addAttribute('code', $this->cod);

        return new Response($XML->asXML(), 200, array('Content-Type' => 'text/xml'));        
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
    protected function validateToken($resumptionToken){
        if($resumptionToken != null){
            $error = false;
            return array('pag' => (((int)$resumptionToken)+1), 'error' => $error);
        }
    }
}



class SimpleXMLExtended extends SimpleXMLElement {
    
    public function addCDATA($cData){
        $node = dom_import_simplexml($this);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cData));
    }

    public function loadXMLVerb($request, $verb){

        $XML = new SimpleXMLExtended("<OAI-PMH></OAI-PMH>");
        $XML->addAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
        $XML->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $XML->addAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
        $XMLresponseDate = $XML->addChild('responseDate', date("D M d, Y G:i"));

        $toDom = dom_import_simplexml($XML);
        $fromDom = dom_import_simplexml($request);
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
        $XML = simplexml_import_dom($toDom);

        $toDom = dom_import_simplexml($XML);
        $fromDom = dom_import_simplexml($verb);
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
        $XML = simplexml_import_dom($toDom);

        return new Response($XML->asXML(), 200, array('Content-Type' => 'text/xml'));
    }
}

