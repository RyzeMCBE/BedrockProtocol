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
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use function count;
use const PHP_INT_MAX;

class LevelChunkPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::LEVEL_CHUNK_PACKET;

	//this appears large enough for a world height of 1024 blocks - it may need to be increased in the future
	private const MAX_BLOB_HASHES = 64;

	private ChunkPosition $chunkPosition;
	/** @phpstan-var DimensionIds::* */
	private int $dimensionId;
	private int $subChunkCount;
	private bool $clientSubChunkRequestsEnabled;
	/** @var int[]|null */
	private ?array $usedBlobHashes = null;
	private string $extraPayload;

	/**
	 * @generate-create-func
	 * @param int[] $usedBlobHashes
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public static function create(ChunkPosition $chunkPosition, int $dimensionId, int $subChunkCount, bool $clientSubChunkRequestsEnabled, ?array $usedBlobHashes, string $extraPayload) : self{
		$result = new self;
		$result->chunkPosition = $chunkPosition;
		$result->dimensionId = $dimensionId;
		$result->subChunkCount = $subChunkCount;
		$result->clientSubChunkRequestsEnabled = $clientSubChunkRequestsEnabled;
		$result->usedBlobHashes = $usedBlobHashes;
		$result->extraPayload = $extraPayload;
		return $result;
	}

	public function getChunkPosition() : ChunkPosition{ return $this->chunkPosition; }

	public function getDimensionId() : int{ return $this->dimensionId; }

	public function getSubChunkCount() : int{
		return $this->subChunkCount;
	}

	public function isClientSubChunkRequestsEnabled() : bool{
		return $this->clientSubChunkRequestsEnabled;
	}

	/** @deprecated incorrect name */
	public function isClientSubChunkRequestEnabled() : bool{
		return $this->clientSubChunkRequestsEnabled;
	}

	public function isCacheEnabled() : bool{
		return $this->usedBlobHashes !== null;
	}

	/**
	 * @return int[]|null
	 */
	public function getUsedBlobHashes() : ?array{
		return $this->usedBlobHashes;
	}

	public function getExtraPayload() : string{
		return $this->extraPayload;
	}

	protected function decodePayload(ByteBufferReader $in) : void{
		$this->chunkPosition = ChunkPosition::read($in);
		$this->dimensionId = VarInt::readSignedInt($in);
		$this->subChunkCount = VarInt::readUnsignedInt($in);
		$subChunkRequestLimit = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
		$this->clientSubChunkRequestsEnabled = $subChunkRequestLimit !== null;
		$cacheEnabled = CommonTypes::getBool($in);
		$count = VarInt::readUnsignedInt($in);
		if($count > self::MAX_BLOB_HASHES){
				throw new PacketDecodeException("Expected at most " . self::MAX_BLOB_HASHES . " blob hashes, got " . $count);
			}
		$blobHashes = [];
		for($i = 0; $i < $count; ++$i){
				$blobHashes[] = LE::readUnsignedLong($in);
			}
		$this->usedBlobHashes = $cacheEnabled ? $blobHashes : null;
		$this->extraPayload = CommonTypes::getString($in);
		return;
	}

	protected function encodePayload(ByteBufferWriter $out) : void{
		$this->chunkPosition->write($out);
		VarInt::writeSignedInt($out, $this->dimensionId);
		$subChunkCount = $this->subChunkCount === PHP_INT_MAX ? 0 : $this->subChunkCount;
		VarInt::writeUnsignedInt($out, $subChunkCount);
		$subChunkRequestLimit = $this->clientSubChunkRequestsEnabled ? $subChunkCount : null;
		CommonTypes::writeOptional($out, $subChunkRequestLimit, VarInt::writeSignedInt(...));
		CommonTypes::putBool($out, $this->usedBlobHashes !== null);
		$blobHashes = $this->usedBlobHashes ?? [];
		VarInt::writeUnsignedInt($out, count($blobHashes));
		foreach($blobHashes as $hash){
				LE::writeUnsignedLong($out, $hash);
			}
		CommonTypes::putString($out, $this->extraPayload);
		return;
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleLevelChunk($this);
	}
}
