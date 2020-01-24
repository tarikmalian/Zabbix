<?php
// ----------------------------------------------------------------------------------------
// Autor: Janssen dos Reis Lima <janssenreislima@gmail.com>
// Script: tickets_zabbix_glpi.php
// Ultima Atualizacao: 18/02/2016
// -----------------------------------------------------------------------------------------
// Atualizado 23/01/2020
// Atualizado por Tarik Malian <carlostarik@hotmail.com>
// -----------------------------------------------------------------------------------------
// Configuracoes:
// -----------------------------------------------------------------------------------------
$user =     	"user_glpi";						
$password = 	"passwd_glpi";						
$xmlhost =  	"ip_glpi_user";					
$xmlurl =   	"glpi/plugins/webservices/xmlrpc.php";	
$category = 	"";						
$watcher = 	    "2";						
$watchergroup = "1";						
$sqlhost = 	    "ip_db_glpi";					
$sqldb = 	    "db_glpi";					
$sqluser =  	"user_db_glpi";                             	
$sqlpwd =   	"passwd_db_glpi";                        		
$path_zabbix = 	"/usr/lib/zabbix/externalscripts/";			
// ------------------------------------------------------------------------------------------------------------------------

$arg[] = "method=glpi.test";
$arg[] = "url=$xmlurl";
$arg[] = "host=$xmlhost";
$response = getxml($arg);
unset($arg);
$webservices_version = $response['webservices'];

$eventval=array();
if ($argv>1) {
	for ($i=1 ; $i<count($argv) ; $i++) {
		$it = explode("=",$argv[$i],2);
		$it[0] = preg_replace('/^--/','',$it[0]);
		$eventval[$it[0]] = (isset($it[1]) ? $it[1] : true);
	}
}

$eventhost=$eventval['eventhost'];
$event=$eventval['event'];
$state=$eventval['state'];
$hostproblemid=$eventval['hostproblemid'];
$lasthostproblemid=$eventval['lasthostproblemid'];
$servico=$eventval['servico'];
$eventzabbix=$eventval['eventzabbix'];
$triggerid=$eventval['triggerid'];
unset($eventval);

function getxml($arg) {
	$args=array();
	if ($arg>1) {
	   for ($i=0 ; $i<count($arg) ; $i++) {
		  $it = explode("=",$arg[$i],2);
		  $it[0] = preg_replace('/^--/','',$it[0]);
		  if (strpos($it[1],',') !== false) {
			$it[1] = explode(",", $it[1]);
		  }
		  $args[$it[0]] = (isset($it[1]) ? $it[1] : true);
	   }
	}
	$method=$args['method'];
	$url=$args['url'];
	$host=$args['host'];
	
	if (isset($args['session'])) {
	   $url.='?session='.$args['session'];
	   unset($args['session']);
	}

	$header = "Content-Type: text/xml";

	echo "+ Calling '$method' on http://$host/$url\n";
	
	$request = xmlrpc_encode_request($method, $args);
	$context = stream_context_create(array('http' => array('method'  => "POST",
								'header'  => $header,
								'content' => $request)));

	$file = file_get_contents("http://$host/$url", false, $context);
	if (!$file) {
	   die("+ No response\n");
	}

	if (in_array('Content-Encoding: deflate', $http_response_header)) {
	   $lenc=strlen($file);
	   echo "+ Compressed response : $lenc\n";
	   $file = gzuncompress($file);
	   $lend=strlen($file);
	   echo "+ Uncompressed response : $lend (".round(100.0*$lenc/$lend)."%)\n";
	}
	$response = xmlrpc_decode($file);
	if (!is_array($response)) {
	   echo $file;
	   die ("+ Bad response\n");
	}

	if (xmlrpc_is_fault($response)) {
		echo("xmlrpc error(".$response['faultCode']."): ".$response['faultString']."\n");
	} else {
	   return $response;
	}
}

if (!extension_loaded("xmlrpc")) {
   die("Extension xmlrpc not loaded\n");
}

