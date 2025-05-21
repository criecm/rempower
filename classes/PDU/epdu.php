<?php
namespace Dsiecm\Rempower\PDU;
//use \Dsiecm\Rempower\Utils\Str;
//use Dsiecm\Rempower\PDU\Base;
//use function \Dsiecm\Rempower\Utils\Str\stripSNMPvalue;
use \SNMP;
class epdu extends Base {

        private static string $portstatusmib = ".1.3.6.1.4.1.534.6.6.7.6.6.1.2.0";
        private static string $portnamemib = ".1.3.6.1.4.1.534.6.6.7.6.1.1.3.0";
        private static string $portoffmib = ".1.3.6.1.4.1.534.6.6.7.6.6.1.3.0";
        private static string $portonmib = ".1.3.6.1.4.1.534.6.6.7.6.6.1.4.0";
        private static string $portrebootmib = ".1.3.6.1.4.1.534.6.6.7.6.6.1.5.0";
        private static string $porttimermib = ".1.3.6.1.4.1.534.6.6.7.6.6.1.7.0";
	private static $SNMPVER = SNMP::VERSION_1;

	public function __construct($pduid,$ip,$community="public",$renew=false) {
		$this->ip = $ip;
		$this->name = $pduid;
		$this->renew = true;
		$this->snmp = new SNMP($this::$SNMPVER,$this->ip,$community);
		$this->snmp->valueretrieval = SNMP_VALUE_PLAIN;
		if ( ! array_key_exists($pduid,$_SESSION) ) {
			$_SESSION[$pduid] = array();
		}
		if ( array_key_exists("names",$_SESSION[$pduid]) && ($renew === false) ) {
			$this->ports = $_SESSION[$pduid];
		} else {
			$this->ports = array("names" => array(), "states" => array(), "timers" => array());
			$this->getAllPorts();
		}
	}

	public function getAllPorts() {
		$this->ports["names"] = $this->snmp->walk(epdu::$portnamemib,TRUE);
		$states = $this->ports["states"] = $this->snmp->walk(epdu::$portstatusmib,TRUE);
		foreach ($states as $portid => $state) {
			$this->ports["states"][$portid] = $this->epduStateToWebapc($state);
		}
		$this->ports["timers"] = $this->snmp->walk(epdu::$porttimermib,TRUE);
		return $this->ports;
	}

	private function epduStateToWebapc($state) {
		switch ($state) {
			case 1: // on
			case 3: // pendingOn
				return 1;
				break;
			case 2: // pendingOff
			case 0: // off
				return 2;
				break;
			default:
				return FALSE;
				break;
		}
	}
		
	public function getPortStatus($portnum,$now=false) {
		if (!array_key_exists($portnum,$this->ports["states"]) || ($now)) {
			$status = $this->snmp->get(epdu::$portstatusmib.".".$portnum);
			$this->ports["states"][$portnum] = $this->epduStateToWebapc($status);
		}
		return $this->ports["states"][$portnum];
	}

	public function controlPort($portnum,$state,$delay=0) {
		$mib = "";
		switch ($state) {
		case 1:
			$mib = epdu::$portonmib . ".$portnum";
			break;
		case 2:
			$mib = epdu::$portoffmib . ".$portnum";
			break;
		case 3:
			$mib = epdu::$portrebootmib . ".$portnum";
			break;
		default:
			return false;
		}
		if ($this->snmp->set($mib,"i",$delay) == TRUE) {
			$this->ports["states"][$portnum] = $state;
			return true;
		} else {
			//debug($this->snmp->getError());
			return false;
		}
	}

	public function setPortName($portnum,$name) {
		if ($this->snmp->set(epdu::$portnamemib.".".$portnum,"s",$name) == TRUE) {
			$this->ports["names"][$portnum] = $name;
			return true;
		} else {
			error_log($this->snmp->getError());
			return false;
		}
	}

	public function setPortDelay($portnum,$timer) {
		if ($this->snmp->set(epdu::$porttimermib.".".$portnum,"i",$timer) == TRUE) {
			$this->ports["timers"][$portnum] = $timer;
			return true;
		} else {
			error_log($this->snmp->getError());
			return false;
		}
	}
}
