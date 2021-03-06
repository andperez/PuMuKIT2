<?php

namespace Pumukit\OaiBundle\Utils;

use Symfony\Component\HttpFoundation\Response;
use SimpleXMLElement;

class SimpleXMLExtended extends SimpleXMLElement {
    
    public function addCDATA($cData){
        $node = dom_import_simplexml($this);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cData));
    }
}

