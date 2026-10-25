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

namespace pocketmine\network\mcpe\protocol\serializer;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\color\Color;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\command\CommandOriginData;
use pocketmine\network\mcpe\protocol\types\entity\BlockPosMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\CompoundTagMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\FloatMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\ShortMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\Vec3MetadataProperty;
use pocketmine\network\mcpe\protocol\types\FloatGameRule;
use pocketmine\network\mcpe\protocol\types\GameRule;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\recipe\MolangItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use pocketmine\network\mcpe\protocol\types\recipe\StringIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\TagItemDescriptor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPieceType;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinArmSizeType;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\network\mcpe\protocol\types\StructureSettings;
use pocketmine\utils\Binary;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function count;
use function hexdec;
use function is_int;
use function preg_match;
use function sprintf;
use function strlen;
use function strrev;
use function substr;

final class CommonTypes{

	private function __construct(){
		//NOOP
	}

	/** @throws DataDecodeException */
	public static function getString(ByteBufferReader $in) : string{
		return $in->readByteArray(VarInt::readUnsignedInt($in));
	}

	public static function putString(ByteBufferWriter $out, string $v) : void{
		VarInt::writeUnsignedInt($out, strlen($v));
		$out->writeByteArray($v);
	}

	/** @throws DataDecodeException */
	public static function getBool(ByteBufferReader $in) : bool{
		return Byte::readUnsigned($in) !== 0;
	}

	public static function putBool(ByteBufferWriter $out, bool $v) : void{
		Byte::writeUnsigned($out, $v ? 1 : 0);
	}

	/** @throws DataDecodeException */
	public static function getUUID(ByteBufferReader $in) : UuidInterface{
		//This is two little-endian longs: bytes 7-0 followed by bytes 15-8
		$p1 = strrev($in->readByteArray(8));
		$p2 = strrev($in->readByteArray(8));
		return Uuid::fromBytes($p1 . $p2);
	}

	public static function putUUID(ByteBufferWriter $out, UuidInterface $uuid) : void{
		$bytes = $uuid->getBytes();
		$out->writeByteArray(strrev(substr($bytes, 0, 8)));
		$out->writeByteArray(strrev(substr($bytes, 8, 8)));
	}

	/** @throws DataDecodeException */
	public static function readColor(ByteBufferReader $in) : Color{
		return Color::fromARGB(LE::readUnsignedInt($in));
	}

	public static function writeColor(ByteBufferWriter $out, Color $color) : void{
		LE::writeUnsignedInt($out, $color->toARGB());
	}

	/** @throws DataDecodeException */
	public static function getSkin(ByteBufferReader $in) : SkinData{
		$skinId = self::getString($in);
		$skinPlayFabId = self::getString($in);
		$skinResourcePatch = self::getString($in);
		$skinData = self::getSkinImage($in);
		$animationCount = VarInt::readUnsignedInt($in);
		$animations = [];
		for($i = 0; $i < $animationCount; ++$i){
			$skinImage = self::getSkinImage($in);
			$animationType = VarInt::readUnsignedInt($in);
			$animationFrames = LE::readFloat($in);
			$expressionType = VarInt::readUnsignedInt($in);
			$animations[] = new SkinAnimation($skinImage, $animationType, $animationFrames, $expressionType);
		}
		$capeData = self::getSkinImage($in);
		$geometryData = self::getString($in);
		$geometryDataVersion = self::getString($in);
		$animationData = self::getString($in);
		$capeId = self::getString($in);
		$fullSkinId = self::getString($in);
		$armSize = SkinArmSizeType::fromOrdinal(Byte::readUnsigned($in))->value;
		$skinColor = sprintf('#%08X', LE::readUnsignedInt($in));
		$personaPieceCount = VarInt::readUnsignedInt($in);
		$personaPieces = [];
		for($i = 0; $i < $personaPieceCount; ++$i){
			$pieceId = self::getString($in);
			$pieceType = PersonaSkinPieceType::fromOrdinal(LE::readUnsignedInt($in))->toJsonString();
			$packId = self::getUUID($in)->toString();
			$isDefaultPiece = self::getBool($in);
			$productId = self::getString($in);
			$personaPieces[] = new PersonaSkinPiece($pieceId, $pieceType, $packId, $isDefaultPiece, $productId);
		}
		$pieceTintColorCount = VarInt::readUnsignedInt($in);
		$pieceTintColors = [];
		for($i = 0; $i < $pieceTintColorCount; ++$i){
			$pieceType = self::getString($in);
			$pieceType = PersonaSkinPieceType::fromPacket($pieceType)->toJsonString();
			$colorCount = 4;
			$colors = [];
			for($j = 0; $j < $colorCount; ++$j){
				$colors[] = sprintf('#%08X', LE::readUnsignedInt($in));
			}
			$pieceTintColors[] = new PersonaPieceTintColor(
				$pieceType,
				$colors
			);
		}

		$premium = self::getBool($in);
		$persona = self::getBool($in);
		$capeOnClassic = self::getBool($in);
		$isPrimaryUser = self::getBool($in);
		$override = self::getBool($in);
		$trustedSkinFlag = self::getString($in);
		$profileHash = self::getString($in);

		return new SkinData(
			$skinId,
			$skinPlayFabId,
			$skinResourcePatch,
			$skinData,
			$animations,
			$capeData,
			$geometryData,
			$geometryDataVersion,
			$animationData,
			$capeId,
			$fullSkinId,
			$armSize,
			$skinColor,
			$personaPieces,
			$pieceTintColors,
			$trustedSkinFlag !== "false",
			$premium,
			$persona,
			$capeOnClassic,
			$isPrimaryUser,
			$override,
			$trustedSkinFlag,
			$profileHash,
		);
	}

