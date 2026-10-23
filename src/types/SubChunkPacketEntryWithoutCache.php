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

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class SubChunkPacketEntryWithoutCache{

	public function __construct(
		private SubChunkPacketEntryCommon $base
	){}

	public function getBase() : SubChunkPacketEntryCommon{ return $this->base; }

	public static function read(ByteBufferReader $in) : self{
		$base = SubChunkPacketEntryCommon::read($in, false);
		if(CommonTypes::readOptional($in, LE::readUnsignedLong(...)) !== null){
			throw new PacketDecodeException("Uncached subchunk entry has a blob hash");
		}
		return new self($base);
	}

	public function write(ByteBufferWriter $out) : void{
		$this->base->write($out, false);
		CommonTypes::putBool($out, false);
	}
}