switch ($event) {
	case "UP":
		if ($lasthostproblemid != 0) { 
			$arg[] = "method=glpi.doLogin";
			$arg[] = "url=$xmlurl";
			$arg[] = "host=$xmlhost";
			$arg[] = "login_password=$password";
			$arg[] = "login_name=$user";

			$response = getxml($arg);
			$session = $response['session'];
			
			$mysql = mysqli_connect($sqlhost, $sqluser, $sqlpwd, $sqldb) or die(mysqli_error());
                        //mysqli_select_db($mysql, $sqldb) or die(mysqli_error($mysql));
			$consulta_chamado = mysqli_query($mysql, "SELECT id FROM glpi_tickets WHERE status <> 5 AND content like '%$triggerid%'");

			$pega_id_ticket = mysqli_fetch_array($consulta_chamado);
			$num_ticket = "{$pega_id_ticket['id']}";
                        $content = "$state: $servico. Registro fechado automaticamente atraves do evento $eventzabbix.";
						
			$arg[] = "method=glpi.addTicketFollowup";
			$arg[] = "url=$xmlurl";
			$arg[] = "host=$xmlhost";
			$arg[] = "session=$session";
			$arg[] = "ticket=$num_ticket";
			$arg[] = "content=$content";
			$resp = getxml($arg);
			unset($arg);
			unset($resp);

			mysqli_query($mysql, "UPDATE glpi_tickets SET status='5' WHERE id='$num_ticket'") or die(mysqli_error($mysql));
			mysqli_close($mysql);

			$arg[] = "method=glpi.doLogout";
			$arg[] = "url=$xmlurl";
			$arg[] = "host=$xmlhost";
			$arg[] = "session=$session";

			$response = getxml($arg);
			unset($arg);
			unset($response);
		}
	case "DOWN":
			switch ($state) {
				case "PROBLEM":
					if ($lasthostproblemid != 1) {
						$arg[] = "method=glpi.doLogin";
						$arg[] = "url=$xmlurl";
						$arg[] = "host=$xmlhost";
						$arg[] = "login_password=$password";
						$arg[] = "login_name=$user";

						$response = getxml($arg);
						$session = $response['session'];

						unset($arg);
						unset($response);
						if (!empty($session)) {
							
							$title = "$state: $servico! - Evento $eventzabbix";
							$content = "Nome do host: $eventhost. ID da trigger: $triggerid. Status da trigger: $state.";
							if ($category != ''){
								$arg[] = "method=glpi.listDropdownValues";
								$arg[] = "url=$xmlurl";
								$arg[] = "host=$xmlhost";
								$arg[] = "session=$session";
								$arg[] = "dropdown=itilcategories";
								$arg[] = "name=$category";
								$response = getxml($arg);
								$categoryid = $response[0]['id'];
								unset($arg);
								$catarg = "category=$categoryid";
							}
							if (!empty($watcher)) {
								$watcherarg = "observer=$watcher";
							} elseif (!empty($watchergroup)) {
								$arg[] = "method=glpi.listUsers";
								$arg[] = "url=$xmlurl";
								$arg[] = "host=$xmlhost";
								$arg[] = "session=$session";
								$arg[] = "group=$watchergroup";
								$response = getxml($arg);
								foreach($response as $user){
									$watcherids .= $user['id'].",";
								}
								$watcherids = rtrim($watcherids, ",");
								$watcherarg = "observer=$watcherids";
								unset($arg);
							} else {
								// uso futuro
							}
							
							
							$arg[] = "method=glpi.createTicket";
							$arg[] = "url=$xmlurl";
							$arg[] = "host=$xmlhost";
							$arg[] = "session=$session";
							$arg[] = "title=$title";
							$arg[] = "content=$content";
							$arg[] = "urgancy=5";

							if (!empty($catarg)) $arg[] = $catarg;
							if (!empty($watcherarg)) $arg[] = $watcherarg;
                                                        if (str_replace(".", "", $webservices_version) >= '120') {
                                                                $arg[] = "use_email_notification=1";
                                                        }
							$response = getxml($arg);
							unset($arg);
							unset($response);

					              	$mysql = mysqli_connect($sqlhost, $sqluser, $sqlpwd, $sqldb) or die(mysqli_error($mysql));
							
							//mysqli_select_db($mysql, $sqldb) or die(mysqli_error($mysql));
					              	$consulta_evento = mysqli_query($mysql,"SELECT id FROM glpi_tickets WHERE name like '%$eventzabbix%'") or die(mysqli_error($mysql));
					
					              	$pega_id_ticket = mysqli_fetch_array($consulta_evento);
					              	$num_ticket = "{$pega_id_ticket['id']}";
					              	sleep(10);
					              	$comando = "python3 $path_zabbix/ack_zabbix_glpi.py $eventzabbix $num_ticket";
					              	$output = shell_exec($comando);
					              	mysqli_close($mysql);
						
							$arg[] = "method=glpi.doLogout";
							$arg[] = "url=$xmlurl";
							$arg[] = "host=$xmlhost";
							$arg[] = "session=$session";
	
							$response = getxml($arg);
							unset($arg);
							unset($response);
						}
					}
			}
}


?>
