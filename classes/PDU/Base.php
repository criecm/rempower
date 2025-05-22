<?php
namespace Dsiecm\Rempower\PDU;
use \SNMP;
abstract class Base {

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
	public array $ports;

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
	abstract function getPortStatus($portnum,$refresh);

	// returns port state
	abstract function controlPort($portnum,$state);

	// returns bool
	abstract function setPortName($portnum,$name);

	// retunr int(secs)
	abstract function getPortDelay($portnum,$refresh);
	// returns bool
	abstract function setPortDelay($portnum,$timer);
}
