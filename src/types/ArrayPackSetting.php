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
use function count;

final class ArrayPackSetting extends PackSetting{
	/** @param list<string> $values */
	public function __construct(string $name, private array $values){
		parent::__construct($name);
	}
	/** @return list<string> */
	public function getValues() : array{ return $this->values; }
	public function getTypeId() : PackSettingType{ return PackSettingType::ARRAY; }
	public static function read(ByteBufferReader $in, string $name) : self{
		$values = [];
		for($i = 0,$n = VarInt::readUnsignedInt($in);$i < $n;$i++) $values[] = CommonTypes::getString($in);
		return new self($name,$values);
	}
	public function write(ByteBufferWriter $out) : void{
		VarInt::writeUnsignedInt($out,count($this->values));
		foreach($this->values as $v) CommonTypes::putString($out,$v);
	}
}
