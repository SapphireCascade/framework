<?php
	$version_list = sqlGetAll("versions");
	foreach($version_list as $version){
		sqlUpdate("versions",array("id"=>$version["id"]),array("version"=>$version["version"]+1));
	}
	header("Location: /");
	exit;
?>