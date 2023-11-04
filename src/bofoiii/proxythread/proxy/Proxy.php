<?php

declare(strict_types=1);

namespace bofoiii\proxythread\proxy;

use pocketmine\plugin\Plugin;

abstract class Proxy{
	public function __construct(
		Plugin $plugin,
		private string $address
	){
		$this->initialize($plugin);
	}

	public function getAddress(): string{
		return $this->address;
	}

	abstract public function initialize(Plugin $plugin): void;
}