	public static function putSkin(ByteBufferWriter $out, SkinData $skin) : void{
		self::putString($out, $skin->getSkinId());
		self::putString($out, $skin->getPlayFabId());
		self::putString($out, $skin->getResourcePatch());
		self::putSkinImage($out, $skin->getSkinImage());
		VarInt::writeUnsignedInt($out, count($skin->getAnimations()));
		foreach($skin->getAnimations() as $animation){
			self::putSkinImage($out, $animation->getImage());
			VarInt::writeUnsignedInt($out, $animation->getType());
			LE::writeFloat($out, $animation->getFrames());
			VarInt::writeUnsignedInt($out, $animation->getExpressionType());
		}
		self::putSkinImage($out, $skin->getCapeImage());
		self::putString($out, $skin->getGeometryData());
		self::putString($out, $skin->getGeometryDataEngineVersion());
		self::putString($out, $skin->getAnimationData());
		self::putString($out, $skin->getCapeId());
		self::putString($out, $skin->getFullSkinId());
		Byte::writeUnsigned($out, SkinArmSizeType::fromPacket($skin->getArmSize())->toOrdinal());
		self::putSkinColor($out, $skin->getSkinColor());
		VarInt::writeUnsignedInt($out, count($skin->getPersonaPieces()));
		foreach($skin->getPersonaPieces() as $piece){
			self::putString($out, $piece->getPieceId());
			LE::writeUnsignedInt($out, PersonaSkinPieceType::fromJsonString($piece->getPieceType())->toOrdinal());
			self::putUUID($out, Uuid::fromString($piece->getPackId()));
			self::putBool($out, $piece->isDefaultPiece());
			self::putString($out, $piece->getProductId());
		}
		VarInt::writeUnsignedInt($out, count($skin->getPieceTintColors()));
		foreach($skin->getPieceTintColors() as $tint){
			self::putString($out, PersonaSkinPieceType::fromJsonString($tint->getPieceType())->value);
			if(count($tint->getColors()) !== 4){
					throw new \InvalidArgumentException("Persona skin piece tint must contain exactly 4 colors");
				}
			foreach($tint->getColors() as $color){
				self::putSkinColor($out, $color);
			}
		}
		self::putBool($out, $skin->isPremium());
		self::putBool($out, $skin->isPersona());
		self::putBool($out, $skin->isPersonaCapeOnClassic());
		self::putBool($out, $skin->isPrimaryUser());
		self::putBool($out, $skin->isOverride());
		self::putString($out, $skin->getTrustedSkinFlag());
		self::putString($out, $skin->getProfileHash());
	}

	private static function putSkinColor(ByteBufferWriter $out, string $color) : void{
		if($color === ""){
			LE::writeUnsignedInt($out, 0);
			return;
		}
		if(preg_match('/^#([a-fA-F0-9]{1,8})$/', $color, $matches) !== 1){
			throw new \InvalidArgumentException("Invalid hex color string: '$color'");
		}
		$argb = hexdec($matches[1]);
		if(!is_int($argb)){
			throw new \InvalidArgumentException("Invalid hex color string: '$color'");
		}
		LE::writeUnsignedInt($out, $argb);
	}

