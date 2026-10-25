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
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class ServerJoinInformation{

	public function __construct(
		private ?GatheringJoinInfo $gatheringJoinInfo,
		private ?StoreEntryPointInfo $storeEntryPointInfo,
		private ?PresenceInfo $presenceInfo,
	){}

	public function getGatheringJoinInfo() : ?GatheringJoinInfo{ return $this->gatheringJoinInfo; }

	public function getStoreEntryPointInfo() : ?StoreEntryPointInfo{ return $this->storeEntryPointInfo; }

	public function getPresenceInfo() : ?PresenceInfo{ return $this->presenceInfo; }

	public static function read(ByteBufferReader $in) : self{
		$storeEntryPointInfo = null;

		$gatheringJoinInfo = CommonTypes::readOptional($in, function(ByteBufferReader $in) : GatheringJoinInfo{
			$gatheringJoinInfo = GatheringJoinInfo::read($in);

			return $gatheringJoinInfo;
		});
		$storeEntryPointInfo = CommonTypes::readOptional($in, StoreEntryPointInfo::read(...));
		$presenceInfo = CommonTypes::readOptional($in, fn(ByteBufferReader $in) => PresenceInfo::read($in));

		return new self(
			$gatheringJoinInfo,
			$storeEntryPointInfo,
			$presenceInfo ?? null,
		);
	}

	public function write(ByteBufferWriter $out) : void{
		CommonTypes::writeOptional($out, $this->gatheringJoinInfo, function (ByteBufferWriter $out, GatheringJoinInfo $info) : void {
			$info->write($out);
		});
		CommonTypes::writeOptional($out, $this->storeEntryPointInfo, fn(ByteBufferWriter $out, StoreEntryPointInfo $info) => $info->write($out));
		CommonTypes::writeOptional($out, $this->presenceInfo, fn(ByteBufferWriter $out, PresenceInfo $info) => $info->write($out));
	}
}
