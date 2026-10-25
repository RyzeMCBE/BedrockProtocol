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
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

class MoveActorDeltaPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::MOVE_ACTOR_DELTA_PACKET;

	public const FLAG_HAS_X = 0x01;
	public const FLAG_HAS_Y = 0x02;
	public const FLAG_HAS_Z = 0x04;
	public const FLAG_HAS_PITCH = 0x08;
	public const FLAG_HAS_YAW = 0x10;
	public const FLAG_HAS_HEAD_YAW = 0x20;
	public const FLAG_GROUND = 0x40;
	public const FLAG_TELEPORT = 0x80;
	public const FLAG_FORCE_MOVE_LOCAL_ENTITY = 0x100;
	public const FLAG_FORCE_COMPLETION = 0x200;

	public int $actorRuntimeId;
	public int $flags;
	public int $ticks = 0;
	public float $xPos = 0;
	public float $yPos = 0;
	public float $zPos = 0;
	public float $pitch = 0.0;
	public float $yaw = 0.0;
	public float $headYaw = 0.0;

	/** @throws DataDecodeException */
	private function readModernComponent(ByteBufferReader $in, int $flag, bool $rotation = false) : float{
		$value = CommonTypes::readOptional($in, $rotation ? CommonTypes::getRotationByte(...) : LE::readFloat(...));
		if($value !== null){
			$this->flags |= $flag;
		}
		return $value ?? 0.0;
	}

	protected function decodePayload(ByteBufferReader $in) : void{
		$this->actorRuntimeId = CommonTypes::getActorRuntimeId($in);
		$this->flags = 0;
		$this->xPos = $this->readModernComponent($in, self::FLAG_HAS_X);
		$this->yPos = $this->readModernComponent($in, self::FLAG_HAS_Y);
		$this->zPos = $this->readModernComponent($in, self::FLAG_HAS_Z);
		$this->pitch = $this->readModernComponent($in, self::FLAG_HAS_PITCH, true);
		$this->yaw = $this->readModernComponent($in, self::FLAG_HAS_YAW, true);
		$this->headYaw = $this->readModernComponent($in, self::FLAG_HAS_HEAD_YAW, true);
		foreach([self::FLAG_GROUND, self::FLAG_TELEPORT, self::FLAG_FORCE_MOVE_LOCAL_ENTITY, self::FLAG_FORCE_COMPLETION] as $flag){
				if(CommonTypes::getBool($in)){
					$this->flags |= $flag;
				}
			}
		$this->ticks = VarInt::readUnsignedLong($in);
		return;
	}

	private function writeModernComponent(ByteBufferWriter $out, int $flag, float $value, bool $rotation = false) : void{
		CommonTypes::writeOptional(
			$out,
			($this->flags & $flag) !== 0 ? $value : null,
			$rotation ? CommonTypes::putRotationByte(...) : LE::writeFloat(...)
		);
	}

	protected function encodePayload(ByteBufferWriter $out) : void{
		CommonTypes::putActorRuntimeId($out, $this->actorRuntimeId);
		$this->writeModernComponent($out, self::FLAG_HAS_X, $this->xPos);
		$this->writeModernComponent($out, self::FLAG_HAS_Y, $this->yPos);
		$this->writeModernComponent($out, self::FLAG_HAS_Z, $this->zPos);
		$this->writeModernComponent($out, self::FLAG_HAS_PITCH, $this->pitch, true);
		$this->writeModernComponent($out, self::FLAG_HAS_YAW, $this->yaw, true);
		$this->writeModernComponent($out, self::FLAG_HAS_HEAD_YAW, $this->headYaw, true);
		foreach([self::FLAG_GROUND, self::FLAG_TELEPORT, self::FLAG_FORCE_MOVE_LOCAL_ENTITY, self::FLAG_FORCE_COMPLETION] as $flag){
				CommonTypes::putBool($out, ($this->flags & $flag) !== 0);
			}
		VarInt::writeUnsignedLong($out, $this->ticks);
		return;
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleMoveActorDelta($this);
	}
}
