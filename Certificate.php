<?php

/**
 * The class to insert certificates into PDFs
 */
class Notarynow_Model_Pdf_Certificate
{
    protected $logger;
    protected $conf;

    protected $certificate;
    protected $name;
    protected $location;
    protected $reason;
    protected $contactInfo;
    
    //errors
    protected $error;
    
    public function __construct()
    {
	    $this->logger = Zend_Registry::get('logger');
	    $this->conf = Zend_Registry::get('conf');
		
	    $this->error = new Notarynow_Model_Error();

    }	    
    
    public function setError(Notarynow_Model_Error $error)
    {
	    $this->error = $error;
	    return $this;
    }
    
    public function getError()
    {
	    return $this->error;
    }

    public function setCertificate(Notarynow_Model_NotaryCertificate $certificate)
    {
	    $this->certificate = $certificate;
	    return $this;
    }
    
    public function getCertificate()
    {
	    return $this->certificate;
    }


    public function setName($name)
    {
	    $this->name = (string)$name;
	    return $this;
    }
    
    public function getName()
    {
	    return $this->name;
    }

    public function setLocation($location)
    {
	    $this->location = (string)$location;
	    return $this;
    }
    
    public function getLocation()
    {
	    return $this->location;
    }

    public function setReason($reason)
    {
	    $this->reason = (string)$reason;
	    return $this;
    }
    
    public function getReason()
    {
	    return $this->reason;
    }
    
    public function setContactInfo($contactInfo)
    {
	    $this->contactInfo = (string)$contactInfo;
	    return $this;
    }
    
    public function getContactInfo()
    {
	    return $this->contactInfo;
    }

     
}
    