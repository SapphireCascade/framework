<?php
	//securityFlaw();
	$GLOBALS["settings"] = array();
	speed();
	session_start();
	require_once 'vendor/PHPMailer/src/PHPMailer.php';
	require_once 'vendor/PHPMailer/src/Exception.php';
	require_once 'vendor/PHPMailer/src/SMTP.php';
	// require_once 'vendor/Mailgun/src/Mailgun.php';

	// Import PHPMailer classes into the global namespace
	// These must be at the top of your script, not inside a function
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;
	// use Mailgun\Mailgun;
	$gethtml=true;
	setup();
	if($_SERVER["SERVER_NAME"]!=settings("MAIN_SITE_URL")){
		if(!isSelf()){
			header("Location: http://".settings("MAIN_SITE_URL"));
		}
	}
	// if($_SERVER["REQUEST_URI"]!="/"&&!isset($_COOKIE["login_id"])&&$_SERVER["REQUEST_URI"]!="/tandc"&&$_SERVER["REQUEST_URI"]!="/login/login"&&$_SERVER["REQUEST_URI"]!="/signup/signup"){
	// 	if(!isset($_SESSION["return_url"])||!$_SESSION["return_url"]){
	// 		$_SESSION["return_url"] = $_SERVER["REQUEST_URI"];
	// 	}
	// 	header("Location: /");
	// }
	function settings($name="",$value=false){
		if($name===""){
			return $GLOBALS["settings"];
		} elseif($value!==false){
			$GLOBALS["settings"][$name] = $value;
		} else {
			if(!isset($GLOBALS["settings"][$name])){
				echo "Settings value not set: ".$name;
				exit;
			}
			return $GLOBALS["settings"][$name];
		}
	}
	function isSelf(){
		return $_COOKIE["is_self"]==settings("IS_SELF_KEY");
	}

	function securityFlaw(){
		echo display("securityFlaw.html");
		exit;
	}

	function checkCsrfToken(){
		if($_REQUEST["csrf_token"] === $_SESSION["csrf_token"]){
			// $_SESSION["csrf_token"] = generateRandomString(50);
			return true;
		} else {
			return false;
		}
	}

	function encrypt($message,$key){
		$key_bin = "";
		for($i=0;$i<strlen($key);$i++){
			$key_bin.= decbin(ord($key[$i]));
		}
		$message_bin = "";
		for($i=0;$i<strlen($message);$i++){
			$num = decbin(ord($message[$i]));
			while(strlen($num)!=8){
				$num="0".$num;
			}
			$message_bin.= $num;
		}
		$key_length = strlen($key_bin);
		$message_length = strlen($message_bin);
		$encrypted_bin = "";
		for($i=0;$i<$message_length;$i++){
			if((int)$key_bin[$i%$key_length] xor (int)$message_bin[$i]){
				$encrypted_bin .= "1";
			} else {
				$encrypted_bin .= "0";
			}
		}
		$encrypted="";
		$binary = "";
		$encrypted_bin_length = strlen($encrypted_bin);
		for($i=0;$i<$encrypted_bin_length;$i++){
			$binary.=$encrypted_bin[$i];
			if($i%8 == 7){
				$encrypted .= bindec($binary)." ";
				$binary = "";
			}
		}
		return $encrypted;
	}
	function decrypt($message,$key){
		$key_bin = "";
		for($i=0;$i<strlen($key);$i++){
			$key_bin.=decbin(ord($key[$i]));
		}
		$message_bin = "";
		$message = trim($message);
		$message_array = explode(" ",$message);
		$message_bin = "";
		foreach($message_array as $dec){
			$num = decbin($dec);
			while(strlen($num)!=8){
				$num="0".$num;
			}
			$message_bin .= $num;
		}
		$key_length = strlen($key_bin);
		$decrypted_bin = "";
		$message_length = strlen($message_bin);
		for($i=0;$i<$message_length;$i++){
			if((int)$key_bin[$i%$key_length] xor (int)$message_bin[$i]){
				$decrypted_bin .= "1";
			} else {
				$decrypted_bin .= "0";
			}
		}
		$decrypted="";
		$binary = "";
		$decrypted_bin_length = strlen($decrypted_bin);
		for($i=0;$i<$decrypted_bin_length;$i++){
			$binary.=$decrypted_bin[$i];
			if($i%8 == 7){
				$decrypted .= chr(bindec($binary));
				$binary = "";
			}
		}
		return $decrypted;
	}

	function speed($message,$accuracy=2){
		if(isset($GLOBALS["time"])){
			$difference = round(microtime(true)-$GLOBALS["time"],$accuracy);
			echo $message . " - " .$difference."s<br>";
		}
		$GLOBALS["time"] = microtime(true);
	}

	function emptyHtml($value = true){
		$GLOBALS["gethtml"]=!$value;
	}
	function getHtmlLayout(){
		return $GLOBALS["gethtml"];
	}
	function display($display,&$data=array(),$coch=false){
		if(substr($display,-4,4)=="coch"&&file_exists("view/".$display)){
			$page = fopen("view/".$display,"r");
			$page = fread($page,filesize("view/".$display));
		} elseif(substr($display,-3,3)=="php"&&file_exists("services/".$display)){
			$page = fopen("services/".$display,"r");
			$page = fread($page,filesize("services/".$display));
		} elseif($coch){
			$page = $display;
		} else {
			$page = fopen($display,"r") or die("Error displaying ".$display);
			$page = fread($page,filesize($display));
		}

		$page = preg_replace("/{#[\s\S]*?#}/","",$page);

		$item_list = array();
		$debug=0;
		preg_match_all("/{%[ ]*?set[\s\S]+?=[\s\S]+?%}|{%[ ]*?set[\s\S]+?%}[\s\S]*?{%[ ]*?endset[ ]*?%}/", $page, $set_list, PREG_OFFSET_CAPTURE);
		foreach($set_list[0] as $settag){
			$debug=1;
			$item_list[$settag[1]] = array($settag[0],"set");
		}

		preg_match_all("/{%[ ]*for[\s\S]+?%}|{%[ ]*endfor(?:each)?[ ]*%}/", $page, $for_list, PREG_OFFSET_CAPTURE);
		$for_tag_index=0;
		$for_tag_start=0;
		$for_range = array();
		foreach($for_list[0] as $fortag){
			if($for_tag_index==0){
				$for_tag_start = $fortag[1];
			}
			if(preg_match("/{%[ ]*endfor[ ]*%}/",$fortag[0])){
				$for_tag_index--;
			} else {
				$for_tag_index++;
			}
			if($for_tag_index==0){
				$for_range[] = array($for_tag_start,$fortag[1]+strlen($fortag[0]));
				$for_tag_start=0;
			}

		}
		foreach($for_range as $for_position){
			$item_list[$for_position[0]] = array(substr($page,$for_position[0],$for_position[1]-$for_position[0]),"for");
		}


		preg_match_all("/{%[ ]*if[\s\S]+?%}|{%[ ]*endif[ ]*%}/", $page, $if_list, PREG_OFFSET_CAPTURE);
		$if_tag_index=0;
		$if_tag_start=0;
		$if_range=array();
		foreach($if_list[0] as $iftag){
			if($if_tag_index==0){
				$if_tag_start = $iftag[1];
			}
			if(preg_match("/{%[ ]*?endif[ ]*?/",$iftag[0])){
				$if_tag_index--;
			} else {
				$if_tag_index++;
			}
			if($if_tag_index==0){
				$if_range[] = array($if_tag_start,$iftag[1]+strlen($iftag[0]));
				$if_tag_start=0;
			}
		}
		foreach($if_range as $if_position){
			$item_list[$if_position[0]] = array(substr($page,$if_position[0],$if_position[1]-$if_position[0]),"if");
		}

		preg_match_all("/{{[ ]*?[\s\S]+?[ ]*?}}/", $page, $output,PREG_OFFSET_CAPTURE);
		foreach($output[0] as $item){
			$item_list[$item[1]] = array($item[0],"output");
		}

		preg_match_all("/{%[ ]*include[ ]*[\s\S]+?[ ]*%}/",$page,$includes,PREG_OFFSET_CAPTURE);
		foreach($includes[0] as $include){
			$item_list[$include[1]] = array($include[0],"include");
		}


		ksort($item_list);
		foreach($item_list as $position=>$item){
			foreach($item_list as $pos=>$val){
				if($pos<($position+strlen($item[0]))&&$pos>$position){
					unset($item_list[$pos]);
				}
			}
		}
		foreach($item_list as $position=>$item){
			switch($item[1]){
				case "set":
					$set_tag = $item[0];
					if(preg_match("/{%[ ]*set[ ]*([\s\S]*?)[ ]*=([\s\S]*?)%}/",$set_tag,$match)){
						$var_name = $match[1];
						$value = evaluate(cochToPhp($match[2]),$data,null,true);
						$data[$var_name] = $value;
						$page = preg_replace(strToRegex($item[0]),"",$page,1);
					} else {
						preg_match("/{%[ ]*set[ ]*([\s\S]*?)[ ]*?%}([\s\S]*?){%[ ]*endset[ ]*%}/",$set_tag,$match);
						$var_name = $match[1];
						$value = display($match[2],$data,true);
						$data[$var_name] = $value;
						$page = preg_replace(strToRegex($item[0]), "", $page,1);
					}
					break;
				case "if":
					$if_statement = $item[0];
					preg_match_all("/{%[ ]*(?:if[\s\S]+?|else[\s\S]*?|endif)[ ]*%}/",$if_statement,$if_structure,PREG_OFFSET_CAPTURE);
					$index =0;
					$if_offsets=array();
					foreach($if_structure[0] as $if_part){
						if(preg_match("/{%[ ]*if[\s\S]+?%}/",$if_part[0])){
							if($index==0){
								$if_offsets[] = $if_part[1];
							}
							$index++;
						} elseif(preg_match("/{%[ ]*else[\s\S]+?%}/",$if_part[0])){
							$index--;
							if($index==0){
								$if_offsets[] = $if_part[1];
							}
							$index++;
						} elseif(preg_match("/{%[ ]*endif[ ]*%}/",$if_part[0])){
							$index--;
							if($index==0){
								$if_offsets[] = $if_part[1];
							}
						}
					}
					$index=1;
					$if_structure = array();
					foreach($if_offsets as $if_offset){
						$if_structure[] = substr($if_statement,$if_offset,$if_offsets[$index]-$if_offset);
						$index++;
					}
					foreach($if_structure as $statement){
						if(!$statement){
							$page = preg_replace(strToRegex($if_statement),"",$page,1);
						}
						if(preg_match("/^{%[ ]*else[ ]*%}/",$statement,$head)){
							$body = str_replace($head[0],"",$statement);
							$page = preg_replace(strToRegex($if_statement),display($body,$data,true),$page,1);
							break;
						} elseif(preg_match("/^{%[\s\S]+?if[ ]+?([\s\S]+?)[ ]*%}/",$statement,$head)){
							$body = str_replace($head[0],"",$statement);
							$conjunction = $head[1];
							cochToPhp($conjunction);
							if(evaluate($conjunction,$data)){
								$page = preg_replace(strToRegex($if_statement),display($body,$data,true),$page,1);
							}
						}
					}
					break;
				case "for":
					$for_loop = $item[0];
					preg_match("/^{%[ ]*for[\s\S]+?%}/",$for_loop,$loop_head);
					preg_match("/{%[ ]*endfor[ ]*%}$/",$for_loop,$loop_tail,PREG_OFFSET_CAPTURE);
					$loop_head = $loop_head[0];
					$loop_body = substr($for_loop,strlen($loop_head),$loop_tail[0][1]-strlen($loop_head));
					$phy_var = "";
					$vir_var = "";
					if(preg_match("/ ([^ ]+) as ([^ ]+)(?:[ ]+if[ ]+?([\s\S]+?)[ ]*%}$)?/",$loop_head,$variables)){
						$index=0;
						$vir_var = $variables[2];
						$phy_var = $data;
						while($index<strlen($variables[1])){
							if($variables[1][$index]=="."){
								$phy_var=$phy_var[$target];
								$target="";
							} else {
								$target.=$variables[1][$index];
							}
							$index++;
						}
						$phy_var = $phy_var[$target];
					} elseif(preg_match("/ ([^ ]+) in ([^ ]+)(?:[ ]+if[ ]+?([\s\S]+?)[ ]*%}$)?/",$loop_head,$variables)){
						$index = 0;
						$target="";
						$vir_var = $variables[1];
						$phy_var = $data;
						while($index<strlen($variables[2])){
							if($variables[2][$index]=="."){
								$phy_var=$phy_var[$target];
								$target="";
							} else {
								$target.=$variables[2][$index];
							}
							$index++;
						}
						$phy_var = $phy_var[$target];
					}
					$for_loop_finish="";
					$requirement = cochToPhp($variables[3],"parse_data")?:"true";
					$data["loop"]["index"] = 1;
					$data["loop"]["index0"] = 0;
					$data["loop"]["length"] = count($phy_var);
					if($requirement=="true"){
						$data["loop"]["last"] = false;
					}
					foreach($phy_var as $parse){
						$parse_data = $data;
						$parse_data[$vir_var]=$parse;
						if(evaluate($requirement,$data)){
							$data["loop"]["index"]++;
							$data["loop"]["index0"]++;
							if($requirement=="true" && $data["loop"]["length"] == $data["loop"]["index"]){
								$data["loop"]["last"] = true;
							}
							$for_loop_finish .= display($loop_body,$parse_data,true);
						}
					}
					unset($data["loop"]);
					$page = preg_replace(strToRegex($for_loop),$for_loop_finish,$page,1);
					break;
				case "output":
					$match = $item[0];
					if(preg_match("/js[ ]*?\([ ]*?\"([\s\S]+?)\"[ ]*?\)|js[ ]*?\([ ]*?\'([\s\S]+?)\'[ ]*?\)/",$match,$new)){
						preg_match("/[\s\S]*\//",$display,$route);
						$js_path = $route[0].$new[1];
						if(!file_exists($js_path)){
							$js_path = $new[1];
						}
						if($_SERVER["SERVER_NAME"]==settings("CACHELESS_URL")||in_array($_SERVER["SERVER_NAME"], settings("CACHELESS_URL"))){
							$version=rand();
						} else {
							$version = sqlGetBy("versions",array("type"=>"js"))[0]["version"];
						}
						$value = "<script src='/".$js_path."?v=".$version."'></script>";
						$page = preg_replace(strToRegex($match), $value, $page,1);
					} elseif(preg_match("/css[ ]*?\([ ]*?\"([\s\S]+?)\"[ ]*?\)|css[ ]*?\([ ]*?\'([\s\S]+?)\'[ ]*?\)/",$match,$new)){
						preg_match("/[\s\S]*\//",$display,$route);
						$css_path = $route[0].$new[1];
						if($_SERVER["SERVER_NAME"]==settings("CACHELESS_URL")||in_array($_SERVER["SERVER_NAME"], settings("CACHELESS_URL"))){
							$version=rand();
						} else {
							$version = sqlGetBy("versions",array("type"=>"css"))[0]["version"];
						}
						$value = "<link rel='stylesheet' href='/".$css_path."?v=".$version."'/>";
						$page = preg_replace(strToRegex($match), $value, $page,1);
					} else {
						$variables = $match;
						$variables = preg_replace("/(?:^{{[ ]*)|(?:[ ]*}}$)/","",$variables);
						cochToPhp($variables);
						$result = evaluate("$variables",$data);
						$result = substr($result,1,-1);
						$page = preg_replace(strToRegex($match),$result,$page,1);
					}
					break;
				case "include":
					$match = $item[0];
					preg_match("/{%[ ]*include[ ]*[\'\"]([\s\S]+?)[\'\"][ ]*%}/",$match,$include_data);
					$page = preg_replace(strToRegex($match),display($include_data[1],$data),$page,1);
			}
		}
		if(preg_match_all("/<form[\s\S]*?>/", $page,$form_element_list)){
			foreach($form_element_list[0] as $form_element){
				$page = str_replace($form_element, $form_element."<input type='hidden' name='csrf_token' value='".$_SESSION["csrf_token"]."'>", $page);
			}
		}
		return $page;
	}
	function generateSalt(){
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for($i = 0;$i<22;$i++){
			$randomString .= $characters[rand(0,$charactersLength-1)];
		}
		return '$2a$07$'.$randomString.'$';
	}
	function generateRandomString($length = 10) {
	    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';
	    $charactersLength = strlen($characters);
	    $randomString = '';
	    for ($i = 0; $i < $length; $i++) {
	        $randomString .= $characters[rand(0, $charactersLength - 1)];
	    }
	    return $randomString;
	}
	function cochToPhp(&$conjunction,$main_name="data"){
		// $variables=$conjunction;
		// while(preg_match("/(?:\"[\s\S]*?[^\\\\]\")|(?:\'[\s\S]*?[^\\\\]\')/",$variables,$string)){
		// 	$variables = str_replace($string[0],"",$variables);
		// }
		// preg_match_all("/[a-zA-Z][a-zA-Z_\.0-9\-]*(?=(?:[^\(a-zA-Z_\.0-9\-]|$))/",$variables,$variable_list);
		// foreach($variable_list[0] as $variable){
		// 	$conjunction = str_replace($variable,"\$".$main_name."[\"".str_replace(".","\"][\"",$variable)."\"]",$conjunction);
		// }
		// $conjunction = str_replace("~",".",$conjunction);
		$count = 0;
		$string_list = array();
		while(preg_match("/(?:\"[\s\S]*?[^\\\\]\")|(?:\'[\s\S]*?[^\\\\]\')/",$conjunction,$string)){
			$conjunction = str_replace($string[0],"\\".$count."\\",$conjunction);
			$count+=2;
			$string_list[] = $string[0];
		}
		$count=1;
		$variable_list = array();
		while(preg_match("/[a-z][a-zA-Z_\.0-9]*(?=(?:[^\(a-zA-Z_\.0-9]|$))/",$conjunction,$variable)){
			$conjunction = str_replace($variable[0],"\\".$count."\\",$conjunction);
			$count+=2;
			$variable_list[] = $variable[0];
		}
		$count = 1;
		foreach($variable_list as $index=>$variable){
			$conjunction = str_replace("\\".$count."\\","$".$main_name."[\"".str_replace(".","\"][\"",$variable)."\"]",$conjunction);
			$count+=2;
		}
		$count = 0;
		foreach($string_list as $string){
			$conjunction = str_replace("\\".$count."\\",$string,$conjunction);
			$count+=2;
		}
		return $conjunction;
	}
	function evaluate($expression,$data,$string_list=array(),$return_array=false){
		$count = 0;
		while(preg_match("/(?:\"[\s\S]*?[^\\\\]\")|(?:\'[\s\S]*?[^\\\\]\')/",$expression,$string)){
			$expression = str_replace($string[0],"\\".$count."\\",$expression);
			$count++;
			$string_list[] = $string[0];
		}
		$item_list = array();
		preg_match_all("/\(|\)/",$expression,$bracket_list, PREG_OFFSET_CAPTURE);
		$bracket_index = 0;
		$bracket_start=0;
		$bracket_range = array();
		foreach($bracket_list[0] as $bracket){
			if($bracket_index==0){
				$bracket_start = $bracket[1];
			}
			if(preg_match("/\)/", $bracket[0])){
				$bracket_index--;
			} else {
				$bracket_index++;
			}
			if($bracket_index==0){
				$bracket_range[] = array($bracket_start,$bracket[1]+strlen($bracket[0]));
				$bracket_start = 0;
			}
		}
		$string_difference = 0;
		foreach($bracket_range as $bracket_position){
			$search = substr($expression,$bracket_position[0]-$string_difference,$bracket_position[1]-$bracket_position[0]);
			$replace = evaluate(substr($search,1,-1),$data,$string_list);
			$string_difference+=strlen($search)-strlen($replace);
			$expression = str_replace($search,$replace,$expression);
		}
		preg_match_all("/\\\\([\d]+)\\\\/", $expression,$string_identity_list);
		foreach($string_identity_list[0] as $index=>$search){
			$id = $string_identity_list[1][$index];
			$replace = $string_list[$id];
			$expression = str_replace($search,$replace,$expression);
		}
		$end_string_list = array();
		preg_match_all("/\\$[a-zA-Z][a-zA-Z0-9_]*(?:\[(?:[\d]+|'[a-zA-Z0-9_]*'|\"[a-zA-Z0-9_]*\")\])*/",$expression,$variable_list);
		foreach($variable_list[0] as $variable){
			preg_match_all("/(?:\[([\d]+|'[^']*'|\"[^\"]*\")\])/",$variable,$names_list);
			$variable_value = $data;
			foreach($names_list[1] as $name){
				if(preg_match("/'([a-zA-Z0-9_]*)'|\"([a-zA-Z0-9_]*)\"/",$name,$actual_name)){
					if($actual_name[1]){
						$variable_value = $variable_value["".$actual_name[1]];
					} elseif($actual_name[2]){
						$variable_value = $variable_value["".$actual_name[2]];
					}
				} else {
					$variable_value = $variable_value[(int)$name];
				}
			}
			if(is_string($variable_value)){
				$end_string_list[] = $variable_value;
				$variable_value="\\".(count($end_string_list)-1)."\\";
			}
			if(is_array($variable_value)){
				if($return_array){
					return $variable_value;
				}
				if(count($variable_value)){
					$variable_value = "true";
				} else {
					$variable_value = "false";
				}
			}
			$expression = str_replace($variable,$variable_value,$expression);
		}
		while(preg_match("/([^=]+?)([!=]=)([^=]+)$/",$expression,$equation)){
			if($equation[2]=="!="){
				$expression = str_replace($equation[0],evaluate($equation[1])!=evaluate($equation[3]),$expression);
			} elseif($equation[2]=="==") {
				$expression = str_replace($equation[0],evaluate($equation[1])==evaluate($equation[3]),$expression);
			}
		}
		while(preg_match("/([^\-+]+?)([\-+])([^\-+]+)$/",$expression,$equation)){
			if($equation[2]=="-"){
				$expression = str_replace($equation[0],evaluate($equation[1])-evaluate($equation[3]),$expression);
			} elseif($equation[2]=="+") {
				$expression = str_replace($equation[0],evaluate($equation[1])+evaluate($equation[3]),$expression);
			}
		}
		while(preg_match("/([^\/*]+?)([\/*])([^\/*]+)$/",$expression,$equation)){
			if($equation[2]=="/"){
				$expression = str_replace($equation[0],evaluate($equation[1])/evaluate($equation[3]),$expression);
			} elseif($equation[2]=="*") {
				$expression = str_replace($equation[0],evaluate($equation[1])*evaluate($equation[3]),$expression);
			}
		}
		while(preg_match("/([^~]+?)([~])([~]+)$/",$expression,$equation)){
			$expression = str_replace($equation[0],evaluate($equation[1]).evaluate($equation[3]),$expression);
		}
		if(count($end_string_list)){
			preg_match_all("/\\\\([\d]+)\\\\/", $expression,$end_string_identity_list);
			foreach($end_string_identity_list[0] as $index=>$search){
				$id = $end_string_identity_list[1][$index];
				$replace = $end_string_list[$id];
				$expression = "'".str_replace($search,$replace,$expression)."'";
			}
		}
		if($expression == "false"){
			return false;
		} elseif($expression == "true"){
			return true;
		}
		return $expression;
	}
	function strToRegex($expression){
		$expression = str_replace(".","\\.",$expression);
		$expression = str_replace("/","\\/",$expression);
		$expression = str_replace("(","\(",$expression);
		$expression = str_replace(")","\)",$expression);
		$expression = str_replace("\"","\\\"",$expression);
		$expression = str_replace("?", "\\?", $expression);
		$expression = str_replace("*","\\*",$expression);
		$expression = str_replace("[","\\[",$expression);
		$expression = str_replace("]","\\]",$expression);
		$expression = str_replace("|","\\|",$expression);
		$expression ="/".$expression."/";
		return $expression;
	}
	function getLoggedInUser($refresh=false){
		if($_SERVER["REQUEST_URI"]=="/login/login"){
			return array();
		}
		if(isset($_COOKIE["login_id"])) {
			$salt = $_COOKIE["salt"];
			$login_id = $_COOKIE["login_id"];
			$current_login = sqlGetBy("logins",array("loginid"=>crypt($login_id,$salt)))[0];
			if($current_login){
				$user = $current_login["users"];
				$user["encryption_key"] = $current_login["encryption_key"];
				$_SESSION["user"] = $user;
				if(isset($_SESSION["return_url"])&&$_SESSION["return_url"]){
					$return_url = $_SESSION["return_url"];
					unset($_SESSION["return_url"]);
					header("Location: ".$return_url);
					exit;
				}
			} else {
				if(!isset($_SESSION["return_url"])||!$_SESSION["return_url"]){
					$_SESSION["return_url"] = $_SERVER["REQUEST_URI"];
				}
				return array();
			}
		} else {
			return array();
		}
		if($user["verified_email"]=="N"){
			if(!preg_match("/\/verify_email/",$_SERVER["REQUEST_URI"])&&!preg_match("/\/favicon\.ico/",$_SERVER["REQUEST_URI"])){
				header("Location: /verify_email?id=".$user["loginid"]);
				exit;
			}
		}
		return $user;
	}
	$indent=0;
	function repeat($item,$val,$mul=1){
		$passer="";
		$val*=$mul;
		while($val>0){
			$passer.=$item;
			$val--;
		}
		return $passer;
	}
	function dump($data, $echo=true){
		if($echo){
			$info = debug_backtrace()[0];
			$file_name = $info["file"];
			$line = $info["line"];
			echo $file_name." - ".$line."<br/>";
		}
		$output="";
		if(is_array($data)){
			$output.=repeat("&nbsp;",$GLOBALS["indent"],3) . "<b>array</b> <i>(size=".count($data).")</i><br>";
			$GLOBALS["indent"]++;
			foreach($data as $key=>$val){
				$output.=repeat("&nbsp;",$GLOBALS["indent"],3);
				if(is_string($key)){
					$output.="'".$key."'";
				} else {
					$output.=$key;
				}
				$output.=" => ";
				if(is_array($val)){
					$output.="<br>";
					$GLOBALS["indent"]++;
					$output.=dump($val,false);
					$GLOBALS["indent"]--;
				} else {
					$output.=dump($val,false);
				}
			}
			$GLOBALS["indent"]-=1;
		} elseif(is_string($data)){
			$data = str_replace(">","&gt;",$data);
			$data = str_replace("<","&lt;",$data);
			$output.="<small>string</small> <span style='color:red'>'".$data."'</span> <i>(length=".strlen($data).")</i><br>";
		} elseif(is_int($data)){
			$output.="<small>integer</small> <span style='color:green'>".$data."</span><br>";
		} elseif(is_bool($data)){
			if($data){
				$bool = "true";
			} else {
				$bool = "false";
			}
			$output.="<small>bool</small> <span style='color:purple'>".$bool."</span><br>";
		} elseif(is_float($data)){
			$output.="<small>float</small> <span style='color:green'>".$data."</span><br>";
		} elseif(is_object($data)){
			$object_var_list = get_object_vars($data);
			$output.=repeat("&nbsp;",$GLOBALS["indent"],3) . "<b>object</b> <i>".get_class($data)."</i><br>";
			$GLOBALS["indent"]++;
			foreach($object_var_list as $key=>$val){
				$output.=repeat("&nbsp;",$GLOBALS["indent"],3);
				if(is_string($key)){
					$output.="'".$key."'";
				} else {
					$output.=$key;
				}
				$output.=" -> ";
				if(is_array($val)){
					$output.="<br>";
					$GLOBALS["indent"]++;
					$output.=dump($val,false);
					$GLOBALS["indent"]--;
				} else {
					$output.=dump($val,false);
				}
			}
			$GLOBALS["indent"]-=1;
		} else {
			$output.="<span style='color:blue'>NULL</span><br>";
		}
		if($echo){
			echo $output."<br/>";
		} else {
			return $output;
		}
	}
	function sqlShow($show = false){
		$GLOBALS["show_sql"] = $show;
	}
	function sqlGetConnection(){
		if(isset($GLOBALS["connection"])){
			return $GLOBALS["connection"];
		} else {
			$servername = settings("DB_SERVER_NAME");
			$username=settings("DB_USERNAME");
			$password=settings("DB_PASSWORD");
			$db_name=settings("DB_DBNAME");
			$connection = mysqli_connect($servername, $username, $password, $db_name);
			$GLOBALS["connection"] = $connection;
			return $connection;
		}
	}
	function sql($sql, $object=array()){
		$get = false;
		$return_id = false;
		if(preg_match("/^[ ]*[sS][eE][lL][eE][cC][tT]/", $sql)){
			$get = true;
		} elseif(preg_match("/^[ ]*[iI][nN][sS][eE][rR][tT]/",$sql)){
			$return_id = true;
		}
		$conn = sqlGetConnection();
		if(count($object)){
			$stmt = $conn->prepare($sql);
			if(!function_exists("types")){
				function types(&$types,$object){
					foreach($object as $row){
						if(is_int($row)){
							$types.="i";
						} elseif(is_array($row)){
							types($types,$row);
						} else {
							$types.="s";
						}
					}
				}
			}
			$types = "";
			types($types,$object);
			$params = array(&$types);
			$i=0;
			foreach($object as $row){
				if(is_array($row)){
					foreach($row as $item){
						$var_name = "name".$i;
						$$var_name = $item;
						$i++;
					}
				} else {
					$var_name = "name".$i;
					$$var_name = $row;
					$i++;
				}
			}
			$j=0;
			while($j<$i){
				$var_name = "name".$j;
				$params[] = &$$var_name;
				$j++;
			}
			call_user_func_array(array($stmt,"bind_param"),$params);
			if(isset($GLOBALS["show_sql"])){
				dump($params);
				echo "<br>".$sql."<br>";
				if($GLOBALS["show_sql"]){
					exit;
				}
				unset($GLOBALS["show_sql"]);
			}
			if(!$stmt){
				return array();
			}
			if($get){
				$stmt->execute();
                $stmt->store_result();
                $variables = array();
                $data = array();
                $meta = $stmt->result_metadata();
                while($field = $meta->fetch_field()) {
                    $variables[] = &$data[$field->name];
                }
                call_user_func_array(array($stmt, 'bind_result'), $variables);
                $i=0;
                while($stmt->fetch())
                {
                    $array[$i] = array();
                    foreach($data as $k=>$v)
                    $array[$i][$k] = $v;
                    $i++;
                }
				sqlGetForeignKeys($array);
				return $array;
			} else {
				if($return_id){
					$stmt->execute();
					return $stmt->insert_id;
				} else {
					return $stmt->execute();
				}
			}
		} else {
			if(isset($GLOBALS["show_sql"])){
				echo "<br>".$sql."<br>";
				if($GLOBALS["show_sql"]){
					exit;
				}
			}
			if($get){
				$result = $conn->query($sql);
				$return = array();
				if($result->num_rows>0){
					while($row=$result->fetch_assoc()){
						$return[]=$row;
					}
					sqlGetForeignKeys($return);
					return $return;
				} else {
					return array();
				}
			} else {
				return $conn->query($sql);
			}
		}
	}
	function sqlGetForeignKeys(&$data){
		$key_list = array();
		foreach($data as $row){
			foreach($row as $col=>$val){
				if($val==null){
					continue;
				}
				preg_match("/^([\s\S]+)_id$/",$col,$match);
				if(count($match)){
					$table = $match[1];
					if(!isset($key_list[$table])){
						$key_list[$table] = array();
					}
					$key_list[$table][] = $val;

				}
			}
		}
		foreach($key_list as $table=>$id_list){
			$conn = sqlGetConnection();
			$result = $conn->query("SELECT * FROM ".$table." WHERE id IN (".implode(",",$id_list).")");
			while($row = $result->fetch_assoc()){
				$return = array();
				foreach($data as $i=>$item){
					foreach($item as $col=>$val){
						if($col==$table."_id"&&$val==$row["id"]){
							$data[$i][$table]=$row;
							unset($data[$i][$table."_id"]);
						}
					}
				}
			}
		}
	}
	function sqlGetBy($table, $rows){
		$sql = "SELECT * FROM ".$table. " WHERE true ";
		foreach($rows as $col=>$val){
			$sql.=" AND ".$col." ";
			if(is_array($val)){
				$sql.=" IN (";
				foreach($val as $i=>$value){
					if($i!=0){
						$sql.=",";
					}
					$sql.="?";
				}
				$sql.=") ";
			} else {
				$sql.=" = ? ";
			}
		}
		return sql($sql, $rows);
	}
	// function sqlSelectBuilder($data){
	// 	$select = "";
	// 	$from = "";
	// 	$where = array();
	// 	$limit = "";
	// 	$offset = "";
	// 	$order="";
	// 	$order_by="";
	// 	if($data["select"]){
	// 		$select = $data["select"];
	// 	} else {
	// 		return array();
	// 	}
	// 	if($data["from"]){
	// 		$from = $data["from"];
	// 	} else {
	// 		return array();
	// 	}
	// 	if($data["where"]){
	// 		$where = $data["where"];
	// 	}
	// 	if($data["limit"]){
	// 		$limit = $data["limit"];
	// 	}
	// 	if($data["offset"]){
	// 		$offset = $data["offset"];
	// 	}
	// 	if($data["order_by"]){
	// 		$order_by = $data["order_by"];
	// 	}
	// 	if($data["order"]){
	// 		$order = $data["order"];
	// 		if(!$data["order_by"]){
	// 			$order_by = "id";
	// 		}
	// 	}
	// 	$sql = "SELECT $select FROM $from ";
	// 	if($where){
	// 		$sql.=" WHERE TRUE ";
	// 		foreach($where as $col=>$val){
	// 			if(is_string($val)){
	// 				$val="'$val'";
	// 			}
	// 			$sql.= " AND $col = $val ";
	// 		}
	// 	}
	// 	if($order_by){
	// 		$sql.= "ORDER BY $order_by ";
	// 	}
	// 	if($order){
	// 		$sql.= " $order ";
	// 	}
	// 	if($limit){
	// 		$sql.= " LIMIT $limit ";
	// 	}
	// 	if($offset){
	// 		$sql.= " OFFSET $offset ";
	// 	}
	// 	return sql($sql, true);
	// }
	function sqlGetAll($table){
		return sqlGetBy($table,array());
	}
	function sqlGetById($table, $id){
		if(is_array($id)){
			return sqlGetBy($table, array("id"=>$id));
		}
		return sqlGetBy($table, array("id"=>$id))[0];
	}
	function sqlUpdate($table,$where,$rows){
		if(!sqlGetBy($table,$where)){
			return array();
		}
		$sql = "UPDATE ".$table." SET ";
		$row = "";
		foreach($rows as $col=>$val){
			if($row!=""){
				$row.=",";
			}
			$row .= " ".$col." = ? ";
		}
		$sql .= $row . " WHERE true ";
		foreach($where as $col=>$val){
			$sql.=" AND ".$col." ";
			if(is_array($val)){
				$sql.=" IN (";
				foreach($val as $i=>$value){
					if($i!=0){
						$sql.=",";
					}
					$sql.="?";
				}
				$sql.=") ";
			} else {
				$sql.=" = ? ";
			}
		}
		$sql.=";";
		$object = array();
		foreach($rows as $row){
			$object[] = $row;
		}
		foreach($where as $row){
			$object[] = $row;
		}
		return sql($sql,$object);
	}
	function sqlDelete($table,$rows){
		$sql = "DELETE FROM ".$table." WHERE true ";
		foreach($rows as $col=>$val){
			$sql.=" AND ".$col." ";
			if(is_array($val)){
				$sql.=" IN (";
				foreach($val as $i=>$value){
					if($i!=0){
						$sql.=",";
					}
					$sql.="?";
				}
				$sql.=") ";
			} else {
				$sql.=" = ? ";
			}
		}
		$sql.=";";
		return sql($sql,$rows);
	}
	function sqlInsert($table,$rows){
		$sql = "INSERT INTO ".$table;
		$columns="";
		$values="";
		foreach($rows as $col=>$val){
			if($columns!=""){
				$columns.=", ";
				$values.=", ";
			}
			$columns.=$col;
			$values.="?";
		}
		$sql.=" (".$columns.") VALUES (".$values.");";
		return sql($sql,$rows);
	}
	function getPosts($query_params){
		$post_db_list = sqlGetBy("posts",$query_params);
		$post_list = $post;
		foreach($post_db_list as $item=>$post){
			$post["edit"] = getLoggedInUser()["id"] == $post["users"]["id"];
			$post_list[$item]=$post;
		}
		return $post_list;
	}
	function getParentPost($post){
		$parent = sqlGetById("posts",array("id"=>$post["posts"]["id"]))[0];
		if($parent){
			$parent["edit"] = getLoggedInUser()["id"] == $parent["users"]["id"];
			return $parent;
		}
	}
	function getCommentList($post_list){
		$post_id_list = array();
		foreach($post_list as $post){
			$post_id_list[] = $post["id"];
		}
		$comment_list = sqlGetBy("posts",array("posts_id"=>$post_id_list));
		foreach($comment_list as $item=>$comment){
			$comment["edit"] = getLoggedInUser()["id"] == $comment["users"]["id"];
			$comment_list[$item] = $comment;
		}
		return $comment_list;
	}
	function getFriendIdList($user_id){
		$friend_db_list = sql("SELECT CASE WHEN sender = ? THEN receiver ELSE sender END AS friend FROM friendships WHERE (sender=? OR receiver = ?) AND confirmed = 'Y'",array($user_id,$user_id,$user_id));
		$friend_list = array();
		foreach($friend_db_list as $friend){
			$friend_list[] = $friend["friend"];
		}
		return $friend_list;
	}
	function isFriend($user_id,$friend_id){
		return in_array($friend_id,getFriendIdList($user_id));
	}
	function getFriendList($user_id){
		return sqlGetById("users",getFriendIdList($user_id));
	}
	function email($to,$subject,$name="",$html,$text="",$debug=0){

		if($text==""){
			$text = $html;
		}
		// Load Composer's autoloader
		// require 'vendor/autoload.php';

		$mail = new PHPMailer(true);                              // Passing `true` enables exceptions
		try {
		    //Server settings
		    $mail->SMTPDebug = 0;                                 // Enable verbose debug output
		    $mail->isSMTP();                                      // Set mailer to use SMTP
			$mail->Host = settings("EMAIL_HOST");  // Specify main and backup SMTP servers
		    $mail->SMTPAuth = true;                               // Enable SMTP authentication
		    $mail->Username = settings("EMAIL_USERNAME");                 // SMTP username
		    $mail->Password = settings("EMAIL_PASSWORD");                           // SMTP password
		    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
		    $mail->Port = 587;

		    //Recipients
		    $mail->setFrom(settings("EMAIL_ADDRESS_FROM"), settings("EMAIL_NAME_FROM"));
		    $mail->addAddress($to, $name);     // Add a recipient

		    //Content
		    $mail->isHTML(false);                                  // Set email format to HTML
		    $mail->Subject = $subject;
		    $mail->Body    = $html;
		    $mail->AltBody = $text;
		    $mail->send();
		} catch (Exception $e) {
		    echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
		}
		
	}
?>