	/** @throws DataDecodeException */
	private static function getSkinImage(ByteBufferReader $in) : SkinImage{
		$width = LE::readUnsignedInt($in);
		$height = LE::readUnsignedInt($in);
		$data = self::getString($in);
		try{
			return new SkinImage($height, $width, $data);
		}catch(\InvalidArgumentException $e){
			throw new PacketDecodeException($e->getMessage(), 0, $e);
		}
	}

	private static function putSkinImage(ByteBufferWriter $out, SkinImage $image) : void{
		LE::writeUnsignedInt($out, $image->getWidth());
		LE::writeUnsignedInt($out, $image->getHeight());
		self::putString($out, $image->getData());
	}

	/** @throws DataDecodeException */
	private static function getItemStackFooter(ByteBufferReader $in, int $id, int $meta, int $count) : ItemStack{
		$blockRuntimeId = VarInt::readSignedInt($in);
		$rawExtraData = self::getString($in);

		return new ItemStack($id, $meta, $count, $blockRuntimeId, $rawExtraData);
	}

	private static function putItemStackFooter(ByteBufferWriter $out, ItemStack $itemStack) : void{
		VarInt::writeSignedInt($out, $itemStack->getBlockRuntimeId());
		self::putString($out, $itemStack->getRawExtraData());
	}

	/**
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	public static function getItemStackWithoutStackId(ByteBufferReader $in) : ItemStack
	{
		$id = VarInt::readSignedInt($in);
		$count = LE::readUnsignedShort($in);
		$meta = VarInt::readUnsignedInt($in);
		return self::getItemStackFooter($in, $id, $meta, $count);
	}

	public static function putItemStackWithoutStackId(ByteBufferWriter $out, ItemStack $itemStack) : void
	{
		VarInt::writeSignedInt($out, $itemStack->getId());
		LE::writeUnsignedShort($out, $itemStack->getCount());
		VarInt::writeUnsignedInt($out, $itemStack->getMeta());
		self::putItemStackFooter($out, $itemStack);
		return;
	}

	/** @throws DataDecodeException */
	public static function getItemStackWrapper(ByteBufferReader $in) : ItemStackWrapper
	{
		$id = LE::readSignedShort($in);
		$count = LE::readUnsignedShort($in);
		$meta = VarInt::readUnsignedInt($in);
		$hasNetId = self::getBool($in);
		$stackId = $hasNetId ? VarInt::readSignedInt($in) : 0;
		$blockRuntimeId = Binary::signInt(VarInt::readUnsignedInt($in));
		$rawExtraData = self::getString($in);
		return new ItemStackWrapper($stackId, new ItemStack($id, $meta, $count, $blockRuntimeId, $rawExtraData));
	}

	public static function putItemStackWrapper(ByteBufferWriter $out, ItemStackWrapper $itemStackWrapper) : void{
		$itemStack = $itemStackWrapper->getItemStack();
		LE::writeSignedShort($out, $itemStack->getId());
		LE::writeUnsignedShort($out, $itemStack->getCount());
		VarInt::writeUnsignedInt($out, $itemStack->getMeta());
		self::putBool($out, $hasNetId = $itemStackWrapper->getStackId() !== 0);
		if($hasNetId){
				VarInt::writeSignedInt($out, $itemStackWrapper->getStackId());
			}
		VarInt::writeUnsignedInt($out, $itemStack->getBlockRuntimeId());
		self::putString($out, $itemStack->getRawExtraData());
		return;
	}

	public static function getNetworkItemStackDescriptor(ByteBufferReader $in) : ItemStackWrapper{
		$id = LE::readSignedShort($in);
		$count = LE::readUnsignedShort($in);
		$meta = VarInt::readUnsignedInt($in);

		$hasNetId = self::getBool($in);
		if ($hasNetId) {
			$variant = VarInt::readUnsignedInt($in);
			$stackId = VarInt::readSignedInt($in);
		} else {
			$variant = 0;
			$stackId = 0;
		}

		$blockRuntimeId = Binary::signInt(VarInt::readUnsignedInt($in));
		$rawExtraData = self::getString($in);

		return new ItemStackWrapper($stackId, new ItemStack($id, $meta, $count, $blockRuntimeId, $rawExtraData), $variant);
	}

	public static function putNetworkItemStackDescriptor(ByteBufferWriter $out, ItemStackWrapper $itemStackWrapper) : void{
		LE::writeSignedShort($out, $itemStackWrapper->getItemStack()->getId());
		LE::writeUnsignedShort($out, $itemStackWrapper->getItemStack()->getCount());
		VarInt::writeUnsignedInt($out, $itemStackWrapper->getItemStack()->getMeta());

		self::putBool($out, $hasNetId = $itemStackWrapper->getStackId() !== 0);
		if($hasNetId){
			VarInt::writeUnsignedInt($out, $itemStackWrapper->getStackIdVariant());
			VarInt::writeSignedInt($out, $itemStackWrapper->getStackId());
		}

		VarInt::writeUnsignedInt($out, $itemStackWrapper->getItemStack()->getBlockRuntimeId());
		self::putString($out, $itemStackWrapper->getItemStack()->getRawExtraData());
	}

