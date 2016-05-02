<?php
// This is your SNMP Write+ String
$GLOBALS["apcsnmp"] = "snmpwritestring";

// translates APC IDs into their IP addresses
$GLOBALS["apcids"] = array(
  "APC1" => "xxx.xxx.xxx.xxx",
  "APC2" => "xxx.xxx.xxx.xxx",
);

$GLOBALS["states"] = array(
  1 => "immediateOn",
  2 => "immediateOff",
  3 => "immediateReboot",
  4 => "delayedOn",
  5 => "delayedOff",
  6 => "delayedReboot",
  7 => "cancelPendingCommand");

// This APC MIB is incomplete - must add the port number of the PDU at the end
$GLOBALS["apcportcontrolmib"] = ".1.3.6.1.4.1.318.1.1.12.3.3.1.1.4";
$GLOBALS["apcportnamemib"] = ".1.3.6.1.4.1.318.1.1.4.5.2.1.3";
$GLOBALS["apcporttimermib"] = ".1.3.6.1.4.1.318.1.1.12.3.4.1.1.4";

// After reboot or delayed action, for how much seconds we must force getting 
//  the port status via SNMP (aka not use cached status)
$GLOBALS["renewsecs"] = 30;

// debug ?
$GLOBALS['debug']=false;

