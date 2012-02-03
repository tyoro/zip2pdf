<?php

define('FPDIMAGE_VERSION', '1.0.0');

require_once('fpdf.php');
require_once('fpdi.php');

class FPDImage extends FPDI {
	
    /**
     * get Image
     *
     * @param int $pageno pagenumber
     * @return int Index of imported page - to use with fpdf_tpl::useTemplate()
     */
    function getImage($pageno) {
        if ($this->_intpl) {
            return $this->error('Please import the desired pages before creating a new template.');
        }
        
        $fn = $this->current_filename;
        
        $parser =& $this->parsers[$fn];
        $parser->setPageno($pageno);
        
        $tpl = array();
        $tpl['parser'] =& $parser;
        $tpl['resources'] = $parser->getPageResources();
        $tpl['buffer'] = $parser->getContent();
        
		if( $tpl['resources'][0] == PDF_TYPE_DICTIONARY && isset( $tpl['resources'][1]['/XObject'] ) ){
			foreach( $tpl['resources'][1]['/XObject'][1] as $obj => $ref ){
				if( strpos( $obj , "/Obj" ) !== false ){
					$data = $parser->pdf_resolve_object($parser->c, $ref );
				}
			}
		}

		if( $data[1][0] == PDF_TYPE_DICTIONARY && $data[1][1]["/Subtype"][1] == "/Image" ){
			return  Array( 
				'blob' => $data[2][1],
				'height' => $data[1][1]["/Height"][1],
				'width' => $data[1][1]["/Width"][1],
				'bitspercomponent' => $data[1][1]["/BitsPerComponent"][1],
				'colorspace' => $data[1][1]["/ColorSpace"][1],
				'length' => $data[1][1]["/Length"][1]
			);
		}

	return null;
    }
}