	/** @throws DataDecodeException */
	public static function getRecipeIngredient(ByteBufferReader $in) : RecipeIngredient
	{
		$variant = Byte::readUnsigned($in);
		if($variant === 0){
				$meta = VarInt::readSignedInt($in);
				if($meta !== 32767){
					throw new PacketDecodeException("Expected meta 32767 for empty item descriptor, got $meta");
				}
				$descriptor = null;
			}elseif($variant === 1){
				$type = self::getString($in);
				$descriptor = match($type){
					'name' => new StringIdMetaItemDescriptor(self::getString($in), VarInt::readSignedInt($in)),
					'item_tag' => new TagItemDescriptor(self::getString($in), VarInt::readSignedInt($in)),
					'molang' => new MolangItemDescriptor(self::getString($in), LE::readUnsignedShort($in)),
					default => throw new PacketDecodeException("Unknown item descriptor type $type"),
				};
			}else{
				throw new PacketDecodeException("Expected 0 or 1 for item descriptor variant, got $variant");
			}
		return new RecipeIngredient($descriptor, VarInt::readSignedInt($in));
	}

	public static function putRecipeIngredient(ByteBufferWriter $out, RecipeIngredient $ingredient) : void
	{
		$descriptor = $ingredient->getDescriptor();
		if($descriptor === null){
				Byte::writeUnsigned($out, 0);
				VarInt::writeSignedInt($out, 32767);
			}else{
				Byte::writeUnsigned($out, 1);
				if($descriptor instanceof StringIdMetaItemDescriptor){
					self::putString($out, 'name');
					self::putString($out, $descriptor->getId());
					VarInt::writeSignedInt($out, $descriptor->getMeta());
				}elseif($descriptor instanceof TagItemDescriptor){
					self::putString($out, 'item_tag');
					self::putString($out, $descriptor->getTag());
					VarInt::writeSignedInt($out, $descriptor->getMeta());
				}elseif($descriptor instanceof MolangItemDescriptor){
					self::putString($out, 'molang');
					self::putString($out, $descriptor->getMolangExpression());
					LE::writeUnsignedShort($out, $descriptor->getMolangVersion());
				}else{
					throw new \InvalidArgumentException("Item descriptor " . $descriptor::class . " is unsupported by protocol {2193}");
				}
			}
		VarInt::writeSignedInt($out, $ingredient->getCount());
		return;
	}

	public static function readStackRequestIngredient(ByteBufferReader $in) : RecipeIngredient{
		$type = VarInt::readUnsignedInt($in);
		$innerType = Byte::readUnsigned($in);
		if($type !== $innerType){
			throw new PacketDecodeException("Item descriptor type mismatch: outer type $type, inner type $innerType");
		}
		$descriptor = match($type){
			0 => null,
			1 => new StringIdMetaItemDescriptor(self::getString($in), VarInt::readSignedInt($in)),
			2 => new MolangItemDescriptor(self::getString($in), LE::readUnsignedShort($in)),
			3 => new TagItemDescriptor(self::getString($in)),
			default => throw new PacketDecodeException("Unknown stack request item descriptor type $type")
		};
		return new RecipeIngredient($descriptor, LE::readUnsignedShort($in));
	}

	public static function writeStackRequestIngredient(ByteBufferWriter $out, RecipeIngredient $ingredient) : void{
		$descriptor = $ingredient->getDescriptor();
		$type = match(true){
			$descriptor === null => 0,
			$descriptor instanceof StringIdMetaItemDescriptor => 1,
			$descriptor instanceof MolangItemDescriptor => 2,
			$descriptor instanceof TagItemDescriptor => 3,
			default => throw new \InvalidArgumentException("Unsupported stack request item descriptor " . $descriptor::class)
		};
		VarInt::writeUnsignedInt($out, $type);
		Byte::writeUnsigned($out, $type);
		if($descriptor instanceof StringIdMetaItemDescriptor){
			self::putString($out, $descriptor->getId());
			VarInt::writeSignedInt($out, $descriptor->getMeta());
		}elseif($descriptor instanceof MolangItemDescriptor){
			self::putString($out, $descriptor->getMolangExpression());
			LE::writeUnsignedShort($out, $descriptor->getMolangVersion());
		}elseif($descriptor instanceof TagItemDescriptor){
			self::putString($out, $descriptor->getTag());
		}
		LE::writeUnsignedShort($out, $ingredient->getCount());
	}

