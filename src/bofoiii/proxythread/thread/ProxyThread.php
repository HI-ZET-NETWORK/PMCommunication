<?php

declare(strict_types=1);

namespace bofoiii\proxythread\thread;

use InvalidArgumentException;
use pocketmine\thread\ThreadException;
use pmmp\thread\Thread;
use pmmp\thread\ThreadSafeArray;
use Socket;
use function is_array;
use function json_decode;
use function json_encode;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_sendto;
use function socket_set_nonblock;

final class ProxyThread extends Thread{

	public const KEY_TARGET = "target";
	public const KEY_IDENTIFY = "identify";
	public const KEY_DATA = "data";

	private bool $shutdown = false;

	private ThreadSafeArray $sendQueue;
	private ThreadSafeArray $sendWithTarget;
	private ThreadSafeArray $receiveQueue;

	public function __construct(
		private int $port,
		private ThreadSafeArray $targetPorts, //multi-proxy-socket
		?ThreadSafeArray $sendQueue = null,
		?ThreadSafeArray $sendWithTarget = null,
		?ThreadSafeArray $receiveQueue = null
	){
		$this->sendQueue = $sendQueue ?? new ThreadSafeArray();
		$this->sendWithTarget = $sendWithTarget ?? new ThreadSafeArray();
		$this->receiveQueue = $receiveQueue ?? new ThreadSafeArray();
	}

	public function shutdown() : void{
		$this->shutdown = true;
	}

	public function getTargetPorts(): ThreadSafeArray
	{
		return $this->targetPorts;
	}

	public function getReceiveQueue() : ThreadSafeArray{
		return $this->receiveQueue;
	}

	public function run(): void{
		$receiveSocker = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_nonblock($receiveSocker);

		if($receiveSocker === false){
			throw new InvalidArgumentException("Failed to create socket");
		}

		if(socket_bind($receiveSocker, "0.0.0.0", $this->port) === false){
			throw new InvalidArgumentException("Failed to bind port (bindPort: $this->port)");
		}

		$sendSocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if($sendSocket === false){
			throw new InvalidArgumentException("Failed to create socket");
		}
		
		while(!$this->shutdown){
			$this->receiveData($receiveSocker);
			$this->sendData($sendSocket);
		}

		socket_close($sendSocket);
		socket_close($receiveSocker);
	}

	private function receiveData(Socket $receiveSocker) : void{
		$buffer = "";
		if(socket_recvfrom($receiveSocker, $buffer, 65535, 0, $source, $port) === false){
			$errno = socket_last_error($receiveSocker);
			if($errno === SOCKET_EWOULDBLOCK){
				return;
			}
			throw new ThreadException("Failed received");
		}

		if($buffer !== null && $buffer !== ""){
			
			$data = json_decode($buffer, true);
			if(!is_array($data) || !isset($data[self::KEY_IDENTIFY], $data[self::KEY_DATA])){
				return;
			}

			$data = ThreadSafeArray::fromArray($data);
			$this->receiveQueue[] = $data;
		}
	}

	private function sendData(Socket $sendSocket): void
	{
		while($this->sendQueue->count() > 0){
			$chunk = $this->sendQueue->shift();
			if(!$chunk->offsetExists(self::KEY_IDENTIFY) || !$chunk->offsetExists(self::KEY_DATA)) {
				continue;
			}

			foreach ((array) $this->targetPorts as $port) {
				socket_sendto($sendSocket, json_encode((array) $chunk), 65535, 0, "127.0.0.1", $port);
			}
		}

		while($this->sendWithTarget->count() > 0) {
			$chunk = $this->sendWithTarget->shift();
			$target = $chunk->offsetGet(self::KEY_TARGET);
			$data = $chunk->offsetGet(self::KEY_DATA);
			if (in_array($target, (array) $this->targetPorts)) {
				socket_sendto($sendSocket, json_encode((array) $data), 65535, 0, "127.0.0.1", $target);
			}
		}
	}

	public function sendTo(int $port, array $data) : void
	{
		$new = ThreadSafeArray::fromArray([
			self::KEY_TARGET => $port,
			self::KEY_DATA => $data
		]);

		$this->sendWithTarget[] = $new;
	}

	public function send(array $data) : void{
		$new = ThreadSafeArray::fromArray($data);
		$this->sendQueue[] = $new;
	}
}