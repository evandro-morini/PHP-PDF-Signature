Zend_Pdf from Zend Framework can't sign PDF documents with a digital certificate.

This class extends Zend_Pdf to provide this capability.

The script test_sertificate.php shows how to use the class.
The sample certificate and document are provided.

Limitations:
1. Currently the only type of certificates supported are in the PKCS12 format.
   You can get a sample certificate from http://www.cacert.org
   and then backup it by going into the settings of your browser (presumably, Firefox).
   While the backup, your browser will ask for the password.
   
2. Documents with existing AcroForm elements are not supported (already certified documents,
   documents with forms and JavaScript). These elements are not supported by Zend_Pdf.
   
3. Most digital certificates are recognized by Adobe Reader as 'unknown', and a user who
   opens it should add the certificate into trusted.
   The best certificate would be the one that has Adobe CA as its root certificate authority,
   then it would open as trusted for all users. However, all these certificates use 
   a USB device to store the certificate info and they are expensive. 
   
         
   