	/**
	 * Decodes entity metadata from the stream.
	 *
	 * @return MetadataProperty[]
	 * @phpstan-return array<int, MetadataProperty>
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	public static function getEntityMetadata(ByteBufferReader $in) : array{
		$count = VarInt::readUnsignedInt($in);
		$data = [];
		for($i = 0; $i < $count; ++$i){
			$key = VarInt::readUnsignedInt($in);
			if(isset($data[$key])){
				throw new PacketDecodeException("Duplicate entity metadata key $key");
			}
			$type = VarInt::readUnsignedInt($in);
			$innerType = Byte::readUnsigned($in);
			if($type !== $innerType){
					throw new PacketDecodeException("Entity metadata type mismatch: expected $type, got $innerType");
				}

			$data[$key] = self::readMetadataProperty($in, $type);
		}

		return $data;
	}

	/** @throws DataDecodeException */
	private static function readMetadataProperty(ByteBufferReader $in, int $type) : MetadataProperty{
		return match($type){
			ByteMetadataProperty::ID => ByteMetadataProperty::read($in),
			ShortMetadataProperty::ID => ShortMetadataProperty::read($in),
			IntMetadataProperty::ID => IntMetadataProperty::read($in),
			FloatMetadataProperty::ID => FloatMetadataProperty::read($in),
			StringMetadataProperty::ID => StringMetadataProperty::read($in),
			CompoundTagMetadataProperty::ID => CompoundTagMetadataProperty::read($in),
			BlockPosMetadataProperty::ID => BlockPosMetadataProperty::read($in),
			LongMetadataProperty::ID => LongMetadataProperty::read($in),
			Vec3MetadataProperty::ID => Vec3MetadataProperty::read($in),
			default => throw new PacketDecodeException("Unknown entity metadata type " . $type),
		};
	}

	/**
	 * Writes entity metadata to the packet buffer.
	 *
	 * @param MetadataProperty[] $metadata
	 *
	 * @phpstan-param array<int, MetadataProperty> $metadata
	 */
	public static function putEntityMetadata(ByteBufferWriter $out, array $metadata) : void{
		VarInt::writeUnsignedInt($out, count($metadata));
		foreach($metadata as $key => $d){
			VarInt::writeUnsignedInt($out, $key);
			VarInt::writeUnsignedInt($out, $d->getTypeId());
			Byte::writeUnsigned($out, $d->getTypeId());
			$d->write($out);
		}
	}

	/** @throws DataDecodeException */
	public static function getActorUniqueId(ByteBufferReader $in) : int{
		return VarInt::readSignedLong($in);
	}

	public static function putActorUniqueId(ByteBufferWriter $out, int $eid) : void{
		VarInt::writeSignedLong($out, $eid);
	}

	/** @throws DataDecodeException */
	public static function getActorRuntimeId(ByteBufferReader $in) : int{
		return VarInt::readUnsignedLong($in);
	}

	public static function putActorRuntimeId(ByteBufferWriter $out, int $eid) : void{
		VarInt::writeUnsignedLong($out, $eid);
	}

	/**
	 * Reads a block position
	 *
	 * @throws DataDecodeException
	 */
	public static function getBlockPosition(ByteBufferReader $in, bool $signedY = true) : BlockPosition{
		$x = VarInt::readSignedInt($in);
		$y = $signedY ? VarInt::readSignedInt($in) : Binary::signInt(VarInt::readUnsignedInt($in));
		$z = VarInt::readSignedInt($in);
		return new BlockPosition($x, $y, $z);
	}

	/**
	 * Writes a block position
	 */
	public static function putBlockPosition(ByteBufferWriter $out, BlockPosition $blockPosition, bool $signedY = true) : void{
		VarInt::writeSignedInt($out, $blockPosition->getX());
		if($signedY){
			VarInt::writeSignedInt($out, $blockPosition->getY());
		}else{
			VarInt::writeUnsignedInt($out, Binary::unsignInt($blockPosition->getY()));
		}
		VarInt::writeSignedInt($out, $blockPosition->getZ());
	}

