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
  foreach ($_POST as $k => $v) {
    $keys = preg_split('/_/',$k);
    if (array_key_exists($keys[0],$_SESSION) && (getPDU($keys[0],$keys[1],true) != $v) && array_key_exists($v,$GLOBALS["states"])) {
      if (!array_key_exists($keys[0],$_SESSION["TODO"]))
        $_SESSION["TODO"][$keys[0]]=array();
      $_SESSION["TODO"][$keys[0]][$keys[1]]=$v;
    }
  }
  if (count($_SESSION["TODO"]) > 0) {
    echo "<div id=\"confirm\">\n";
    foreach ($_SESSION["TODO"] as $prise => $todos) {
      echo "<h3>Sur ".$prise.":</h3>\n<ul class=\"todos\">";
      foreach ($todos as $port => $action) {
        if (($action >= 3) && ($action < 7))
          $_SESSION["WILLNEEDTOREFRESH"]=true;
        echo "  <li>Port $port (".$_SESSION[$prise]["names"][$port].") ".getPortStatus($prise,$port)." => <span class=\"willdo\">".$GLOBALS["states"][$action]."</span></li>\n";
      }
      echo "</ul>\n";
    }
?>
<form method="post"><input type="hidden" name="gogo" value="KEY2" /><input type="submit" class="tfou" value="Executer ces actions maintenant" /></form>
<form method="post"><input type="hidden" name="cancel" value="KEY2" /><input type="submit" class="cancel" value="Annuler, c'était pour rire !" /></form>
</div>
<?php
  } else { 
    echo "<div id=\"result\"><p class=\"nothing\">Nothing to do</p></div>\n";
    unset($_SESSION["TODO"]);
  }
}
if (!array_key_exists("TODO",$_SESSION)) { 
?>
<div id="outlets">
  <form method="post" name="Outlets">
  <table id="OutletsTable">
    <thead>
    <tr><th>Machine</th><th>P/O</th><th>Now</th><th>Action</th></tr>
    </thead>
    <tbody>
<?php
  foreach ($GLOBALS["apcids"] as $prise => $ip) {
    getPDU($prise);
    $noprise=preg_replace('/^prise/','',$prise);
//print_r($_SESSION);
    for ($i=1; $i < 25; $i++) {
      echo "    <tr><td>".$_SESSION[$prise]["names"][$i]."</td><td>".$noprise."/".$i."</td>\n";
      echo "      <td>".getPortStatus($prise,$i)."</td>\n";
      echo "      <td>";
      selectforPort($prise,$i);
      echo "\n      </td>\n    </tr>\n";
    }
  } ?>
    </tbody>
  </table>
  <p class="boulegue"><input type="hidden" name="go" value="KEY1"><input type="submit" value="Moa je crun dégun !" /></p></form>
</div>
<?php } 
if ($GLOBALS['debug']) { ?>
<div id="debug">
<pre>REFRESH: <?php print $GLOBALS["refreshpage"]?"OUI":"NON"; ?></pre>
<pre><?php print_r($_SESSION); ?></pre>
<pre><?php print_r($_POST); ?></pre>
</div>
<?php } ?>
</div>
</body>
