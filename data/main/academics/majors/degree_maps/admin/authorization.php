<?php
	// Load the settings from the central config file
	require_once '/data/www/config/phpCAS/config.php';
	// Load the CAS lib
	require_once $phpcas_path . '/CAS.php';

	// Enable debugging
	phpCAS::setLogger();
	// Enable verbose error messages. Disable in production!
	phpCAS::setVerbose(true);

	// Initialize phpCAS
	phpCAS::client(SAML_VERSION_1_1, $cas_host, $cas_port, $cas_context);

	// For production use set the CA certificate that is the issuer of the cert
	// on the CAS server and uncomment the line below
	// phpCAS::setCasServerCACert($cas_server_ca_cert_path);

	// For quick testing you can disable SSL validation of the CAS server.
	// THIS SETTING IS NOT RECOMMENDED FOR PRODUCTION.
	// VALIDATING THE CAS SERVER IS CRUCIAL TO THE SECURITY OF THE CAS PROTOCOL!
	phpCAS::setNoCasServerValidation();

	// logout if desired
	if (isset($_REQUEST['logout'])) {
		phpCAS::logoutWithUrl(CalRoot);
	}

	// force CAS authentication
	phpCAS::forceAuthentication();
    $attributes = phpCAS::getAttributes();

///******* end from PHP CAS example ****///
    
	if (empty($attributes['sAMAccountName'])){
		//no cas? get out.
        http_response_code(403);
        echo json_encode(array("error" => "Access denied"));
	}
    
     /*
the user's Attributes are 
array(16) {
	["commonName"]=> string(12) "Mike Marlett"
	["isFromNewLogin"]=> string(4) "true"
	["mail"]=> string(24) "mike.marlett@wichita.edu"
	["authenticationDate"]=> string(27) "2021-11-21T00:09:57.042279Z"
	["bypassMultifactorAuthentication"]=> string(5) "false"
	["sAMAccountName"]=> string(8) "q262t958"
	["authnContextClass"]=> string(7) "mfa-duo"
	["displayName"]=> string(13) "Marlett, Mike"
	["givenName"]=> string(4) "Mike"
	["successfulAuthenticationHandlers"]=> string(7) "mfa-duo"
	["credentialType"]=> string(21) "DuoSecurityCredential"
	["samlAuthenticationStatementAuthMethod"]=> string(42) "urn:oasis:names:tc:SAML:1.0:am:unspecified"
	["UDC_IDENTIFIER"]=> string(8) "q262t958"
	["authenticationMethod"]=> string(7) "mfa-duo"
	["longTermAuthenticationRequestTokenUsed"]=> string(5) "false"
	["sn"]=> string(7) "Marlett"
}
     */ 