	/**
	 * Reads a floating-point Vector3 object with coordinates rounded to 4 decimal places.
	 *
	 * @throws DataDecodeException
	 */
	public static function getVector3(ByteBufferReader $in) : Vector3{
		$x = LE::readFloat($in);
		$y = LE::readFloat($in);
		$z = LE::readFloat($in);
		return new Vector3($x, $y, $z);
	}

	/**
	 * Reads a floating-point Vector2 object with coordinates rounded to 4 decimal places.
	 *
	 * @throws DataDecodeException
	 */
	public static function getVector2(ByteBufferReader $in) : Vector2{
		$x = LE::readFloat($in);
		$y = LE::readFloat($in);
		return new Vector2($x, $y);
	}

	/**
	 * Writes a floating-point Vector3 object, or 3x zero if null is given.
	 *
	 * Note: ONLY use this where it is reasonable to allow not specifying the vector.
	 * For all other purposes, use the non-nullable version.
	 *
	 * @see CommonTypes::putVector3()
	 */
	public static function putVector3Nullable(ByteBufferWriter $out, ?Vector3 $vector) : void{
		if($vector !== null){
			self::putVector3($out, $vector);
		}else{
			LE::writeFloat($out, 0.0);
			LE::writeFloat($out, 0.0);
			LE::writeFloat($out, 0.0);
		}
	}

	/**
	 * Writes a floating-point Vector3 object
	 */
	public static function putVector3(ByteBufferWriter $out, Vector3 $vector) : void{
		LE::writeFloat($out, $vector->x);
		LE::writeFloat($out, $vector->y);
		LE::writeFloat($out, $vector->z);
	}

	/**
	 * Writes a floating-point Vector2 object
	 */
	public static function putVector2(ByteBufferWriter $out, Vector2 $vector2) : void{
		LE::writeFloat($out, $vector2->x);
		LE::writeFloat($out, $vector2->y);
	}

	/** @throws DataDecodeException */
	public static function getRotationByte(ByteBufferReader $in) : float{
		return Byte::readUnsigned($in) * (1.40625);
	}

	public static function putRotationByte(ByteBufferWriter $out, float $rotation) : void{
		Byte::writeUnsigned($out, (int) ($rotation / (1.40625)));
	}

	/** @throws DataDecodeException */
	private static function readGameRule(ByteBufferReader $in, int $type, bool $isPlayerModifiable, bool $isStartGame) : GameRule{
		return match($type){
			BoolGameRule::ID => BoolGameRule::decode($in, $isPlayerModifiable),
			IntGameRule::ID => IntGameRule::decode($in, $isPlayerModifiable, $isStartGame),
			FloatGameRule::ID => FloatGameRule::decode($in, $isPlayerModifiable),
			default => throw new PacketDecodeException("Unknown gamerule type $type"),
		};
	}

	/**
	 * Reads gamerules
	 *
	 * @return GameRule[] game rule name => value
	 * @phpstan-return array<string, GameRule>
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	public static function getGameRules(ByteBufferReader $in, bool $isStartGame) : array{
		$count = VarInt::readUnsignedInt($in);
		$rules = [];
		for($i = 0; $i < $count; ++$i){
			$name = self::getString($in);
			$isPlayerModifiable = self::getBool($in);
			$type = VarInt::readUnsignedInt($in);
			$rules[$name] = self::readGameRule($in, $type, $isPlayerModifiable, $isStartGame);
		}

		return $rules;
	}

	/**
	 * Writes a gamerule array
	 *
	 * @param GameRule[] $rules
	 * @phpstan-param array<string, GameRule> $rules
	 */
	public static function putGameRules(ByteBufferWriter $out, array $rules, bool $isStartGame) : void{
		VarInt::writeUnsignedInt($out, count($rules));
		foreach($rules as $name => $rule){
			self::putString($out, $name);
			self::putBool($out, $rule->isPlayerModifiable());
			VarInt::writeUnsignedInt($out, $rule->getTypeId());
			$rule->encode($out, $isStartGame);
		}
	}

	/** @throws DataDecodeException */
	public static function getEntityLink(ByteBufferReader $in) : EntityLink{
		$fromActorUniqueId = self::getActorUniqueId($in);
		$toActorUniqueId = self::getActorUniqueId($in);
		$type = Byte::readUnsigned($in);
		$immediate = self::getBool($in);
		$causedByRider = self::getBool($in);
		$vehicleAngularVelocity = LE::readFloat($in);
		return new EntityLink($fromActorUniqueId, $toActorUniqueId, $type, $immediate, $causedByRider, $vehicleAngularVelocity);
	}

