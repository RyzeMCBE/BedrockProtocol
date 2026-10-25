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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackresponse;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function count;

final class ItemStackResponse{

	public const RESULT_OK = 0;
	public const RESULT_ERROR = 1;
	//TODO: there are a ton more possible result types but we don't need them yet and they are wayyyyyy too many for me
	//to waste my time on right now...

	/**
	 * @param ItemStackResponseContainerInfo[] $containerInfos
	 */
	public function __construct(
		private int $result,
		private int $requestId,
		private array $containerInfos = []
	){
		if($this->result !== self::RESULT_OK && count($this->containerInfos) !== 0){
			throw new \InvalidArgumentException("Container infos must be empty if rejecting the request");
		}
	}

	public function getResult() : int{ return $this->result; }

	public function getRequestId() : int{ return $this->requestId; }

	/** @return ItemStackResponseContainerInfo[] */
	public function getContainerInfos() : array{ return $this->containerInfos; }

	public static function read(ByteBufferReader $in) : self{
		$result = Byte::readUnsigned($in);
		$requestId = CommonTypes::readItemStackRequestId($in);
		$containerInfos = CommonTypes::readOptional($in, static fn(ByteBufferReader $in) : array => CommonTypes::readList(
					$in,
					static fn(ByteBufferReader $in) => ItemStackResponseContainerInfo::read($in)
				));
		if($result !== self::RESULT_OK && $containerInfos !== null){
				throw new \InvalidArgumentException("Container infos must be null if rejecting the request");
			}
		return new self($result, $requestId, $containerInfos ?? []);
	}

	public function write(ByteBufferWriter $out) : void{
		Byte::writeUnsigned($out, $this->result);
		CommonTypes::writeItemStackRequestId($out, $this->requestId);
		$containerInfos = $this->result === self::RESULT_OK ? $this->containerInfos : null;
		CommonTypes::writeOptional($out, $containerInfos, static fn(ByteBufferWriter $out, array $list) => CommonTypes::writeList(
					$out,
					$list,
					static fn(ByteBufferWriter $out, ItemStackResponseContainerInfo $info) => $info->write($out)
				));
		return;
	}
}
