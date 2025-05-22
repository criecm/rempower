<?php
// manage APC ports
// inspire de: http://systembash.com/content/use-php-for-apc-snmp-mib/
include("lib/check.php");
include("config.php");
//include("lib/lib.php");
require "vendor/autoload.php";
//require "classes/autoload.php";

//use Dsiecm\Rempower\PDU;
//use Dsiecm\Rempower\PDU\apc;
//use Dsiecm\Rempower\Utils\Str;
//use PDU;

/* Logging */
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\SyslogHandler;
use Monolog\Formatter\LineFormatter;

if ( array_key_exists("debug",$_GET) || ( $GLOBALS["debug"] == true ) ) {
	$LogLevel = Level::Debug;
	ini_set("display_errors",true);
} else {
	$LogLevel = Level::Info;
}

$log = new Logger('rempower');
$syslog = new SyslogHandler('rempower','local6',$LogLevel);
$formatter = new LineFormatter("%channel%.%level_name%: %message% %extra%");
$syslog->setFormatter($formatter);
$log->pushHandler($syslog);

$GLOBALS["refreshpage"]=$_SESSION["WILLNEEDTOREFRESH"];
$_SESSION["WILLNEEDTOREFRESH"]=false;

$pdus = array();
foreach ($apcids as $pdu => $descr) {
	$class="Dsiecm\\Rempower\\PDU\\".$descr["type"];
	$pdus[$pdu] = new $class($pdu,$descr["ip"],$GLOBALS["apcsnmp"]);
}
//var_dump($pdus);
//exit(0);
?><!doctype html><html>
<head>
  <title>Prises APC ECM</title>
  <meta http-equiv = "Content-Type" content = "text/html; charset=utf-8">
  <link rel="stylesheet" type="text/css" href="apc.css" media="screen" />
  <link rel="stylesheet" type="text/css" href="mobile.css" media="handheld" />
  <link rel="stylesheet" type="text/css" href="mobile.css" media="only screen and (max-device-width: 480px)" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
if (($GLOBALS["refreshpage"]) && count($_SESSION["renew"])) { ?>
  <meta http-equiv="refresh" content="5" /><?php
} ?>
  <script type="text/javascript" src="js/jquery-1.7.1.min.js" ></script>
  <script type="text/javascript" src="js/jquery.dataTables.min.js"></script>
  <script type="text/javascript" charset="utf-8">
$(document).ready(function() 
    { 
        $('#OutletsTable').dataTable( {
        	"bPaginate": false,
        	"bAutoWidth":false,
        	"oLanguage": { "sSearch": "Chercher:" }
         }
        ); 
	$("td.name").dblclick(function(e) {
		$(this).children("span.val").hide();
		$(this).children("span.form").show();
//		$(this).parent().children("span.form").style.display="inline";
	});
    } 
);
function refreshpage() {
        window.location=window.location;
}
  </script>
