<?php
namespace Dsiecm\Rempower\PDU;
use \SNMP;
abstract class Base {

	private array $ports;
	protected string $ip;
	protected bool $renew;
	protected string $name;
	protected string $community = "public";
	protected array $states = array(
		0 => "error",
		1 => "on",
		2 => "off",
		3 => "reboot"
	);
	private static $SNMPVER;
	protected $snmp;

//	public function __construct($pduid,$ip,$renew=false) {
//		$this->ip = $ip;
//		$this->name = $pduid;
//		$this->renew = true;
//		$this->snmp = new SNMP($this::$SNMPVER,$this->ip,$this->community);
//		$this->snmp->valueretrieval = SNMP_VALUE_PLAIN;
//		if ( ! array_key_exists($pduid,$_SESSION) ) {
//			$_SESSION[$pduid] = array();
//		}
//		if ( array_key_exists("names",$_SESSION[$pduid]) && ($renew === false) ) {
//			$this->ports = $_SESSION[$pduid];
//		} else {
//			$this->ports = array("names" => array(), "states" => array(), "timers" => array());
//			$this->getAllPorts();
//		}
//	}

	public function getStateName($state_id) {
		if (array_key_exists($state_id,$this->states)) {
			return $this->states[$state_id];
		} else {
			return "UNKNOWN STATE $state_id";
		}
	}
	public function getAllowedStates() {
		$mystates = $this->states;
		unset($mystates[0]);
		return $mystates;
	}
	public function setCommunity($community) {
		$this->community = "$community";
	}

	// returns $this->ports
	abstract function getAllPorts();

	// returns port state (from $states)
	abstract function getPortStatus($portnum);

	// returns port state
	abstract function controlPort($portnum,$state);

	// returns bool
	abstract function setPortName($portnum,$name);

	// returns bool
	abstract function setPortDelay($portnum,$timer);
}
