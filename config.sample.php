<?php
// This is your SNMP Write+ String
$GLOBALS["apcsnmp"] = "snmpwritestring";

// translates APC IDs into their IP addresses
$GLOBALS["apcids"] = array(
	"APC1" => array(
		"ip" => "xxx.xxx.xxx.xxx",
		"type" => "apc"
	),
	"APC2" => array(
		"ip" => "xxx.xxx.xxx.xxx",
		"type" => "epdu"
	)
);

$GLOBALS["mailto"] = "my@mail.address";
// timer's bases to configure (+ pdu nr + port nr to avoid starting all at once)
$GLOBALS["timers"] = array(5,60,120,240,300,480);

// After reboot or delayed action, for how much seconds we must force getting 
//  the port status via SNMP (aka not use cached status)
$GLOBALS["renewsecs"] = 30;

// debug ?
$GLOBALS['debug']=false;

