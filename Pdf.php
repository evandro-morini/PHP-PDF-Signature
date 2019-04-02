<?php

/** Zend_Pdf */
require_once 'Zend/Pdf.php';

require_once 'Zend/Pdf/Exception.php';


class Farit_Pdf extends Zend_Pdf {
    
    //maximum length of the signature, pad the field to this length by 0
    const SIGNATURE_MAX_LENGTH = 11742;

    /**
     * The certificate string
     * @var string
     */
    protected $_certificate;
    
    /**
     * The certificate password
     * @var string
     */
    protected $_certificatePassword; 
    
    /**
     * @var string The current time in the PDF format
     */
    protected $_currentTime;
     

    public function __construct($source = null, $revision = null, $load = false)
    {    
	    parent::__construct($source, $revision, $load);
	
	    $this->_currentTime = Zend_Pdf::pdfDate();
    }


    /**
     * Attaches the signature object to the PDF document
     *
     * @param string $certificate The certificate value in the PKCS#12 format
     * @param string $password The certificate password
     *
     * @throws Zend_Pdf_Exception
     */
    public function attachDigitalCertificate($certificate, $password)
    {
	    if (empty($certificate) || empty($password)) {
	        throw new Zend_Pdf_Exception("No Certificate or password for attaching to the PDF");
	    }
	    $this->_certificate = $certificate;
	    $this->_certificatePassword = $password;
	
	    if (count($this->pages) == 0) {
	        throw new Zend_Pdf_Exception("Cannot attach the digital certificate to a document without pages");	    
	    }
	
	    //create the Certificate Dictionary Element
    	$certificateDictionary = new Zend_Pdf_Element_Dictionary();

	    //add subfields
	    $certificateDictionary->Type = new Zend_Pdf_Element_Name('Sig');
	    $certificateDictionary->Filter = new Zend_Pdf_Element_Name('Adobe.PPKLite');
	    $certificateDictionary->SubFilter = new Zend_Pdf_Element_Name('adbe.pkcs7.detached');
	    $certificateDictionary->ByteRange = new Zend_Pdf_Element_Array(array(
	        new Zend_Pdf_Element_Numeric(0),
	        new Zend_Pdf_Element_Numeric(9999999999),
	        new Zend_Pdf_Element_Numeric(9999999999),
	        new Zend_Pdf_Element_Numeric(9999999999),
	    ));
	    //custom element to add raw text		    
	    $certificateDictionary->Contents = new Farit_Pdf_ElementRaw('<' . str_repeat('0', self::SIGNATURE_MAX_LENGTH) . '>');
	
	    //reference to the signature    
	    $reference = new Zend_Pdf_Element_Dictionary();
	    $reference->Type = new Zend_Pdf_Element_Name('SigRef');
	    //permissions
	    $reference->TransformMethod = new Zend_Pdf_Element_Name('DocMDP');
	
	    $transformParams = new Zend_Pdf_Element_Dictionary();
	    $transformParams->Type = new Zend_Pdf_Element_Name('TransformParams');
	    $transformParams->V = new Zend_Pdf_Element_Name('1.2');
	    //no changes are allowed
	    $transformParams->P = new Zend_Pdf_Element_Numeric(1);
	
	    $reference->TransformParams = $transformParams;
	    $certificateDictionary->Reference = new Zend_Pdf_Element_Array(array($reference));

	    $certificateDictionary->M = new Zend_Pdf_Element_String($this->_currentTime);

	    //the Catalog element
        $root = $this->_trailer->Root;
	
	    //now attach the certificate field to the document
	    $certificateDictionary = $this->_objFactory->newObject($certificateDictionary);

	    //permissions go in the catalog
	    $perms = new Zend_Pdf_Element_Dictionary();
	    $perms->DocMDP = $certificateDictionary;
	    $root->Perms = $perms;

	    //create the small square widget at the top to point to the signature
	    $this->attachSignatureWidget($certificateDictionary);

    }


