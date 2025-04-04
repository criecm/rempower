<?php
// manage APC ports
// inspire de: http://systembash.com/content/use-php-for-apc-snmp-mib/
include("lib/check.php");
include("config.php");
include("lib/lib.php");

if (array_key_exists("debug",$_GET))
  $GLOBALS['debug']=true;

if ($GLOBALS['debug'])
  ini_set("display_errors",true);

$GLOBALS["refreshpage"]=$_SESSION["WILLNEEDTOREFRESH"];
$_SESSION["WILLNEEDTOREFRESH"]=false;

//if ($_SESSION["WILLNEEDREFRESH"])
//  $GLOBALS["refreshpage"]=true;

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
    echo $keys[0]."/".$keys[1].": ".getPortStatus($keys[0],$keys[1],true)." | ";
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
      if (manageAPCPort($prise,$port,$action)) {
        logAction($GLOBALS["states"][$action]." sur la prise ".$prise." port ".$port." (".$_SESSION[$prise]["names"][$port].")");
        echo "<li class=\"done\">OK Port $port (".$_SESSION[$prise]["names"][$port].") ".getPortStatus($prise,$port,true)."\n</li>";
        // if reboot ou delayed action, note this port to be re-checked later
        if (($action >= 3) && ($action < 7))
          $_SESSION["renew"][$prise."_".$port]=time();
      } else {
        echo "<li class=\"failed\">FAILED Port $port (".$_SESSION[$prise]["names"][$port].") ".getPortStatus($prise,$port,true)."\n</li>";
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
      $portnow=getPDUPort($keys[0],$keys[1],true);
#echo "<pre>";
#print_r($keys);
#print_r($portnow);
#echo "</pre>\n";
      switch ($keys[2]) {
        case "action":	
          if (array_key_exists($keys[0],$_SESSION) && ($portnow[0] != $v) && array_key_exists($v,$GLOBALS["states"])) {
            $TODOS['action'][]=array($keys[0],$keys[1],$v);
          }
        break;
        case "timer":
          if (array_key_exists($keys[0],$_SESSION) && ($portnow[1] != $v)) {
            $TODOS['timer'][]=array($keys[0],$keys[1],$v);
          }
        break;
        case "name":
          if (array_key_exists($keys[0],$_SESSION) && ($portnow[2] != $v)) {
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
        echo "  <li>".$prise."/ port ".$port." (".$_SESSION[$prise]["names"][$port].") ".getPortStatus($prise,$port)." => <span class=\"willdo\">".$GLOBALS["states"][$action]."</span></li>\n";
        if (($action >= 3) && ($action < 7)) {
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
      $portstatus=getPDUPort($todo[0],$todo[1]);
      echo "    <li class=\"done\">".$todo[0]."/".$todo[1].": \"".$portstatus[2]."\" => \"".$todo[2]."\" ...";
      if (setAPCPortName($todo[0],$todo[1],$todo[2])) {
	echo "done :)";
      } else {
	echo "<span class=\"iserror\">Non ?!?</span>";
      }
      echo "</li>\n";
    }
    foreach ($TODOS['timer'] as $k => $todo) {
      $portstatus=getPDUPort($todo[0],$todo[1]);
      echo "    <li class=\"done\">".$todo[0]."/".$todo[1].": \"".$portstatus[1]."\" => \"".$todo[2]."\" ...";
      if (setAPCPortTimer($todo[0],$todo[1],$todo[2])) {
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
  foreach ($GLOBALS["apcids"] as $prise => $desc) {
    if ($desc["type"] != "apc") {
      continue;
    }
    getPDU($prise);
    $noprise=preg_replace('/^prise/','',$prise);
//print_r($_SESSION);
    for ($i=1; $i < 25; $i++) {
      //echo "    <tr>\n<td>".$_SESSION[$prise]["names"][$i]."</td><td>".$noprise."/".$i."</td>\n";
      $pname=$_SESSION[$prise]["names"][$i];
      if ($pname == "") {
              $pname="ZZZvide";
      }
      echo "    <tr>\n<td class=\"name\"><span class=\"index\" style=\"display: none\">$pname</span><span class=\"val\">".$_SESSION[$prise]["names"][$i]."</span><span class=\"form\" style=\"display: none\"><input type=\"text\" value=\"".$_SESSION[$prise]["names"][$i]."\" name=\"".$prise."_".$i."_name\"></span></td><td>".$noprise."/".$i."</td>\n";
      echo "      <td>".getPortStatus($prise,$i)."</td>\n";
      echo "      <td>";
      selectforPort($prise,$i);
      echo "\n      </td>\n";
      echo "      <td>";
      selectPortTimer($prise,$i);
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
<?php if (defined($TODOS)) { ?>
<pre>TODOS: <?php print_r($TODOS); ?></pre>
<?php } ?>
<pre>REFRESH: <?php print $GLOBALS["refreshpage"]?"OUI":"NON"; ?></pre>
<pre><?php print_r($_SESSION); ?></pre>
<pre><?php print_r($_POST); ?></pre>
</div>
<?php } ?>
</div>
</body>
