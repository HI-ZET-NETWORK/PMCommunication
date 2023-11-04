<?php

declare(strict_types=1);

namespace bofoiii\proxythread\event;

use ArrayIterator;
use pocketmine\event\Event;
use bofoiii\proxythread\proxy\Proxy;

abstract class ProxyEvent extends Event{
	public function __construct(
		private readonly Proxy $proxy,
		private readonly ArrayIterator $iterator
	){}

	public function getProxy(): Proxy{
		return $this->proxy;
	}

	public function getIterator(): ArrayIterator{
		return $this->iterator;
	}
}