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

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\color\Color;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\BitSet;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\DimensionData;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\GatheringJoinInfo;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeGroupEntry;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeItemEntry;
use pocketmine\network\mcpe\protocol\types\inventory\FullContainerName;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\PredictedResult;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeAutoStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingResultsStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\MineBlockStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseSlotInfo;
use pocketmine\network\mcpe\protocol\types\inventory\TriggerType;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientData;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientDataToSkinDataHelper;
use pocketmine\network\mcpe\protocol\types\MapDecoration;
use pocketmine\network\mcpe\protocol\types\MapImage;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeUnlockingRequirement;
use pocketmine\network\mcpe\protocol\types\recipe\ShapedRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapelessRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\StringIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\TagItemDescriptor;
use pocketmine\network\mcpe\protocol\types\ScoreboardIdentityPacketEntry;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\network\mcpe\protocol\types\sound\SoundDataEvent;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryCommon;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithCache;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithCacheList;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithoutCache;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithoutCacheList;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketHeightMapInfo;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPositionOffset;
use pocketmine\network\mcpe\protocol\types\SubChunkRequestResult;
use pocketmine\network\mcpe\protocol\types\SystemCategory;
use pocketmine\network\mcpe\protocol\types\UpdateSubChunkBlocksPacketEntry;
use Ramsey\Uuid\Uuid;
use function array_fill;
use function base64_encode;
use function bin2hex;
use function hex2bin;
use function str_repeat;
use function strlen;
final class Bedrock12652ProtocolTest extends TestCase
{
	public function testSignedBlockHashesRetainTheirIdentityOnTheWire() : void{
		foreach([-2144268767, -1, 0, 2147483647] as $hash){
			$stack = new ItemStack(1, 0, 1, $hash, "");
			$out = new ByteBufferWriter();
			CommonTypes::putItemStackWrapper($out, new ItemStackWrapper(1, $stack));
			$decoded = CommonTypes::getItemStackWrapper(new ByteBufferReader($out->getData()))->getItemStack();
			self::assertSame($hash, $decoded->getBlockRuntimeId());
			if($hash === -2144268767){
				self::assertSame('01000100000102a19cc4810800', bin2hex($out->getData()));
			}
			$packet = UpdateBlockPacket::create(new BlockPosition(0, 64, 0), $hash, 0, 0);
			$out = new ByteBufferWriter();
			$packet->encode($out);
			$decodedPacket = new UpdateBlockPacket();
			$decodedPacket->decode(new ByteBufferReader($out->getData()));
			self::assertSame($hash, $decodedPacket->blockRuntimeId);
			$entry = new UpdateSubChunkBlocksPacketEntry(new BlockPosition(0, 64, 0), $hash, 0, 0, 0);
			$out = new ByteBufferWriter();
			$entry->write($out);
			self::assertSame($hash, UpdateSubChunkBlocksPacketEntry::read(new ByteBufferReader($out->getData()))->getBlockRuntimeId());
			$transaction = UseItemTransactionData::new([], UseItemTransactionData::ACTION_CLICK_BLOCK, TriggerType::PLAYER_INPUT, new BlockPosition(0, 64, 0), 1, 0, new ItemStackWrapper(1, $stack), new Vector3(0, 65, 0), new Vector3(0, 1, 0), $hash, PredictedResult::SUCCESS, 0);
			$out = new ByteBufferWriter();
			$transaction->encodeTransaction($out);
			$decodedTransaction = new UseItemTransactionData();
			$decodedTransaction->decodeTransaction(new ByteBufferReader($out->getData()));
			self::assertSame($hash, $decodedTransaction->getBlockRuntimeId());
		}
	}

