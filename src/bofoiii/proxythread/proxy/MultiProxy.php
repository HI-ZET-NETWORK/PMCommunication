<?php

declare(strict_types=1);

namespace bofoiii\proxythread\proxy;

use ArrayIterator;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use bofoiii\proxythread\event\ProxyReceiveDataEvent;
use bofoiii\proxythread\exception\ProxyException;
use bofoiii\proxythread\thread\ProxyThread;
use pmmp\thread\Thread;
use ThreadedArray;

class MultiProxy extends Proxy{
	/**
	 * @phpstan-var array<string, ProxyThread>
	 * @var ProxyThread[]
	 */
	private array $threads = [];

	/**
	 * @phpstan-var array<string, ThreadedArray>
	 * @var ThreadedArray[]
	 */
	private array $volatiles = [];

	public function initialize(Plugin $plugin) : void{
		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void{
			foreach($this->volatiles as $key => $volatile){
				while($volatile->count() > 0){
					$chunk = $volatile->shift();
					(new ProxyReceiveDataEvent($this, new ArrayIterator((array)$chunk)))->call();
				}
			}
		}), 2);
	}

	public function insert(string $key, ProxyThread $thread): void{
		$this->volatiles[$key] = $thread->getReceiveQueue();
		$this->threads[$key] = $thread;
		$thread->start(Thread::INHERIT_ALL);
	}

	public function close(): void{
		foreach($this->threads as $key => $thread){
			$this->delete($key);
		}
	}

	public function delete(string $key): void{
		if(!isset($this->threads[$key])){
			throw ProxyException::wrap("No proxy found with key $key");
		}

		($proxy = $this->threads[$key])->shutdown();
		while($proxy->isRunning()){
		}
		unset($this->threads[$key], $this->volatiles[$key]);
	}

	public function select(string $key): ?ProxyThread{
		return $this->threads[$key] ?? null;
	}
}