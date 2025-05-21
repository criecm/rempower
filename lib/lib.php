<?php
/* vim: set ts=2 sw=2 et */
// traitement de valeur SNMP
function stripSNMPvalue($str) {
  $motifs=array('/^(INTEGER|STRING): /','/^"/','/"$/');
  if (preg_match('/INTEGER/',$str)) {
    $motifs[]='/[^0-9]/';
  }
  return preg_replace($motifs,'',$str);
}

// update SNMP infos in session for a rack
function getPDU($apcid,$renew=false) {
  // si c'est la premiere fois, on force la mise a jour
  if (!array_key_exists($apcid,$_SESSION)) {
    $_SESSION[$apcid]=array();
    $_SESSION[$apcid]["names"]=array();
    $_SESSION[$apcid]["control"]=array();
    $_SESSION[$apcid]["timer"]=array();
    $renew=true;
  }
  // si ?renew=qqch, on force
  if (array_key_exists("renew",$_GET))
    $renew=true;

  // on met a jour si demande ou si c'est la premiere fois
  if (($renew)||(!array_key_exists(1,$_SESSION[$apcid]["names"]))) {
    $resn=snmp2_walk($GLOBALS["apcids"][$apcid]["ip"],$GLOBALS["apcsnmp"],$GLOBALS["apc"]["portnamemib"]);
    foreach ($resn as $k => $v)
      $_SESSION[$apcid]["names"][$k+1]=stripSNMPvalue($v);
  }
  // status
  if (($renew)||(!array_key_exists(1,$_SESSION[$apcid]["control"]))) {
    $resc=snmp2_walk($GLOBALS["apcids"][$apcid]["ip"],$GLOBALS["apcsnmp"],$GLOBALS["apc"]["portcontrolmib"]);
    if ($resc)
      foreach ($resc as $k => $v)
        $_SESSION[$apcid]["control"][$k+1]=stripSNMPvalue($v);
  }
  // timers
  if (($renew)||(!array_key_exists(1,$_SESSION[$apcid]["timer"]))) {
    $resc=snmp2_walk($GLOBALS["apcids"][$apcid]["ip"],$GLOBALS["apcsnmp"],$GLOBALS["apc"]["porttimermib"]);
    if ($resc)
      foreach ($resc as $k => $v)
        $_SESSION[$apcid]["timer"][$k+1]=stripSNMPvalue($v);
  }
  return $_SESSION[$apcid];
}

function getPDUPort($apcid,$port,$renew=false) {
  if (!array_key_exists($apcid,$_SESSION)) {
    getPDU($apcid);
  }
  if ($renew) {
    $_SESSION[$apcid]["control"][$port]=stripSNMPvalue(snmpget($GLOBALS["apcids"][$apcid]["ip"],$GLOBALS["apcsnmp"],$GLOBALS["apc"]["portcontrolmib"].".".$port));
    $_SESSION[$apcid]["timer"][$port]=stripSNMPvalue(snmpget($GLOBALS["apcids"][$apcid]["ip"],$GLOBALS["apcsnmp"],$GLOBALS["apc"]["porttimermib"].".".$port));
    $_SESSION[$apcid]["names"][$port]=stripSNMPvalue(snmpget($GLOBALS["apcids"][$apcid]["ip"],$GLOBALS["apcsnmp"],$GLOBALS["apc"]["portnamemib"].".".$port));
  }
  if (!array_key_exists(1,$_SESSION[$apcid]["names"])) {
    // TODO: error !!!
    die("AIIIE");
    return false;
  }
  return array($_SESSION[$apcid]["control"][$port],$_SESSION[$apcid]["timer"][$port],$_SESSION[$apcid]["names"][$port]);
}

function logAction($str) {
  error_log($str);
  error_log($str,1,"toro@ec-m.fr","Subject: Action APC via metrologie");
}

