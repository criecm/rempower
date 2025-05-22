<?php
namespace Dsiecm\Rempower\PDU;
//use \Dsiecm\Rempower\Utils\Str;
//use Dsiecm\Rempower\PDU\Base;
use \SNMP;
use function \Dsiecm\Rempower\Utils\Str\stripSNMPvalue;
class apc extends Base {

        private static string $portnamemib = ".1.3.6.1.4.1.318.1.1.4.5.2.1.3";
        private static string $portcontrolmib = ".1.3.6.1.4.1.318.1.1.12.3.3.1.1.4";
        private static string $porttimermib = ".1.3.6.1.4.1.318.1.1.12.3.4.1.1.4";
	private static $SNMPVER = SNMP::VERSION_2C;

	public function __construct($pduid,$ip,$community="public",$renew=false) {
		$this->ip = $ip;
		$this->name = $pduid;
		$this->renew = true;
		$this->snmp = new SNMP($this::$SNMPVER,$this->ip,$community);
		$this->snmp->exceptions_enabled=SNMP::ERRNO_ANY;
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
                $this->ports["names"] = $this->snmp->walk(apc::$portnamemib,TRUE);
                $this->ports["states"] = $this->snmp->walk(apc::$portcontrolmib,TRUE);
                $this->ports["timers"] = $this->snmp->walk(apc::$porttimermib,TRUE);
		return $this->ports;
	}

	public function getPortStatus($portnum,$now=false) {
		if (!array_key_exists($portnum,$this->ports["states"]) || ($now)) {
			$this->ports["states"][$portnum] = $this->snmp->get(apc::$portcontrolmib.".".$portnum);
		}
		return $this->ports["states"][$portnum];
	}

	public function controlPort($portnum,$state,$delay=0) {
		if ($this->snmp->set(apc::$portcontrolmib.".".$portnum,"i",$state) == TRUE) {
			$this->ports["states"][$portnum] = $state;
			return true;
		} else {
			return false;
		}
	}

	public function setPortName($portnum,$name) {
		if ($this->snmp->set(apc::$portnamemib.".".$portnum,"s",$name) == TRUE) {
			$this->ports["names"][$portnum] = $name;
			return true;
		} else {
			error_log($this->snmp->getError());
			return false;
		}
	}

	public function getPortDelay($portnum,$now=false) {
		if (!array_key_exists($portnum,$this->ports["timers"]) || ($now)) {
			$timer = $this->snmp->get(apc::$porttimermib.".".$portnum);
			$this->ports["timers"][$portnum] = $timer;
		}
		return $this->ports["timers"][$portnum];
	}

	public function setPortDelay($portnum,$timer) {
		if ($this->snmp->set(apc::$porttimermib.".".$portnum,"i",$timer) == TRUE) {
			$this->ports["timers"][$portnum] = $timer;
			return true;
		} else {
			error_log($this->snmp->getError());
			return false;
		}
	}
}
