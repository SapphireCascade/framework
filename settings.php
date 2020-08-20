<?php
	function setup(){
		settings("MAIN_SITE_URL","");
		settings("REQUIRE_LOGIN",false);// will redirect you from any page to LOGIN_REDIRECT if user isn't logged in
		settings("LOGIN_REDIRECT","");// will redirect you to this page if user isn't logged in
		settings("LOGIN_NOT_REQUIRED",array(""));// these pages won't redirect you if you aren't logged in
		settings("IS_SELF_KEY", getenv("IS_SELF"));//create a cookie called is_self and store a random string, create an environment variable with the same string and pass this environment variable through this function
		settings("CACHELESS_URL", "");// can be as a string or an array of strings. Javascript and CSS won't cache on these sites (development purposes only)
		settings("DB_SERVER_NAME", "");
		settings("DB_USERNAME", "");
		settings("DB_PASSWORD", getenv(""));
		settings("DB_DBNAME", "");
		settings("EMAIL_HOST", "");
		settings("EMAIL_USERNAME", getenv(""));
		settings("EMAIL_PASSWORD", getenv(""));
		settings("EMAIL_ADDRESS_FROM", "");
		settings("EMAIL_NAME_FROM", "");
		settings("ERROR_NAME", "");// Who to contact when the user sees an error
	}
?>