// bool function manageAPCPort
//    $apcid = ID of the APC unit as defined in config.php
//    $apcport = Port of the outlet on the APC Switch
//    $apcpass = APC Pass (SNMP Community String)
//    $action {
//      immediateOn             (1),
//      immediateOff            (2),
//      immediateReboot         (3),
//      delayedOn               (4),
//      delayedOff              (5),
//      delayedReboot           (6),
//      cancelPendingCommand    (7)
//    }
//  returns TRUE or FALSE
function manageAPCPort($apcid, $apcport, $action) {
  $ip = $GLOBALS["apcids"][$apcid]["ip"];
  $mib = $GLOBALS["apc"]["portcontrolmib"].".".$apcport;
  $VERIFY = snmpset($ip, $GLOBALS["apcsnmp"], $mib, "i", $action);
  return $VERIFY;
}

function setAPCPortName($apcid, $apcport, $name) {
  $ip = $GLOBALS["apcids"][$apcid]["ip"];
  $mib = $GLOBALS["apc"]["portnamemib"].".".$apcport;
  $VERIFY = snmpset($ip, $GLOBALS["apcsnmp"], $mib, "s", $name);
  if ($VERIFY) {
    $_SESSION[$apcid]["names"][$apcport]=$name;
  }
  return $VERIFY;
}

function setAPCPortTimer($apcid, $apcport, $timer) {
  $ip = $GLOBALS["apcids"][$apcid]["ip"];
  $mib = $GLOBALS["apc"]["porttimermib"].".".$apcport;
  $VERIFY = snmpset($ip, $GLOBALS["apcsnmp"], $mib, "i", $timer);
  if ($VERIFY) {
    $_SESSION[$apcid]["timer"][$apcport]=$timer;
  }
  return $VERIFY;
}

// display port's status
function getPortStatus($apcid,$port,$now=false) {
//  if (array_key_exists($apcid.$port,$_SESSION["renew"])) {
//    if ($_SESSION["renew"][$apcid.$port]+$GLOBALS["renewsecs"] < time())
//      $now=true;
//    else
//      unset($_SESSION["renew"][$apcid.$port]);
//  }
  $portstatus=getPDUPort($apcid,$port,$now);
  $status=(int)$portstatus[0];
  switch ($status) {
    case 1:
      return "<span class=\"status ison\">On</span>";
    case 2:
      return "<span class=\"status isoff\">Off</span>"; 
    case 3:
      return "<span class=\"status isrebooting\">Rebooting</span>"; 
    default:
      return "<span class=\"status iserror\">Error ($status)</span>"; 
  }
}

// display HTML SELECT for a port
function selectforPort($apcid,$port,$now=false) {
  echo "<select name=\"".$apcid."_".$port."_action\">";
  $portstatus=getPDUPort($apcid,$port,$now);
  $status=(int)$portstatus[0];
  for ($o=1;$o<8;$o++) {
    if ($o==$status) { 
      echo "<option value=\"".$o."\" disabled=\"disabled\" selected=\"selected\">Action</option>";
    } elseif ($o == 7) {
      if (($status < 7)&&($status > 3))
        echo "<option value=\"".$o."\">".$GLOBALS["states"][$o]."</option>";
    } else {
      echo "<option value=\"".$o."\">".$GLOBALS["states"][$o]."</option>";
    }
  }
  echo "</select>";
}

// same for port timer
function selectPortTimer($apcid,$port,$now=false) {
  echo "<select name=\"".$apcid."_".$port."_timer\">";
  $portstatus=getPDUPort($apcid,$port,$now);
  // valeurs possibles des timers
  $tms=array(5,60,120,240,300,480);
  // decalees (+n° prise + n° port)
  foreach ($tms as $tm) {
    $timers[]=strval($tm).strval($apcid).strval($port);
  }
  $timer=(int)$portstatus[1];
  // on ajoute l'option pour ne rien changer
  if (!in_array($timer,$timers)) {
    echo "<option value=\"".$timer."\" disabled=\"disabled\" selected=\"selected\">".$timer."s</option>";
  }
  foreach ($timers as $secs) {
    $secs=(int)$secs+(int)$apcid*2;
    if ($secs==$timer) {
      echo "<option value=\"".$secs."\" disabled=\"disabled\" selected=\"selected\">".$secs."s</option>";
    } else {
      echo "<option value=\"".$secs."\">".$secs."s</option>";
    }
  }
  echo "</select>";
}
