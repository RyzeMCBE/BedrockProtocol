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

declare (strict_types=1);
namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use function array_map;
use function file_get_contents;
use function hex2bin;
use function json_decode;
final class PlayerAuthInput12652FixtureTest extends TestCase
{
	/** @return iterable<string, array{string, string, ?list<int>}> */
	public static function packets() : iterable
	{
		// Independently encoded by Amber-PM/BedrockProtocol at
		// 3fc30afe680768c81cd3b748d1ff595142c7d9bc, using protocol 2193.
		// These are interoperability fixtures, not captured client packets.
		/** @var array<string, array{hex: string, actions: ?list<int>}> $fixtures */
		$fixtures = json_decode((string) file_get_contents(__DIR__ . '/fixtures/player_auth_input_2193_amber.json'), true, flags: JSON_THROW_ON_ERROR);
		foreach ($fixtures as $name => $fixture) {
			yield $name => [$name, $fixture['hex'], $fixture['actions']];
		}
	}
	/** @param list<int>|null $expectedActions */
	#[DataProvider('packets')]
	public function testDecodeIndependentPacket(string $name, string $hex, ?array $expectedActions) : void
	{
		$bytes = hex2bin($hex);
		self::assertNotFalse($bytes);
		$reader = new ByteBufferReader($bytes);
		$packet = new PlayerAuthInputPacket();
		$packet->decode($reader);
		self::assertSame(0, $reader->getUnreadLength());
		self::assertEquals(new Vector3(-123, 68, 538), $packet->getPosition());
		self::assertSame(0.25, $packet->getAnalogMoveVecX());
		self::assertSame(0.5, $packet->getAnalogMoveVecZ());
		self::assertEquals(new Vector3(1, 2, 3), $packet->getCameraOrientation());
		self::assertEquals(new Vector2(4, 5), $packet->getRawMove());
		if ($expectedActions === null) {
			self::assertNull($packet->getBlockActions());
		} else {
			$actions = $packet->getBlockActions();
			self::assertNotNull($actions);
			self::assertSame($expectedActions, array_map(static fn($action) => $action->getActionType(), $actions));
			foreach ($actions as $action) {
				self::assertInstanceOf(PlayerBlockActionWithBlockInfo::class, $action);
				self::assertEquals(new BlockPosition(-123, 68, 538), $action->getBlockPosition());
				self::assertSame(1, $action->getFace());
			}
		}
		self::assertSame($name === 'vehicle' ? 56 : null, $packet->getVehicleInfo()?->getPredictedVehicleActorUniqueId());
		self::assertSame($name === 'request' ? 17 : null, $packet->getItemStackRequest()?->getRequestId());
		$writer = new ByteBufferWriter();
		$packet->encode($writer);
		self::assertSame($bytes, $writer->getData());
	}
}