	private static function encodePacket(DataPacket $packet) : string
	{
		$out = new ByteBufferWriter();
		$packet->encode($out);
		return $out->getData();
	}
	public function testItemStackNetIdVariantRoundTrip() : void
	{
		$out = new ByteBufferWriter();
		CommonTypes::writeItemStackNetIdVariant($out, -123);
		self::assertSame('85ffffff', bin2hex($out->getData()));
		self::assertSame(-123, CommonTypes::readItemStackNetIdVariant(new ByteBufferReader($out->getData())));
	}
	public function testItemStackWrapperExactWireFormat() : void
	{
		$item = new ItemStackWrapper(5, new ItemStack(7, 1, 3, 2, 'data'));
		$out = new ByteBufferWriter();
		CommonTypes::putItemStackWrapper($out, $item);
		self::assertSame('0700030001010a020464617461', bin2hex($out->getData()));
		$decoded = CommonTypes::getItemStackWrapper(new ByteBufferReader($out->getData()));
		self::assertSame(7, $decoded->getItemStack()->getId());
		self::assertSame(5, $decoded->getStackId());
	}
	public function testEmptyItemStackExactWireFormat() : void
	{
		$item = new ItemStackWrapper(0, ItemStack::null());
		$out = new ByteBufferWriter();
		CommonTypes::putItemStackWrapper($out, $item);
		self::assertSame('0000000000000000', bin2hex($out->getData()));
		self::assertSame(0, CommonTypes::getItemStackWrapper(new ByteBufferReader($out->getData()))->getItemStack()->getId());
		$out = new ByteBufferWriter();
		CommonTypes::putItemStackWithoutStackId($out, ItemStack::null());
		self::assertSame('000000000000', bin2hex($out->getData()));
		self::assertSame(0, CommonTypes::getItemStackWithoutStackId(new ByteBufferReader($out->getData()))->getId());
	}
	public function testEntityMetadataExactWireFormat() : void
	{
		$out = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($out, [5 => new IntMetadataProperty(12)]);
		self::assertSame('0105020218', bin2hex($out->getData()));
		$decoded = CommonTypes::getEntityMetadata(new ByteBufferReader($out->getData()));
		self::assertInstanceOf(IntMetadataProperty::class, $decoded[5]);
		self::assertSame(12, $decoded[5]->getValue());
	}
	public function testNewEntityMetadataRejectsMismatchedTypes() : void
	{
		$this->expectException(PacketDecodeException::class);
		CommonTypes::getEntityMetadata(new ByteBufferReader("\x01\x05\x02\x03\x18"));
	}
	public function testNewEntityMetadataRejectsDuplicateKeys() : void
	{
		$this->expectException(PacketDecodeException::class);
		CommonTypes::getEntityMetadata(new ByteBufferReader("\x02\x05\x02\x02\x18\x05"));
	}
	private static function createSkin() : SkinData
	{
		$image = new SkinImage(1, 1, "\x01\x02\x03\x04");
		return new SkinData('skin', 'play', '{}', $image, [new SkinAnimation($image, 2, 1.5, 3)], new SkinImage(0, 0, ''), '{}', '1', '', '', 'full', SkinData::ARM_SIZE_SLIM, '#01020304', [new PersonaSkinPiece('piece', PersonaSkinPiece::PIECE_TYPE_PERSONA_HAIR, '00000000-0000-0000-0000-000000000001', true, 'product')], [new PersonaPieceTintColor(PersonaPieceTintColor::PIECE_TYPE_PERSONA_HAIR, ['#00000001', '#00000002', '#00000003', '#00000004'])], true, false, true, false, true, true, 'true', 'hash');
	}
	public function testDefaultSkinGeometryIsValidJsonForModernClients() : void
	{
		$skin = new SkinData('skin', '', '{}', new SkinImage(1, 1, "\x01\x02\x03\x04"));
		self::assertSame('{}', $skin->getGeometryData());
	}
	public function testClientProfileHashAndTrustArePreservedDuringSkinConversion() : void
	{
		$client = new ClientData();
		$client->AnimatedImageData = [];
		$client->SkinId = 'windows-skin';
		$client->SkinResourcePatch = base64_encode('{"geometry":{"default":"geometry.humanoid.custom"}}');
		$client->SkinImageHeight = 1;
		$client->SkinImageWidth = 1;
		$client->SkinData = base64_encode("\x01\x02\x03\x04");
		$client->CapeImageHeight = 0;
		$client->CapeImageWidth = 0;
		$client->CapeData = '';
		$client->SkinGeometryData = base64_encode('{}');
		$client->SkinGeometryDataEngineVersion = base64_encode('1.26.44');
		$client->SkinAnimationData = '';
		$client->CapeId = '';
		$client->ArmSize = SkinData::ARM_SIZE_WIDE;
		$client->SkinColor = '#00000000';
		$client->PersonaPieces = [];
		$client->PieceTintColors = [];
		$client->TrustedSkin = false;
		$client->PremiumSkin = false;
		$client->PersonaSkin = false;
		$client->CapeOnClassicSkin = false;
		$client->OverrideSkin = true;
		$client->ProfileHash = 'windows-profile-hash';
		$skin = ClientDataToSkinDataHelper::fromClientData($client);
		self::assertSame('windows-profile-hash', $skin->getProfileHash());
		self::assertSame('false', $skin->getTrustedSkinFlag());
		self::assertFalse($skin->isVerified());
	}
	public function testSkinRoundTrip() : void
	{
		$out = new ByteBufferWriter();
		CommonTypes::putSkin($out, self::createSkin());
		$decoded = CommonTypes::getSkin(new ByteBufferReader($out->getData()));
		self::assertSame('skin', $decoded->getSkinId());
		self::assertSame(SkinData::ARM_SIZE_SLIM, $decoded->getArmSize());
		self::assertSame('#01020304', $decoded->getSkinColor());
		self::assertSame(PersonaSkinPiece::PIECE_TYPE_PERSONA_HAIR, $decoded->getPersonaPieces()[0]->getPieceType());
		self::assertSame('00000000-0000-0000-0000-000000000001', $decoded->getPersonaPieces()[0]->getPackId());
		self::assertSame(['#00000001', '#00000002', '#00000003', '#00000004'], $decoded->getPieceTintColors()[0]->getColors());
		self::assertSame('hash', $decoded->getProfileHash());
	}
	public function testPlayerListRoundTrip() : void
	{
		$uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
		$addition = PlayerListPacket::add([PlayerListEntry::createAdditionEntry($uuid, 7, 'player', self::createSkin())]);
		$decodedAddition = new PlayerListPacket();
		$decodedAddition->decode(new ByteBufferReader(self::encodePacket($addition)));
		self::assertSame(PlayerListPacket::TYPE_ADD, $decodedAddition->type);
		self::assertSame('player', $decodedAddition->entries[0]->username);
		$removal = PlayerListPacket::remove([PlayerListEntry::createRemovalEntry($uuid)]);
		$encodedRemoval = self::encodePacket($removal);
		$decodedRemoval = new PlayerListPacket();
		$decodedRemoval->decode(new ByteBufferReader($encodedRemoval));
		self::assertSame(PlayerListPacket::TYPE_REMOVE, $decodedRemoval->type);
		self::assertSame($uuid->toString(), $decodedRemoval->entries[0]->uuid->toString());
	}
	public function testPlayerSkinRoundTrip() : void
	{
		$packet = PlayerSkinPacket::create(Uuid::fromString('00000000-0000-0000-0000-000000000001'), 'old', 'new', self::createSkin());
		$decoded = new PlayerSkinPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame('new', $decoded->newSkinName);
		self::assertSame('old', $decoded->oldSkinName);
		self::assertTrue($decoded->skin->isVerified());
	}
	public function testCreativeContentWithEmptyIconsRoundTrip() : void
	{
		$packet = CreativeContentPacket::create([new CreativeGroupEntry(1, 'group', ItemStack::null())], [new CreativeItemEntry(2, ItemStack::null(), 0)]);
		$decoded = new CreativeContentPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame(0, $decoded->getGroups()[0]->getIcon()->getId());
		self::assertSame(0, $decoded->getItems()[0]->getItem()->getId());
	}
	public function testCreativeGroupCategoryUsesVersionSpecificWireWidth() : void
	{
		$out = new ByteBufferWriter();
		(new CreativeGroupEntry(1, 'group', ItemStack::null()))->write($out);
		$expected = '010567726f7570000000000000';
		self::assertSame($expected, bin2hex($out->getData()), "Unexpected creative group wire format for {'1.26.52'}");
	}
	public function testCreativeContentCanBeReadByTheClientWireFormat() : void
	{
		$packet = CreativeContentPacket::create([new CreativeGroupEntry(1, 'group', ItemStack::null())], [new CreativeItemEntry(2, ItemStack::null(), 0)]);
		$reader = new ByteBufferReader(self::encodePacket($packet));
		self::assertSame(CreativeContentPacket::NETWORK_ID, \pmmp\encoding\VarInt::readUnsignedInt($reader));
		self::assertSame(1, \pmmp\encoding\VarInt::readUnsignedInt($reader));
		$category = \pmmp\encoding\Byte::readUnsigned($reader);
		self::assertSame(1, $category);
		self::assertSame('group', CommonTypes::getString($reader), "Creative group fields became misaligned for {'1.26.52'}");
		self::assertSame(0, CommonTypes::getItemStackWithoutStackId($reader)->getId());
		self::assertSame(1, \pmmp\encoding\VarInt::readUnsignedInt($reader));
		self::assertSame(2, CommonTypes::readCreativeItemNetId($reader));
		self::assertSame(0, CommonTypes::getItemStackWithoutStackId($reader)->getId());
		self::assertSame(0, \pmmp\encoding\VarInt::readUnsignedInt($reader));
		self::assertSame(0, $reader->getUnreadLength());
	}
	public function testRecipeIngredientRoundTrip() : void
	{
		$descriptors = [null, new TagItemDescriptor('minecraft:logs')];
		$descriptors[] = new StringIdMetaItemDescriptor('minecraft:stick', 2);
		foreach ($descriptors as $descriptor) {
			$out = new ByteBufferWriter();
			CommonTypes::putRecipeIngredient($out, new RecipeIngredient($descriptor, 3));
			$decoded = CommonTypes::getRecipeIngredient(new ByteBufferReader($out->getData()));
			self::assertSame(3, $decoded->getCount());
			if ($descriptor === null) {
				self::assertNull($decoded->getDescriptor());
			} else {
				self::assertInstanceOf($descriptor::class, $decoded->getDescriptor());
			}
		}
	}
	public function testCraftingDataRoundTrip() : void
	{
		$descriptor = new StringIdMetaItemDescriptor('minecraft:stick', 0);
		$ingredient = new RecipeIngredient($descriptor, 1);
		$output = new ItemStack(7, 0, 1, 0, '');
		$uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
		$unlocking = new RecipeUnlockingRequirement(null);
		$shaped = new ShapedRecipe(CraftingDataPacket::ENTRY_SHAPED, 'shaped', [[$ingredient]], [$output], $uuid, 'crafting_table', 1, true, $unlocking, 2);
		$shapeless = new ShapelessRecipe(CraftingDataPacket::ENTRY_SHAPELESS, 'shapeless', [$ingredient], [$output], $uuid, 'crafting_table', 1, $unlocking, 3);
		$packet = CraftingDataPacket::create([$shapeless, $shaped], [], [], [], true);
		$decoded = new CraftingDataPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertCount(2, $decoded->recipesWithTypeIds);
		self::assertTrue($decoded->cleanRecipes);
		self::assertSame(CraftingDataPacket::ENTRY_SHAPED, $decoded->recipesWithTypeIds[0]->getTypeId());
	}
	public function testEmptyCraftingDataExactWireFormat() : void
	{
		$packet = CraftingDataPacket::create([], [], [], [], false);
		$encoded = self::encodePacket($packet);
		self::assertStringEndsWith(str_repeat("\x00", 12), $encoded);
	}
	public function testAutomaticCraftingStackRequestRoundTrip() : void
	{
		$descriptor = new StringIdMetaItemDescriptor('minecraft:stick', 0);
		$action = new CraftRecipeAutoStackRequestAction(4, 2, 2, [new RecipeIngredient($descriptor, 3)]);
		$out = new ByteBufferWriter();
		$action->write($out);
		$decoded = CraftRecipeAutoStackRequestAction::read(new ByteBufferReader($out->getData()));
		self::assertSame(4, $decoded->getRecipeId());
		self::assertSame(2, $decoded->getRepetitions());
		self::assertSame(3, $decoded->getIngredients()[0]->getCount());
	}
	public function testResourcePackResponseRoundTrip() : void
	{
		foreach ([ResourcePackClientResponsePacket::STATUS_REFUSED, ResourcePackClientResponsePacket::STATUS_SEND_PACKS, ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS, ResourcePackClientResponsePacket::STATUS_COMPLETED] as $status) {
			$packIds = $status === ResourcePackClientResponsePacket::STATUS_SEND_PACKS ? ['sample-pack'] : [];
			$packet = ResourcePackClientResponsePacket::create($status, $packIds);
			$decoded = new ResourcePackClientResponsePacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
			self::assertSame($status, $decoded->status);
			self::assertSame($packIds, $decoded->packIds);
		}
	}
	public function testResourcePackInfoRoundTrip() : void
	{
		$packet = ResourcePacksInfoPacket::create([], false, false, false, Uuid::fromString('00000000-0000-0000-0000-000000000000'), '1.0.0', false);
		$decoded = new ResourcePacksInfoPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame([], $decoded->resourcePackEntries);
		self::assertSame('1.0.0', $decoded->getWorldTemplateVersion());
	}
	public function testMovePlayerRoundTrip() : void
	{
		foreach ([MovePlayerPacket::MODE_NORMAL, MovePlayerPacket::MODE_TELEPORT] as $mode) {
			$packet = MovePlayerPacket::create(17, new Vector3(1.5, 64.25, -3.75), 10.0, 20.0, 30.0, $mode, true, 0, 4, 9, 22);
			$decoded = new MovePlayerPacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
			self::assertSame($mode, $decoded->mode);
			self::assertSame(22, $decoded->tick);
			if ($mode === MovePlayerPacket::MODE_TELEPORT) {
				self::assertSame(4, $decoded->teleportCause);
				self::assertSame(9, $decoded->teleportItem);
			}
		}
	}
	public function testLevelChunkRoundTrip() : void
	{
		$packet = LevelChunkPacket::create(new ChunkPosition(3, -2), 0, 8, false, null, 'chunk');
		$decoded = new LevelChunkPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame(3, $decoded->getChunkPosition()->getX());
		self::assertSame(-2, $decoded->getChunkPosition()->getZ());
		self::assertSame(8, $decoded->getSubChunkCount());
		self::assertFalse($decoded->isCacheEnabled());
		self::assertSame('chunk', $decoded->getExtraPayload());
	}
	public function testItemStackRequestRoundTrip() : void
	{
		$request = new ItemStackRequest(-3, [new MineBlockStackRequestAction(2, 13, -123)], ['filter'], 7);
		$out = new ByteBufferWriter();
		$request->write($out);
		$decoded = ItemStackRequest::read(new ByteBufferReader($out->getData()));
		self::assertSame(-3, $decoded->getRequestId());
		self::assertSame(['filter'], $decoded->getFilterStrings());
		self::assertCount(1, $decoded->getActions());
		self::assertInstanceOf(MineBlockStackRequestAction::class, $decoded->getActions()[0]);
		self::assertSame(-123, $decoded->getActions()[0]->getStackId());
	}
	public function testCraftingResults2193DescriptorRoundTripFromClientWire() : void
	{
		$payload = hex2bin("0101011b6d696e6563726166743a707269736d6172696e655f737461697273000100df8fd6ea020a0000000000000000000001");
		self::assertNotFalse($payload);
		$decoded = DeprecatedCraftingResultsStackRequestAction::read(new ByteBufferReader($payload));
		self::assertSame(1, $decoded->getIterations());
	}
	public function testItemStackResponseRoundTrip() : void
	{
		foreach ([ItemStackResponse::RESULT_OK, ItemStackResponse::RESULT_ERROR] as $result) {
			$response = new ItemStackResponse($result, -7, []);
			$out = new ByteBufferWriter();
			$response->write($out);
			$decoded = ItemStackResponse::read(new ByteBufferReader($out->getData()));
			self::assertSame($result, $decoded->getResult());
			self::assertSame(-7, $decoded->getRequestId());
			self::assertSame([], $decoded->getContainerInfos());
		}
	}
	public function testItemStackResponseSlotRoundTrip() : void
	{
		$slot = new ItemStackResponseSlotInfo(2, 3, 4, 17, 'name', 'filtered', 6);
		$out = new ByteBufferWriter();
		$slot->write($out);
		$decoded = ItemStackResponseSlotInfo::read(new ByteBufferReader($out->getData()));
		self::assertSame(17, $decoded->getItemStackId());
		self::assertSame('filtered', $decoded->getFilteredCustomName());
		self::assertSame(6, $decoded->getDurabilityCorrection());
	}
	public function testScoreboardRoundTrip() : void
	{
		$entry = new ScorePacketEntry();
		$entry->scoreboardId = 12;
		$entry->objectiveName = 'objective';
		$entry->score = 7;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
		$entry->customName = 'line';
		foreach ([SetScorePacket::TYPE_CHANGE, SetScorePacket::TYPE_REMOVE] as $type) {
			$packet = SetScorePacket::create($type, [$entry]);
			$decoded = new SetScorePacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
			self::assertSame($type, $decoded->type);
			self::assertSame('objective', $decoded->entries[0]->objectiveName);
			if ($type === SetScorePacket::TYPE_CHANGE) {
				self::assertSame('line', $decoded->entries[0]->customName);
			}
		}
	}
	public function testScoreboardFakePlayerExactWireFormat() : void
	{
		$entry = new ScorePacketEntry();
		$entry->scoreboardId = 12;
		$entry->objectiveName = 'objective';
		$entry->score = 7;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
		$entry->customName = 'line';
		$packet = SetScorePacket::create(SetScorePacket::TYPE_CHANGE, [$entry]);
		$expected = '6c0103106368616e676566616b65706c6179657218096f626a65637469766507000000046c696e65';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new SetScorePacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame('objective', $decoded->entries[0]->objectiveName);
		self::assertSame('line', $decoded->entries[0]->customName);
	}
	public function testScoreboardRemovalExactWireFormat() : void
	{
		$entry = new ScorePacketEntry();
		$entry->scoreboardId = 12;
		$entry->objectiveName = 'objective';
		$entry->score = 7;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
		$packet = SetScorePacket::create(SetScorePacket::TYPE_REMOVE, [$entry]);
		$expected = '6c01000672656d6f76651801096f626a656374697665';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new SetScorePacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame('objective', $decoded->entries[0]->objectiveName);
	}
	public function testLegacyHcfScoreboardConstructionDefaultsToChange() : void
	{
		$entry = new ScorePacketEntry();
		$entry->scoreboardId = 0;
		$entry->objectiveName = 'player';
		$entry->score = 0;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
		$entry->customName = 'line';
		$packet = new SetScorePacket();
		$packet->entries = [$entry];
		$encoded = self::encodePacket($packet);
		$decoded = new SetScorePacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame(SetScorePacket::TYPE_CHANGE, $decoded->type);
		self::assertSame('line', $decoded->entries[0]->customName);
	}
	public function testModernMovementDeltaExactWireFormat() : void
	{
		$packet = new MoveActorDeltaPacket();
		$packet->actorRuntimeId = 7;
		$packet->flags = MoveActorDeltaPacket::FLAG_HAS_X | MoveActorDeltaPacket::FLAG_GROUND;
		$packet->xPos = 1.5;
		$expected = '6f07010000c03f00000000000100000000';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new MoveActorDeltaPacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame($packet->flags, $decoded->flags);
		self::assertSame(1.5, $decoded->xPos);
	}
	public function testDimensionDefinitionPackIdExactWireFormat() : void
	{
		$data = new DimensionData(256, -64, 1, 0);
		$out = new ByteBufferWriter();
		$data->write($out);
		$expected = '7f80050200' . str_repeat('00', 16) . '106d696e6563726166743a706c61696e73';
		self::assertSame($expected, bin2hex($out->getData()));
		$decoded = DimensionData::read(new ByteBufferReader($out->getData()));
		self::assertSame(256, $decoded->getMaxHeight());
		self::assertSame(-64, $decoded->getMinHeight());
		self::assertSame('minecraft:plains', $decoded->getDefaultBiome());
		self::assertSame('00000000-0000-0000-0000-000000000000', $decoded->getPackId()?->toString());
	}
	public function testTransferGatheringsOptionalExactWireFormat() : void
	{
		$packet = TransferPacket::create('host', 19132, true);
		$expected = '5504686f7374bc4a0100';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new TransferPacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame('host', $decoded->address);
		self::assertNull($decoded->gatheringsConfig);
	}
	public function testModernGatheringOptionalFieldsPreserveAbsentValues() : void
	{
		$out = new ByteBufferWriter();
		CommonTypes::putUUID($out, Uuid::fromString(Uuid::NIL));
		CommonTypes::putString($out, 'experience');
		CommonTypes::putBool($out, false);
		CommonTypes::putBool($out, false);
		CommonTypes::putString($out, 'creator');
		CommonTypes::putBool($out, false);
		CommonTypes::putBool($out, false);
		CommonTypes::putBool($out, false);
		$expected = $out->getData();
		$decoded = GatheringJoinInfo::read(new ByteBufferReader($expected));
		self::assertSame(Uuid::NIL, $decoded->getExperienceWorldId()->toString());
		self::assertSame('', $decoded->getScenarioId());
		$encoded = new ByteBufferWriter();
		$decoded->write($encoded);
		self::assertSame(bin2hex($expected), bin2hex($encoded->getData()));
	}
	public function testEmptyMapUpdateExactWireFormat() : void
	{
		$packet = new ClientboundMapItemDataPacket();
		$packet->mapId = 7;
		$packet->origin = new BlockPosition(0, 0, 0);
		$packet->scale = 0;
		$expected = '430e0000000000' . str_repeat('00', 9);
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new ClientboundMapItemDataPacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame(7, $decoded->mapId);
		self::assertSame([], $decoded->decorations);
	}
	public function testMapDecorationsAndPixelsRoundTrip() : void
	{
		$packet = new ClientboundMapItemDataPacket();
		$packet->mapId = 7;
		$packet->origin = new BlockPosition(0, 0, 0);
		$packet->scale = 2;
		$packet->parentMapIds = [3];
		$packet->decorations = [new MapDecoration(1, 2, 3, 4, 'marker', new Color(5, 6, 7, 8))];
		$packet->colors = new MapImage([[new Color(9, 10, 11, 12)]]);
		$packet->xOffset = 1;
		$packet->yOffset = 2;
		$decoded = new ClientboundMapItemDataPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame([3], $decoded->parentMapIds);
		self::assertSame('marker', $decoded->decorations[0]->getLabel());
		self::assertSame($packet->decorations[0]->getColor()->toARGB(), $decoded->decorations[0]->getColor()->toARGB());
		self::assertNotNull($decoded->colors);
		self::assertSame($packet->colors->getPixels()[0][0]->toARGB(), $decoded->colors->getPixels()[0][0]->toARGB());
	}
	public function testPlaySoundLoopCountExactWireFormat() : void
	{
		$packet = PlaySoundPacket::create('test', 0, 0, 0, 1, 1, null, 2);
		$expected = '5604746573740000000000803f0000803f04000000';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new PlaySoundPacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame(2, $decoded->loopCount);
	}
	public function testAnvilDamageModernFieldRemovalExactWireFormat() : void
	{
		$packet = AnvilDamagePacket::create(new BlockPosition(1, 2, 3), 5);
		$expected = '8d01020406';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new AnvilDamagePacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame(0, $decoded->getDamageAmount());
	}
	public function testPlayerLocationVariantExactWireFormat() : void
	{
		$packet = PlayerLocationPacket::createHide(7);
		$expected = 'c6020e0102';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new PlayerLocationPacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame(7, $decoded->getActorUniqueId());
	}
	public function testPlayerEntityOverrideVariantExactWireFormat() : void
	{
		$packet = PlayerUpdateEntityOverridesPacket::createIntOverride(7, 2, 300);
		$expected = 'c5020e02020e736574696e746f766572726964652c010000';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new PlayerUpdateEntityOverridesPacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertSame(7, $decoded->getActorUniqueId());
		self::assertSame(300, $decoded->getIntOverrideValue());
	}
	public function testSoundUpdateEventVariantExactWireFormat() : void
	{
		$packet = ClientboundUpdateSoundDataPacket::create(3, 'legacy', SoundDataEvent::stop());
		$expected = 'dc0203000000000000000100000000000000000000';
		$encoded = self::encodePacket($packet);
		self::assertSame($expected, bin2hex($encoded));
		$decoded = new ClientboundUpdateSoundDataPacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertNotNull($decoded->getStopEvent());
	}
	public function testSystemDiagnosticsCategoriesVersioned() : void
	{
		$packet = ServerboundDiagnosticsPacket::create(1, 2, 3, 4, 5, 6, 7, 8, 9, [], [], [], [], [new SystemCategory('tick', 4)]);
		$encoded = self::encodePacket($packet);
		self::assertStringEndsWith('00000001047469636b040000000000000000', bin2hex($encoded));
		$decoded = new ServerboundDiagnosticsPacket();
		$decoded->decode(new ByteBufferReader($encoded));
		self::assertCount(1, $decoded->getSystemCategories());
	}
	public function testPlayerAuthInputRoundTrip() : void
	{
		$flags = new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS);
		$flags->set(PlayerAuthInputFlags::START_SPRINTING, true);
		$packet = PlayerAuthInputPacket::create(new Vector3(1, 64, 2), 1, 2, 3, 0.25, 0.5, $flags, 1, 0, 1, new Vector2(0, 0), 10, new Vector3(0, 0, 0), null, null, null, null, 0, 0, new Vector3(0, 0, 0), new Vector2(0, 0));
		$decoded = new PlayerAuthInputPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame(10, $decoded->getTick());
		self::assertTrue($decoded->getInputFlags()->get(PlayerAuthInputFlags::START_SPRINTING));
		self::assertSame(PlayerAuthInputFlags::NUMBER_OF_FLAGS, $decoded->getInputFlags()->getLength());
	}
	public function testInventoryContentRoundTrip() : void
	{
		$item = new ItemStackWrapper(5, new ItemStack(7, 1, 3, 2, 'data'));
		$empty = new ItemStackWrapper(0, ItemStack::null());
		$packet = InventoryContentPacket::create(2, [$item], new FullContainerName(12), $empty);
		$decoded = new InventoryContentPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame(7, $decoded->items[0]->getItemStack()->getId());
		self::assertSame(5, $decoded->items[0]->getStackId());
	}
	public function testInventorySlotRoundTrip() : void
	{
		$item = new ItemStackWrapper(5, new ItemStack(7, 1, 3, 2, 'data'));
		$packet = InventorySlotPacket::create(2, 4, new FullContainerName(12), null, $item);
		$decoded = new InventorySlotPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame(7, $decoded->item->getItemStack()->getId());
		self::assertSame(5, $decoded->item->getStackId());
	}
	public function testMobEquipmentRoundTrip() : void
	{
		$item = new ItemStackWrapper(5, new ItemStack(7, 1, 3, 2, 'data'));
		$packet = MobEquipmentPacket::create(4, $item, 3, 2, 1);
		$decoded = new MobEquipmentPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame(7, $decoded->item->getItemStack()->getId());
		self::assertSame(5, $decoded->item->getStackId());
	}
	public function test2193HeightmapRowsAreLengthPrefixed() : void
	{
		$map = new SubChunkPacketHeightMapInfo(array_fill(0, 256, 7));
		$out = new ByteBufferWriter();
		$map->write($out);
		self::assertSame(272, strlen($out->getData()));
		$decoded = SubChunkPacketHeightMapInfo::read(new ByteBufferReader($out->getData()));
		self::assertSame($map->getHeights(), $decoded->getHeights());
	}
	public function test2193DimensionDataUsesMinYAndHeightRange() : void
	{
		$uuid = Uuid::fromString('01020304-0506-0708-090a-0b0c0d0e0f10');
		$data = new DimensionData(320, -64, 1, 1000, $uuid, 'plains');
		$out = new ByteBufferWriter();
		$data->write($out);
		$decoded = DimensionData::read(new ByteBufferReader($out->getData()));
		self::assertSame(320, $decoded->getMaxHeight());
		self::assertSame(-64, $decoded->getMinHeight());
		self::assertSame('plains', $decoded->getDefaultBiome());
	}
	public function test2193ItemStackResponseRemovesDummyOptionalMarker() : void
	{
		$response = new ItemStackResponse(ItemStackResponse::RESULT_ERROR, 5);
		$out = new ByteBufferWriter();
		$response->write($out);
		self::assertSame('010a00', bin2hex($out->getData()));
		$decoded = ItemStackResponse::read(new ByteBufferReader($out->getData()));
		self::assertSame(ItemStackResponse::RESULT_ERROR, $decoded->getResult());
		self::assertSame(5, $decoded->getRequestId());
		self::assertSame([], $decoded->getContainerInfos());
	}
	public function testIntegerGameRuleWireFormat() : void
	{
		$rule = new IntGameRule(300, false);
		$out = new ByteBufferWriter();
		$rule->encode($out, true);
		self::assertSame('2c010000', bin2hex($out->getData()));
		self::assertSame(300, IntGameRule::decode(new ByteBufferReader($out->getData()), false, true)->getValue());
	}
	public function testScoreboardIdentityRoundTrip() : void
	{
		$entry = new ScoreboardIdentityPacketEntry();
		$entry->scoreboardId = 7;
		$entry->actorUniqueId = 23;
		$packet = SetScoreboardIdentityPacket::create(SetScoreboardIdentityPacket::TYPE_REGISTER_IDENTITY, [$entry]);
		$decoded = new SetScoreboardIdentityPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame(23, $decoded->entries[0]->actorUniqueId);
	}
	public function testNewSubChunkRequestRoundTrip() : void
	{
		$packet = SubChunkRequestPacket::create(0, new SubChunkPosition(3, 4, -2), [new SubChunkPositionOffset(1, 0, -1)]);
		$decoded = new SubChunkRequestPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
		self::assertSame(3, $decoded->getBasePosition()->getX());
		self::assertSame(-1, $decoded->getEntries()[0]->getZOffset());
	}
	public function testSubChunkRoundTrip() : void
	{
		foreach ([false, true] as $cacheEnabled) {
			$base = new SubChunkPacketEntryCommon(new SubChunkPositionOffset(1, 0, -1), SubChunkRequestResult::SUCCESS, 'terrain', null, null);
			$entries = $cacheEnabled ? new SubChunkPacketEntryWithCacheList([new SubChunkPacketEntryWithCache($base, 17)]) : new SubChunkPacketEntryWithoutCacheList([new SubChunkPacketEntryWithoutCache($base)]);
			$packet = SubChunkPacket::create(0, new SubChunkPosition(3, 4, -2), $entries);
			$decoded = new SubChunkPacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet)));
			self::assertSame($cacheEnabled, $decoded->isCacheEnabled());
			self::assertSame(3, $decoded->getBaseSubChunkPosition()->getX());
			self::assertSame('terrain', $decoded->getEntries()->getEntries()[0]->getBase()->getTerrainData());
		}
	}
}
