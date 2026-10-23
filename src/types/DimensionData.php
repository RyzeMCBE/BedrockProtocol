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
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class DimensionData{

	public function __construct(
		private int $maxHeight,
		private int $minHeight,
		private int $generator,
		private int $dimensionType,
		private ?UuidInterface $packId = null,
		private string $defaultBiome = "minecraft:plains",
	){}

	public function getMaxHeight() : int{ return $this->maxHeight; }

	public function getMinHeight() : int{ return $this->minHeight; }

	public function getGenerator() : int{ return $this->generator; }

	public function getDimensionType() : int{ return $this->dimensionType; }

	public function getPackId() : ?UuidInterface{ return $this->packId; }
	public function getDefaultBiome() : string{ return $this->defaultBiome; }

	public static function read(ByteBufferReader $in) : self{
		$minHeight = VarInt::readSignedInt($in);
		$heightRange = VarInt::readSignedInt($in);
		$maxHeight = $minHeight + $heightRange;
		$generator = VarInt::readSignedInt($in);
		$dimensionType = VarInt::readSignedInt($in);
		$packId = CommonTypes::getUUID($in);
		$defaultBiome = CommonTypes::getString($in);

		return new self($maxHeight, $minHeight, $generator, $dimensionType, $packId, $defaultBiome);
	}

	public function write(ByteBufferWriter $out) : void{
		VarInt::writeSignedInt($out, $this->minHeight);
		VarInt::writeSignedInt($out, $this->maxHeight - $this->minHeight);
		VarInt::writeSignedInt($out, $this->generator);
		VarInt::writeSignedInt($out, $this->dimensionType);
		CommonTypes::putUUID($out, $this->packId ?? Uuid::fromString(Uuid::NIL));
		CommonTypes::putString($out, $this->defaultBiome);
	}
}
