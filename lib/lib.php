<?php

// update SNMP infos in session for a rack
function getPDU($apcid,$port=0,$renew=false) {
  // si c'est la premiere fois, on force la mise a jour
  if (!array_key_exists($apcid,$_SESSION)) {
    $_SESSION[$apcid]=array();
    $_SESSION[$apcid]["names"]=array();
    $_SESSION[$apcid]["control"]=array();
    $renew=true;
  }
  // si ?renew=qqch, on force
  if (array_key_exists("renew",$_GET))
    $renew=true;

  if (!array_key_exists(1,$_SESSION[$apcid]["names"])) {
    $resn=preg_replace('/^(STRING|INTEGER): "(.*)"$/','$2',snmp2_walk($GLOBALS["apcids"][$apcid],$GLOBALS["apcsnmp"],$GLOBALS["apcportnamemib"]));
    foreach ($resn as $k => $v)
      $_SESSION[$apcid]["names"][$k+1]=$v;
  }
  // si un seul port est demande, on le donne
  if ($port>1) {
    if ($renew)
      $_SESSION[$apcid]["control"][$port]=trim(snmpget($GLOBALS["apcids"][$apcid],$GLOBALS["apcsnmp"],$GLOBALS["apcportcontrolmib"].".".$port),"INTEGER: ");
    return $_SESSION[$apcid]["control"][$port];
  } 
  // on met a jour si demande ou si c'est la premiere fois
  if (($renew)||(!array_key_exists(1,$_SESSION[$apcid]["control"]))) {
    $resc=preg_replace('/^(STRING|INTEGER): /','',snmp2_walk($GLOBALS["apcids"][$apcid],$GLOBALS["apcsnmp"],$GLOBALS["apcportcontrolmib"]));
    if ($resc)
      foreach ($resc as $k => $v)
        $_SESSION[$apcid]["control"][$k+1]=$v;
  }
  return $_SESSION[$apcid];
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
  $ip = $GLOBALS["apcids"][$apcid];
  $mib = $GLOBALS["apcportcontrolmib"].".".$apcport;
  $VERIFY = snmpset($ip, $GLOBALS["apcsnmp"], $mib, "i", $action);
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
  $status=(int)getPDU($apcid,$port,$now);
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
function selectforPort($apcid,$port) {
  echo "<select name=\"".$apcid."_".$port."\">";
  $status=(int)getPDU($apcid,$port);
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