	public static function putEntityLink(ByteBufferWriter $out, EntityLink $link) : void{
		self::putActorUniqueId($out, $link->fromActorUniqueId);
		self::putActorUniqueId($out, $link->toActorUniqueId);
		Byte::writeUnsigned($out, $link->type);
		self::putBool($out, $link->immediate);
		self::putBool($out, $link->causedByRider);
		LE::writeFloat($out, $link->vehicleAngularVelocity);
	}

	/** @throws DataDecodeException */
	public static function getCommandOriginData(ByteBufferReader $in) : CommandOriginData{
		$result = new CommandOriginData();

		$result->type = CommonTypes::getString($in);
		$result->uuid = self::getUUID($in);
		$result->requestId = self::getString($in);
		$result->playerActorUniqueId = LE::readSignedLong($in);

		return $result;
	}

	public static function putCommandOriginData(ByteBufferWriter $out, CommandOriginData $data) : void{
		self::putString($out, $data->type);
		self::putUUID($out, $data->uuid);
		self::putString($out, $data->requestId);
		LE::writeSignedLong($out, $data->playerActorUniqueId);
	}

	/** @throws DataDecodeException */
	public static function getStructureSettings(ByteBufferReader $in) : StructureSettings{
		$result = new StructureSettings();

		$result->paletteName = self::getString($in);

		$result->ignoreEntities = self::getBool($in);
		$result->ignoreBlocks = self::getBool($in);
		$result->allowNonTickingChunks = self::getBool($in);

		$result->dimensions = self::getBlockPosition($in, true);
		$result->offset = self::getBlockPosition($in, true);

		$result->lastTouchedByPlayerID = self::getActorUniqueId($in);
		$result->rotation = Byte::readUnsigned($in);
		$result->mirror = Byte::readUnsigned($in);
		$result->animationMode = Byte::readUnsigned($in);
		$result->animationSeconds = LE::readFloat($in);
		$result->integrityValue = LE::readFloat($in);
		$result->integritySeed = LE::readUnsignedInt($in);
		$result->pivot = self::getVector3($in);

		return $result;
	}

	public static function putStructureSettings(ByteBufferWriter $out, StructureSettings $structureSettings) : void{
		self::putString($out, $structureSettings->paletteName);

		self::putBool($out, $structureSettings->ignoreEntities);
		self::putBool($out, $structureSettings->ignoreBlocks);
		self::putBool($out, $structureSettings->allowNonTickingChunks);

		self::putBlockPosition($out, $structureSettings->dimensions, true);
		self::putBlockPosition($out, $structureSettings->offset, true);

		self::putActorUniqueId($out, $structureSettings->lastTouchedByPlayerID);
		Byte::writeUnsigned($out, $structureSettings->rotation);
		Byte::writeUnsigned($out, $structureSettings->mirror);
		Byte::writeUnsigned($out, $structureSettings->animationMode);
		LE::writeFloat($out, $structureSettings->animationSeconds);
		LE::writeFloat($out, $structureSettings->integrityValue);
		LE::writeUnsignedInt($out, $structureSettings->integritySeed);
		self::putVector3($out, $structureSettings->pivot);
	}

	/** @throws DataDecodeException */
	public static function getStructureEditorData(ByteBufferReader $in) : StructureEditorData{
		$result = new StructureEditorData();

		$result->structureName = self::getString($in);
		$result->filteredStructureName = self::getString($in);
		$result->structureDataField = self::getString($in);

		$result->includePlayers = self::getBool($in);
		$result->showBoundingBox = self::getBool($in);

		$result->structureBlockType = VarInt::readSignedInt($in);
		$result->structureSettings = self::getStructureSettings($in);
		$result->structureRedstoneSaveMode = VarInt::readSignedInt($in);

		return $result;
	}

	public static function putStructureEditorData(ByteBufferWriter $out, StructureEditorData $structureEditorData) : void{
		self::putString($out, $structureEditorData->structureName);
		self::putString($out, $structureEditorData->filteredStructureName);
		self::putString($out, $structureEditorData->structureDataField);

		self::putBool($out, $structureEditorData->includePlayers);
		self::putBool($out, $structureEditorData->showBoundingBox);

		VarInt::writeSignedInt($out, $structureEditorData->structureBlockType);
		self::putStructureSettings($out, $structureEditorData->structureSettings);
		VarInt::writeSignedInt($out, $structureEditorData->structureRedstoneSaveMode);
	}