</head>
<body>
<div id="tout">
<h1>Gestion des prises APC de la salle serveur</h1>
<p class="alert">Ceci n'est pas un exercice…</p>
<?php
// ceci permet de remettre a jour le statut des ports recemment modifies
if (count($_SESSION["renew"]) > 0) {
  echo "<div id=\"recent\"><h3>Etat des dernières prises modifiées: </h3>\n";
  echo "<p>";
  foreach ($_SESSION["renew"] as $k => $v) {
    $keys=preg_split('/_/',$k);
    echo $keys[0]."/".$keys[1].": ". $pdus[$keys[0]]->getPortStatus($keys[1],true)." | ";
    if ($v+$GLOBALS["renewsecs"] < time()) {
      unset($_SESSION["renew"][$k]);
    }
  }
  echo "</p>\n";
  echo "<p class=\"refresh\"><a href=\"javascript:refreshpage()\">Refresh</a></p>\n";
  echo "</div>\n";
}
if (array_key_exists("cancel",$_POST) && array_key_exists("TODO",$_SESSION) && ($_POST["cancel"] == "KEY2")) {
  unset($_SESSION["TODO"]);
  echo "<div id=\"result\">\n";
  echo "<p class=\"alert\">Tous les ports sont bien arrêtés.</p>\n";
  echo "<p class=\"joke\">… Bon allez, on bosse ou on rigole là ? :-p</p>\n";
  echo "</div>";
} elseif (array_key_exists("gogo",$_POST) && array_key_exists("TODO",$_SESSION) && ($_POST["gogo"] == "KEY2")) {
  // JUSTDOIT
  echo "<div id=\"result\">\n";
  ini_set("display_errors",true);
  foreach ($_SESSION["TODO"] as $prise => $todo) {
    echo "<h3>Sur ".$prise.":</h3>\n<ul class=\"doing\">";
    foreach ($todo as $port => $action) {
      if ($pdus[$prise]->controlPort($port,$action)) {
        $str=$pdus[$prise]->getStateName($action)." sur la prise ".$prise." port ".$port." (".$pdus[$prise]->getPortName($port).")";
  	error_log($str,1,$GLOBALS["mailto"],"Subject: Action APC via metrologie");
        echo "<li class=\"done\">OK Port $port (".$pdus[$prise]->getPortName($port).") ".$pdus[$prise]->getPortStatus($port,true)."\n</li>";
        // if reboot ou delayed action, note this port to be re-checked later
        if (($action >= 3) && ($action < 7))
          $_SESSION["renew"][$prise."_".$port]=time();
      } else {
        echo "<li class=\"failed\">FAILED Port $port (".$pdus[$prise]->getPortName($port).") ".$pdus[$prise]->getPortStatus($port,true)."\n</li>";
      }
    }
    echo "</ul>\n";
  }
  unset($_SESSION["TODO"]);
  echo "</div>\n";
  if (count($_SESSION["renew"]) > 0)
    echo "<script type=\"text/javascript\">setTimeout('refreshpage()',5000);</script>\n";
}
// traitement du formulaire (confirmation)
elseif (array_key_exists("go",$_POST) && ($_POST["go"] == "KEY1")) {
  $_SESSION["TODO"]=array();
  unset($_POST["go"]);
  $TODOS=array('name'=>array(),'timer'=>array(),'action'=>array());
  foreach ($_POST as $k => $v) {
    $keys = preg_split('/_/',$k);
// print "<pre>KEYS count: ".count($keys)."</pre>\n";
    if (count($keys) == 3) {
      $portstatus=$pdus[$keys[0]]->getPortStatus($keys[1],true);
#echo "<pre>";
#print_r($keys);
#print_r($portnow);
#echo "</pre>\n";
      switch ($keys[2]) {
        case "action":	
          if (array_key_exists($keys[0],$_SESSION) && ($portstatus != $v) && ($pdus[$keys[0]]->getStateName($v) != FALSE)) {
            $TODOS['action'][]=array($keys[0],$keys[1],$v);
          }
        break;
        case "timer":
          if (array_key_exists($keys[0],$_SESSION) && ($pdus[$keys[0]]->getPortDelay($keys[1],TRUE) != $v)) {
            $TODOS['timer'][]=array($keys[0],$keys[1],$v);
          }
        break;
        case "name":
          if (array_key_exists($keys[0],$_SESSION) && ($pdus[$keys[0]]->getPortName($keys[1],TRUE) != $v)) {
            $TODOS['name'][]=array($keys[0],$keys[1],$v);
          }
        break;
      }
    }
  }

  if (count($TODOS['action']) > 0) {
    $_SESSION["TODO"]=array();
    foreach ($TODOS['action'] as $k => $todo) {
      if (!array_key_exists($todo[0],$_SESSION["TODO"]))
	$_SESSION["TODO"][$todo[0]]=array();

      $_SESSION["TODO"][$todo[0]][$todo[1]]=$todo[2];
    }
  }

  if (count($_SESSION["TODO"]) > 0) {
    echo "<div id=\"confirm\">\n";
    echo "<ul class=\"todos\">\n";
    foreach ($_SESSION["TODO"] as $prise => $todo) {
      foreach ($todo as $port => $action) {
        echo "  <li>".$prise."/ port ".$port." (".$pdus[$prise]->getPortName($port).") ".$pdus[$prise]->getPortStatus($port)." => <span class=\"willdo\">".$pdus[$prise]->getStateName($action)."</span></li>\n";
        if (($action >= 0) && ($action < 4)) {
          $_SESSION["WILLNEEDTOREFRESH"]=true;
	}
      }
    }
    echo "</ul>\n";
?>
<form method="post"><input type="hidden" name="gogo" value="KEY2" /><input type="submit" class="tfou" value="Executer ces actions maintenant" /></form>
<form method="post"><input type="hidden" name="cancel" value="KEY2" /><input type="submit" class="cancel" value="Annuler, c'était pour rire !" /></form>
</div>
<?php
  } else {
    echo "<div id=\"results\">\n  <ul class=\"done\">\n";
    foreach ($TODOS['name'] as $k => $todo) {
      echo "    <li class=\"done\">".$todo[0]."/".$todo[1].": \"<span class=\"status\">".$pdus[$todo[0]]->getPortName($todo[1])."</span>\" => \"".$todo[2]."\" ...";
      if ($pdus[$todo[0]]->setPortName($todo[1],$todo[2]) == TRUE) {
	echo "done :)";
      } else {
	echo "<span class=\"iserror\">Non ?!?</span>";
      }
      echo "</li>\n";
    }
    foreach ($TODOS['timer'] as $k => $todo) {
      $portstate=$pdus[$todo[0]]->getPortStatus($todo[1]);
      echo "    <li class=\"done\">".$todo[0]."/".$todo[1].": \"<span class=\"status\">".$pdus[$todo[0]]->getPortDelay($todo[1])."</span>\" => \"".$todo[2]."\" ...";
      if ($pdus[$todo[0]]->setPortDelay($todo[1],$todo[2]) == TRUE) {
	echo "done :)";
      } else {
	echo "<span class=\"iserror\">Non ?!?</span>";
      }
      echo "</li>\n";
    }
    echo "</div>\n";
    unset($_SESSION["TODO"]);
  }
}
if (!array_key_exists("TODO",$_SESSION)) {
?>
<div id="outlets">
  <form method="post" name="Outlets">
  <table id="OutletsTable">
    <thead>
    <tr><th>Machine</th><th>P/O</th><th>Now</th><th>Action</th><th>Timer</th></tr>
    </thead>
    <tbody>
<?php
  foreach ($GLOBALS["apcids"] as $prise => $ip) {
    $groupprise=preg_replace('/^(prise|pdu)(.*)[-_]?[0-9]+$/','$2',$prise);
    $groupprise=preg_replace('/[-_]$/','',$groupprise);
    $noprise=preg_replace('/^.*(\d+)$/','$1',$prise);
    if ($groupprise != "") {
      $labelprise = $groupprise."/".$noprise;
    } else {
      $labelprise = $noprise;
    }
    echo "<!-- prise $prise (groupe $groupprise, label $labelprise -->\n";
    foreach ($pdus[$prise]->getPortsIds() as $portid) {
      $portname = $pdus[$prise]->getPortName($portid);
      if ($portname == "") {
              $pname="ZZZvide";
      } else {
	      $pname = $portname;
      }
      echo "    <tr>\n<td class=\"name\"><span class=\"index\" style=\"display: none\">$pname</span><span class=\"val\">".$portname."</span><span class=\"form\" style=\"display: none\"><input type=\"text\" value=\"".$portname."\" name=\"".$prise."_".$portid."_name\"></span></td><td>".$labelprise."/".$portid."</td>\n";
      // port's actual state
      $states = $pdus[$prise]->getAllowedStates();
      echo "<!-- STATES: ".join(",",$states)." -->\n";
      $portstate=$pdus[$prise]->getPortStatus($portid);
      $portstatelabel=$pdus[$prise]->getStateName($portstate);
      echo "      <td><span class=\"status is".$portstatelabel."\">$portstatelabel</span></td>\n";
      // select for state
      echo "      <td>\n";
      echo "        <select name=\"".$prise."_".$portid."_action\">\n";
      foreach ($states as $code => $label) {
	      if ($code == $portstate) {
		      echo "          <option value=\"".$code."\" disabled=\"disabled\" selected=\"selected\">Action</option>\n";
	      } else {
		      echo "          <option value=\"".$code."\">".$label."</option>\n";
	      }
      }
      echo "        </select>\n";
      echo "      </td>\n";
      // select for timer
      $timer=$pdus[$prise]->getPortDelay($portid);
      echo "<!-- $prise / $portid timer = $timer -->\n";
      echo "      <td>\n";
      echo "        <select name=\"".$prise."_".$portid."_timer\">\n";
      $timers=array();
      echo "<!-- GLOBAL timers = ".join(",",$GLOBALS["basetimers"])." -->\n";
      foreach ($GLOBALS["basetimers"] as $tm) {
      echo "<!-- timers[] = ".$tm." -->\n";
	      $timers[]=(int)$tm + (int)$noprise + (int)$portid;
      }
      echo "<!-- timers = ".join(",",$timers)." -->\n";
      // si le timer actuel n'est pas dans la liste, on l'ajoute pour ne rien changer
      if (!in_array($timer,$timers)) {
	      echo "          <option value=\"".$timer."\" disabled=\"disabled\" selected=\"selected\">".$timer."s</option>\n";
      }
      foreach ($timers as $secs) {
	      if ($secs==$timer) {
		      echo "          <option value=\"".$secs."\" disabled=\"disabled\" selected=\"selected\">".$secs."s</option>\n";
	      } else {
		      echo "          <option value=\"".$secs."\">".$secs."s</option>\n";
	      }
      }
      echo "        </select>\n";
      echo "      </td>\n";
      echo "    </tr>\n";
    }
  } ?>
    </tbody>
  </table>
  <p class="boulegue"><input type="hidden" name="go" value="KEY1"><input type="submit" value="Moa je crun dégun !" /></p></form>
</div>
<?php }
if ($GLOBALS['debug']) { ?>
<div id="debug">
<?php if (defined("TODOS")) { ?>
<pre>TODOS: <?php print_r($TODOS); ?></pre>
<?php } ?>
<pre>REFRESH: <?php print $GLOBALS["refreshpage"]?"OUI":"NON"; ?></pre>
<pre><?php print_r($_SESSION); ?></pre>
<pre><?php print_r($_POST); ?></pre>
</div>
<?php } ?>
</div>
</body>
