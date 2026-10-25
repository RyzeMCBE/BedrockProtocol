<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;

class RecordStartedPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::RECORD_STARTED_PACKET;

	public int $x;
	public int $y;
	public int $z;
	public int $serverSoundHandle;

	/** @generate-create-func */
	public static function create(int $x, int $y, int $z, int $serverSoundHandle) : self{
		$result = new self;
		$result->x = $x;
		$result->y = $y;
		$result->z = $z;
		$result->serverSoundHandle = $serverSoundHandle;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in) : void{
		$this->x = \pmmp\encoding\VarInt::readSignedInt($in);
		$this->y = \pmmp\encoding\VarInt::readSignedInt($in);
		$this->z = \pmmp\encoding\VarInt::readSignedInt($in);
		$this->serverSoundHandle = LE::readUnsignedLong($in);
	}

	protected function encodePayload(ByteBufferWriter $out) : void{
		\pmmp\encoding\VarInt::writeSignedInt($out, $this->x);
		\pmmp\encoding\VarInt::writeSignedInt($out, $this->y);
		\pmmp\encoding\VarInt::writeSignedInt($out, $this->z);
		LE::writeUnsignedLong($out, $this->serverSoundHandle);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleRecordStarted($this);
	}
}