	/** @throws PacketDecodeException */
	public static function getNbtRoot(ByteBufferReader $in) : TreeRoot{
		$offset = $in->getOffset();
		try{
			return (new NetworkNbtSerializer())->read($in->getData(), $offset, 512);
		}catch(NbtDataException $e){
			throw PacketDecodeException::wrap($e, "Failed decoding NBT root");
		}finally{
			$in->setOffset($offset);
		}
	}

	public static function getNbtCompoundRoot(ByteBufferReader $in) : CompoundTag{
		try{
			return self::getNbtRoot($in)->mustGetCompoundTag();
		}catch(NbtDataException $e){
			throw PacketDecodeException::wrap($e, "Expected TAG_Compound NBT root");
		}
	}

	/** @throws DataDecodeException */
	public static function readRecipeNetId(ByteBufferReader $in) : int{
		return VarInt::readUnsignedInt($in);
	}

	public static function writeRecipeNetId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeUnsignedInt($out, $id);
	}

	/** @throws DataDecodeException */
	public static function readCreativeItemNetId(ByteBufferReader $in) : int{
		return VarInt::readUnsignedInt($in);
	}

	public static function writeCreativeItemNetId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeUnsignedInt($out, $id);
	}

	/**
	 * This is a union of ItemStackRequestId, LegacyItemStackRequestId, and ServerItemStackId, used in serverbound
	 * packets to allow the client to refer to server known items, or items which may have been modified by a previous
	 * as-yet unacknowledged request from the client.
	 *
	 * - Server itemstack ID is positive
	 * - InventoryTransaction "legacy" request ID is negative and even
	 * - ItemStackRequest request ID is negative and odd
	 * - 0 refers to an empty itemstack (air)
	 *
	 * @throws DataDecodeException
	 */
	public static function readItemStackNetIdVariant(ByteBufferReader $in) : int{
		return LE::readSignedInt($in);
	}

	/**
	 * This is a union of ItemStackRequestId, LegacyItemStackRequestId, and ServerItemStackId, used in serverbound
	 * packets to allow the client to refer to server known items, or items which may have been modified by a previous
	 * as-yet unacknowledged request from the client.
	 */
	public static function writeItemStackNetIdVariant(ByteBufferWriter $out, int $id) : void{
		LE::writeSignedInt($out, $id);
	}

	/** @throws DataDecodeException */
	public static function readItemStackRequestId(ByteBufferReader $in) : int{
		return VarInt::readSignedInt($in);
	}

	public static function writeItemStackRequestId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeSignedInt($out, $id);
	}

	/** @throws DataDecodeException */
	public static function readLegacyItemStackRequestId(ByteBufferReader $in) : int{
		return VarInt::readSignedInt($in);
	}

	public static function writeLegacyItemStackRequestId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeSignedInt($out, $id);
	}

	/** @throws DataDecodeException */
	public static function readServerItemStackId(ByteBufferReader $in) : int{
		return VarInt::readSignedInt($in);
	}

	public static function writeServerItemStackId(ByteBufferWriter $out, int $id) : void{
		VarInt::writeSignedInt($out, $id);
	}

	/**
	 * @phpstan-template T
	 * @phpstan-param \Closure(ByteBufferReader) : (T|null) $reader
	 * @phpstan-return T|null
	 * @throws DataDecodeException
	 */
	public static function readOptional(ByteBufferReader $in, \Closure $reader) : mixed{
		if(self::getBool($in)){
			return $reader($in);
		}
		return null;
	}

	/**
	 * @phpstan-template T
	 * @phpstan-param T|null $value
	 * @phpstan-param \Closure(ByteBufferWriter, T) : void $writer
	 */
	public static function writeOptional(ByteBufferWriter $out, mixed $value, \Closure $writer) : void{
		if($value !== null){
			self::putBool($out, true);
			$writer($out, $value);
		}else{
			self::putBool($out, false);
		}
	}

	/**
	 * @phpstan-template T
	 * @phpstan-param \Closure(ByteBufferReader) : T $reader
	 * @phpstan-return list<T>
	 * @throws DataDecodeException
	 */
	public static function readList(ByteBufferReader $in, \Closure $reader) : array{
		$count = VarInt::readUnsignedInt($in);
		$result = [];
		for($i = 0; $i < $count; ++$i){
			$result[] = $reader($in);
		}
		return $result;
	}

	/**
	 * @phpstan-template T
	 * @phpstan-param array<T> $list
	 * @phpstan-param \Closure(ByteBufferWriter, T) : void $writer
	 */
	public static function writeList(ByteBufferWriter $out, array $list, \Closure $writer) : void{
		VarInt::writeUnsignedInt($out, count($list));
		foreach($list as $item){
			$writer($out, $item);
		}
	}

}
