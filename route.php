<?php
	try {
		$data=array("request"=>array("get"=>$_GET,"post"=>$_POST,"uri"=>array("url"=>$_SERVER["REQUEST_URI"],"query"=>$_SERVER["QUERY_STRING"])));
		include "settings.php";
		include "global.php";
		$html="";
		$query_pos= strpos($_SERVER["REQUEST_URI"],"?");
		if($query_pos){
			$location = substr($_SERVER["REQUEST_URI"],0,$query_pos);
			if($location=="/"){
				$location = "/index";
			}
		} elseif($_SERVER["REQUEST_URI"]=="/"){
			$location = "/index";
		} else {
			$location = substr($_SERVER["REQUEST_URI"],0,strlen($_SERVER["REQUEST_URI"]));
		}
		$redirects = sqlGetAll("redirects");
		
		foreach($redirects as $redirect){
			preg_match($redirect["url"],$location,$matches);
			if(!$matches){
				continue;
			}
			unset($matches[0]);
			$id=0;
			preg_match("/\?([\s\S]+)$/",$redirect["redirect"],$query_string);
			$query_tmp = explode("&",$query_string[1]);
			$query = array();
			foreach($query_tmp as $query_string){
				$tmp_array = explode("=",$query_string);
				$query[$tmp_array[0]] = $tmp_array[1];
			}
			foreach($query as $item=>$index){
				preg_match("/{([\d]+)}/",$index,$index_id);
				$index_id = $index_id[1];
				$_GET[$item] = $matches[$index_id];
			}
			$location = substr($redirect["redirect"], 0,strpos($redirect["redirect"],"?"));
		}
		$file = "handlers".$location.".php";
		$view = "view".$location.".coch";

		$data["setup"]=array("handler"=>$file,"view"=>$view);
		$data["user"]=getLoggedInUser();
		$content = array();
		$data["content"]=$content;
		if(file_exists($file)||isset($render_view)||file_exists($view)){
			if(file_exists($file)){
				include $file;
			}
			if(isset($render_view)||file_exists($view)) {
				$data["content"]=$content;
				if(getHtmlLayout()){
					$csrf_token = generateRandomString(50);
					$_SESSION["csrf_token"] = $csrf_token;
					$data["setup"]["csrf_token"] = $csrf_token;
					$html.=display("head.coch",$data);
					if($data["user"]){
						include "handlers/friend_list.php";
						$data["content"]=$content;
						$html.=display("friend_list.coch",$data);
					}
				}
				if(isset($render_view)){
					$html.=display($render_view,$data);
				} elseif(file_exists($view)){
					$html.=display($view,$data);
				}
				if(getHtmlLayout()){
					$html.=display("foot.coch");
				}
			}
		} else {
			$file = "services".$location.".php";
			$data["setup"]["handler"] = $file;
			if(file_exists($file)){
				if(checkCsrfToken()||$location=="/verify_email_code"){
					include $file;
				} else {
					echo "<h1>CSRF TOKEN ERROR</h1>";
				}
				exit;
			} elseif($file = file_exists("scripts".$location.".php")&&isSelf()){
				include "scripts".$location.".php";
				exit;
			} else {
				echo "Error 404";
				exit;
			}
		}
		if(isset($_GET["coch"])&&$_GET["coch"]!="false"&&isSelf()){
			dump($data);
			exit;
		}
		if(isset($_REQUEST["preload"])&&$_REQUEST["preload"]){
			if(!isset($_SESSION["preload_url_list"])){
				$_SESSION["preload_url_list"] = array();
			}
			if(!isset($_SESSION["preload_url_list"][$_SERVER["REQUEST_URI"]])){
				$_SESSION["preload_url_list"][$_SERVER["REQUEST_URI"]] = $html;
			}
		} else {
			echo $html;
		}
		// echo $html;
	} catch(Throwable $e){
		if(isSelf()){
			echo "<h1>Error</h1><br/>".$e->getMessage()." at ".$e->getFile()." on Line: ".$e->getLine();
		} else {
			$message = $e->getMessage()." at ".$e->getFile()." on Line: ".$e->getLine();
			$message = str_replace("'","\'",$message);
			$previous = sqlGetBy("error_log",array("message"=>str_replace("'","\'",$message)));
			if(count($previous)){
				$error_code = $previous[0]["error_code"];
			} else {
				$error_code = generateRandomString(30);
				sqlInsert("error_log",array("error_code"=>$error_code,"message"=>$message));
			}
			echo "<h1>An error has occured</h1><br/><h2>Please contact ".$_GLOBALS["ERROR_NAME"]." using error code: ".$error_code." for assistance</h2>";
		}
		exit;
	}
?>