    /**
     * Adds the signature widget
     * 
     * @param Zend_Pdf_Element_Dictionary $certificateDictionary
     *
     * @return Zend_Pdf_Element_Dictionary
     */
    protected function attachSignatureWidget($certificateDictionary)
    {
	    //get the first page
	    $pages = $this->pages;
	    $page = array_shift($pages);

	    //the Catalog element
        $root = $this->_trailer->Root;
    
	    $signatureDictionary = new Zend_Pdf_Element_Dictionary();
	    $signatureDictionary->Type = new Zend_Pdf_Element_Name('Annot');
	    $signatureDictionary->SubType = new Zend_Pdf_Element_Name('Widget');
	    //zero rectangular
	    $signatureDictionary->Rect = new Zend_Pdf_Element_Array(array(new Zend_Pdf_Element_Numeric(0),
	        new Zend_Pdf_Element_Numeric(0),  new Zend_Pdf_Element_Numeric(0), new Zend_Pdf_Element_Numeric(0)));
	    //page    
	    $signatureDictionary->P = $page->getPageDictionary();
	    $signatureDictionary->F = new Zend_Pdf_Element_Numeric(4);
	    $signatureDictionary->FT = new Zend_Pdf_Element_Name('Sig');
	    $signatureDictionary->T = new Zend_Pdf_Element_String('Signature');
	    $signatureDictionary->Ff = new Zend_Pdf_Element_Numeric(0);
	    //pointer to the certificate
	    $signatureDictionary->V = $certificateDictionary;	

	    //now attach the signature widget to the document	
	    $signatureDictionary = $this->_objFactory->newObject($signatureDictionary);

	    //pointer to the Signature Widget
	    $acroForm = new Zend_Pdf_Element_Dictionary();
	    $acroForm->Fields = new Zend_Pdf_Element_Array(array($signatureDictionary)); 
	    $acroForm->SigFlags = new Zend_Pdf_Element_Numeric(3);
	    $root->AcroForm = $acroForm;
    }

    /**
     * Load PDF document from a file
     *
     * @param string $source
     * @param integer $revision
     * @return Farit_Model_Pdf
     */

    public static function load($source = null, $revision = null)
    {
        return new Farit_Pdf($source, $revision, true);
    }

    /**
     * Create new PDF document from a $source string
     *
     * @param string $source
     * @param integer $revision
     * @return Farit_Model_Pdf
     */
    public static function parse(&$source = null, $revision = null)
    {
        return new Farit_Pdf($source, $revision);
    }


