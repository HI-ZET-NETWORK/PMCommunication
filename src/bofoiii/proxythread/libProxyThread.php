<?php

declare(strict_types=1);

namespace bofoiii\proxythread;

use pocketmine\plugin\Plugin;
use bofoiii\proxythread\proxy\MultiProxy;

final class libProxyThread{

	public static function createMultiProxy(Plugin $plugin, string $address): MultiProxy{
		return new MultiProxy($plugin, $address);
	}
	
}