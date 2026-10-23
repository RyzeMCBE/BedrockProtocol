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
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class GatheringJoinInfo{
	private bool $hasExperienceWorldId = true;
	private bool $hasExperienceWorldName = true;
	private bool $hasTargetId = true;
	private bool $hasScenarioId = true;
	private bool $hasServerId = true;

	public function __construct(
		private UuidInterface $experienceId,
		private string $experienceName,
		private UuidInterface $experienceWorldId,
		private string $experienceWorldName,
		private string $creatorId,
		private UuidInterface $targetId,
		private string $scenarioId,
		private string $serverId,
	){}

	public function getExperienceId() : UuidInterface{ return $this->experienceId; }

	public function getExperienceName() : string{ return $this->experienceName; }

	public function getExperienceWorldId() : UuidInterface{ return $this->experienceWorldId; }

	public function getExperienceWorldName() : string{ return $this->experienceWorldName; }

	public function getCreatorId() : string{ return $this->creatorId; }

	public function getTargetId() : UuidInterface{ return $this->targetId; }

	public function getScenarioId() : string{ return $this->scenarioId; }

	public function getServerId() : string{ return $this->serverId; }

	public static function read(ByteBufferReader $in) : self{
		$experienceId = CommonTypes::getUUID($in);
		$experienceName = CommonTypes::getString($in);
		$experienceWorldId = CommonTypes::readOptional($in, CommonTypes::getUUID(...));
		$experienceWorldName = CommonTypes::readOptional($in, CommonTypes::getString(...));
		$creatorId = CommonTypes::getString($in);
		$targetId = CommonTypes::readOptional($in, CommonTypes::getUUID(...));
		$scenarioId = CommonTypes::readOptional($in, CommonTypes::getString(...));
		$serverId = CommonTypes::readOptional($in, CommonTypes::getString(...));
		$result = new self(
				$experienceId,
				$experienceName,
				$experienceWorldId ?? Uuid::fromString(Uuid::NIL),
				$experienceWorldName ?? "",
				$creatorId,
				$targetId ?? Uuid::fromString(Uuid::NIL),
				$scenarioId ?? "",
				$serverId ?? ""
			);
		$result->hasExperienceWorldId = $experienceWorldId !== null;
		$result->hasExperienceWorldName = $experienceWorldName !== null;
		$result->hasTargetId = $targetId !== null;
		$result->hasScenarioId = $scenarioId !== null;
		$result->hasServerId = $serverId !== null;
		return $result;
	}

	public function write(ByteBufferWriter $out) : void{
		CommonTypes::putUUID($out, $this->experienceId);
		CommonTypes::putString($out, $this->experienceName);
		CommonTypes::writeOptional($out, $this->hasExperienceWorldId ? $this->experienceWorldId : null, CommonTypes::putUUID(...));
		CommonTypes::writeOptional($out, $this->hasExperienceWorldName ? $this->experienceWorldName : null, CommonTypes::putString(...));
		CommonTypes::putString($out, $this->creatorId);
		CommonTypes::writeOptional($out, $this->hasTargetId ? $this->targetId : null, CommonTypes::putUUID(...));
		CommonTypes::writeOptional($out, $this->hasScenarioId ? $this->scenarioId : null, CommonTypes::putString(...));
		CommonTypes::writeOptional($out, $this->hasServerId ? $this->serverId : null, CommonTypes::putString(...));
		return;
	}
}