    /**
     * Renders the PDF document
     *
     * @throws Zend_Pdf_Exception
     */
    public function render()
    { 
	    //the file with root certificates
	    $rootCertificateFile = null;
	
	    $matches = array();
	    //render what we have for now
        $pdfDoc = parent::render();

        if (empty($this->_certificate)) {
            return $pdfDoc;
        }

	    //set the modification date
	    $this->properties['ModDate'] = $this->_currentTime;
	    
	    //look for the match line by line    
	    $pdfLines = explode("\n", $pdfDoc);
	    //find the ByteRange and Signature parts that were inserted when we attached the signature object
	    foreach ($pdfLines as $line) {
	        if (preg_match('/.*<<.+\/Sig.+\/Adobe.PPKLite.+\/ByteRange\s*\[(.+)\].+\/Contents\s*(<\d+>).*/', 
		        $line, $matches, PREG_OFFSET_CAPTURE) === 1) {
		        break;    
	        }
	    }

	    if (count($matches) < 3) {
	        throw new Zend_Pdf_Exception('No signature field match was found');    
	    }
		
	    //offset from the beginning of the document
	    $lineOffset = strpos($pdfDoc, $matches[0][0]);
	    //[0] - body and [1] - offset
	    $byteRangePart = $matches[1];
	    $signaturePart = $matches[2];
	
	    //offset where the signature starts
	    $signatureStartPosition = $lineOffset + $signaturePart[1];
	    //offset where the ByteRange starts
	    $byteRangeStartPosition = $lineOffset + $byteRangePart[1];
	
	    //offset where the signature ends
	    $signatureEndPosition = $signatureStartPosition + strlen($signaturePart[0]);
	    //position of the signature from the end of the PDF
	    $signatureFromDocEndPosition = strlen($pdfDoc) - $signatureEndPosition;
	    //cut out the signature part
	    $pdfDoc = substr($pdfDoc, 0, $signatureStartPosition) . substr($pdfDoc, $signatureEndPosition);
		
	    //replace the ByteRange with the positions of the signature
	    $byteRangeLength = strlen($byteRangePart[0]);
	    $calculatedByteRange = sprintf('0 %u %u %u', $signatureStartPosition, $signatureEndPosition, 
	    $signatureFromDocEndPosition);
	    //pad with spaces to put it in the same position
	    $calculatedByteRange .= str_repeat(' ', $byteRangeLength - strlen($calculatedByteRange));
	    //replace the original ByteRange with the calculated ByteRange
	    $pdfDoc = substr_replace($pdfDoc, $calculatedByteRange, $byteRangeStartPosition, $byteRangeLength);
		
	    //get the certificate info
	    if (!function_exists('openssl_pkcs12_read')) {
	        throw new Zend_Pdf_Exception('Please install the OpenSSL support for php');	
	    }

	    $certificateInfo = array();
	    $result = openssl_pkcs12_read($this->_certificate, $certificateInfo, $this->_certificatePassword);
	    if (!$result) {
	        throw new Zend_Pdf_Exception('Unable to open the digital certificate. Check the certificate password');	    
	    }
	
	    // write the document to a temporary folder
	    $tempDoc = tempnam(sys_get_temp_dir(), 'tmppdf');
	    $f = fopen($tempDoc, 'wb');
	    if (!$f) {
	        throw new Zend_Pdf_Exception('Unable to create temporary file: ' . $tempDoc);
	    }
	
	    $pdfDocLength = strlen($pdfDoc);
	    fwrite($f, $pdfDoc, $pdfDocLength);
	    fclose($f);
	
	    // get digital signature via openssl library
	    $tempSign = tempnam(sys_get_temp_dir(), 'tmpsig');
	    if (!function_exists('openssl_pkcs7_sign')) {
	        throw new Zend_Pdf_Exception('Please install the OpenSSL support for php');	
	    }
	
	    //create a file with extra root certificates
	    if (array_key_exists('extracerts', $certificateInfo) && (count($certificateInfo['extracerts']) > 0)) {
	        $rootCertificateFile = tempnam(sys_get_temp_dir(), 'tmproot');

	        file_put_contents($rootCertificateFile, implode("\n", $certificateInfo['extracerts']));
	    } 
	
	    if ($rootCertificateFile) {
	        $signResult = openssl_pkcs7_sign($tempDoc, $tempSign, $certificateInfo['cert'], array($certificateInfo['pkey'], 
	            $this->_certificatePassword), 
	            array(), PKCS7_BINARY | PKCS7_DETACHED, $rootCertificateFile);
	        unlink($rootCertificateFile);
	    }
	    else {
	        $signResult = openssl_pkcs7_sign($tempDoc, $tempSign, $certificateInfo['cert'], array($certificateInfo['pkey'], 
	            $this->_certificatePassword), 
	            array(), PKCS7_BINARY | PKCS7_DETACHED); 
	    }
	    if (!$signResult) {		    
	        unlink($tempDoc);   
	        throw new Zend_Pdf_Exception('Cannot sign with pkcs7');
	    }    
	    unlink($tempDoc);
		
	    // read signature
	    $signature = file_get_contents($tempSign);
	    if ($signature === false) {
	        unlink($tempSign);
	        throw new Zend_Pdf_Exception('Cannot read the pkcs7 signed document');
	    }	
	    unlink($tempSign);
	
	    // extract signature
	    $signature = substr($signature, $pdfDocLength);
	    $signature = substr($signature, (strpos($signature, "%%EOF\n\n------") + 13));
	
	    $tmpArr = explode("\n\n", $signature);
	    $signature = $tmpArr[1];
			
	    unset($tmpArr);
	
	    // decode signature
	    $signature = base64_decode(trim($signature));	
	
	    // convert signature to hex
	    $signature = current(unpack('H*', $signature));
	    $signature = str_pad($signature, self::SIGNATURE_MAX_LENGTH, '0');
	
	    // Add signature to the document
	    $pdfDoc = substr($pdfDoc, 0, $signatureStartPosition) 
	        . '<' . $signature . '>' . substr($pdfDoc, $signatureStartPosition);
			 		 
	    return $pdfDoc;     
    }

        
}