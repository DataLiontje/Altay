<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

/**
 * All the entity classes
 */

namespace pocketmine\entity;

use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\item\Item;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Bearing;
use pocketmine\math\Facing;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\metadata\Metadatable;
use pocketmine\metadata\MetadataValue;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\EntityEventPacket;
use pocketmine\network\mcpe\protocol\MoveEntityAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\SetEntityDataPacket;
use pocketmine\network\mcpe\protocol\SetEntityLinkPacket;
use pocketmine\network\mcpe\protocol\SetEntityMotionPacket;
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\Random;
use pocketmine\utils\UUID;
use function abs;
use function assert;
use function cos;
use function count;
use function deg2rad;
use function floor;
use function get_class;
use function is_array;
use function is_infinite;
use function is_nan;
use function lcg_value;
use function sin;
use const M_PI_2;

abstract class Entity extends Location implements Metadatable, EntityIds{

	public const MOTION_THRESHOLD = 0.00001;

	public const NETWORK_ID = -1;

	public const DATA_TYPE_BYTE = 0;
	public const DATA_TYPE_SHORT = 1;
	public const DATA_TYPE_INT = 2;
	public const DATA_TYPE_FLOAT = 3;
	public const DATA_TYPE_STRING = 4;
	public const DATA_TYPE_SLOT = 5;
	public const DATA_TYPE_POS = 6;
	public const DATA_TYPE_LONG = 7;
	public const DATA_TYPE_VECTOR3F = 8;

	public const DATA_FLAGS = 0;
	public const DATA_HEALTH = 1; //int (minecart/boat)
	public const DATA_VARIANT = 2; //int
	public const DATA_COLOR = 3, DATA_COLOUR = 3; //byte
	public const DATA_NAMETAG = 4; //string
	public const DATA_OWNER_EID = 5; //long
	public const DATA_TARGET_EID = 6; //long
	public const DATA_AIR = 7; //short
	public const DATA_POTION_COLOR = 8; //int (ARGB!)
	public const DATA_POTION_AMBIENT = 9; //byte
	/* 10 (byte) */
	public const DATA_HURT_TIME = 11; //int (minecart/boat)
	public const DATA_HURT_DIRECTION = 12; //int (minecart/boat)
	public const DATA_PADDLE_TIME_LEFT = 13; //float
	public const DATA_PADDLE_TIME_RIGHT = 14; //float
	public const DATA_EXPERIENCE_VALUE = 15; //int (xp orb)
	public const DATA_MINECART_DISPLAY_BLOCK = 16, DATA_DISPLAY_ITEM = 16; //int (id | (data << 16))
	public const DATA_MINECART_DISPLAY_OFFSET = 17, DATA_DISPLAY_OFFSET = 17; //int
	public const DATA_MINECART_HAS_DISPLAY = 18, DATA_HAS_DISPLAY = 18; //byte (must be 1 for minecart to show block inside)

	//TODO: add more properties

	public const DATA_ENDERMAN_HELD_ITEM_ID = 23; //short
	public const DATA_ENTITY_AGE = 24; //short

	/* 26 (byte) player-specific flags
	 * 27 (int) player "index"?
	 * 28 (block coords) bed position */
	public const DATA_FIREBALL_POWER_X = 29; //float
	public const DATA_FIREBALL_POWER_Y = 30;
	public const DATA_FIREBALL_POWER_Z = 31;
	/* 32 (unknown)
	 * 33 (float) fishing bobber
	 * 34 (float) fishing bobber
	 * 35 (float) fishing bobber */
	public const DATA_POTION_AUX_VALUE = 36; //short
	public const DATA_LEAD_HOLDER_EID = 37; //long
	public const DATA_SCALE = 38; //float
	public const DATA_INTERACTIVE_TAG = 39; //string (button text)
	public const DATA_NPC_SKIN_ID = 40; //string
	public const DATA_URL_TAG = 41; //string
	public const DATA_MAX_AIR = 42; //short
	public const DATA_MARK_VARIANT = 43; //int
	public const DATA_CONTAINER_TYPE = 44; //byte (ContainerComponent)
	public const DATA_CONTAINER_BASE_SIZE = 45; //int (ContainerComponent)
	public const DATA_CONTAINER_EXTRA_SLOTS_PER_STRENGTH = 46; //int (used for llamas, inventory size is baseSize + thisProp * strength)
	public const DATA_BLOCK_TARGET = 47; //block coords (ender crystal)
	public const DATA_WITHER_INVULNERABLE_TICKS = 48; //int
	public const DATA_WITHER_TARGET_1 = 49; //long
	public const DATA_WITHER_TARGET_2 = 50; //long
	public const DATA_WITHER_TARGET_3 = 51; //long
	/* 52 (short) */
	public const DATA_BOUNDING_BOX_WIDTH = 53; //float
	public const DATA_BOUNDING_BOX_HEIGHT = 54; //float
	public const DATA_FUSE_LENGTH = 55; //int
	public const DATA_RIDER_SEAT_POSITION = 56; //vector3f
	public const DATA_RIDER_ROTATION_LOCKED = 57; //byte
	public const DATA_RIDER_MAX_ROTATION = 58; //float
	public const DATA_RIDER_MIN_ROTATION = 59; //float
	public const DATA_AREA_EFFECT_CLOUD_RADIUS = 60; //float
	public const DATA_AREA_EFFECT_CLOUD_WAITING = 61; //int
	public const DATA_AREA_EFFECT_CLOUD_PARTICLE_ID = 62; //int
	/* 63 (int) shulker-related */
	public const DATA_SHULKER_ATTACH_FACE = 64; //byte
	/* 65 (short) shulker-related */
	public const DATA_SHULKER_ATTACH_POS = 66; //block coords
	public const DATA_TRADING_PLAYER_EID = 67; //long

	/* 69 (byte) command-block */
	public const DATA_COMMAND_BLOCK_COMMAND = 70; //string
	public const DATA_COMMAND_BLOCK_LAST_OUTPUT = 71; //string
	public const DATA_COMMAND_BLOCK_TRACK_OUTPUT = 72; //byte
	public const DATA_CONTROLLING_RIDER_SEAT_NUMBER = 73; //byte
	public const DATA_STRENGTH = 74; //int
	public const DATA_MAX_STRENGTH = 75; //int
	/* 76 (int) */
	public const DATA_LIMITED_LIFE = 77;
	public const DATA_ARMOR_STAND_POSE_INDEX = 78; //int
	public const DATA_ENDER_CRYSTAL_TIME_OFFSET = 79; //int
	public const DATA_ALWAYS_SHOW_NAMETAG = 80; //byte: -1 = default, 0 = only when looked at, 1 = always
	public const DATA_COLOR_2 = 81; //byte
	/* 82 (unknown) */
	public const DATA_SCORE_TAG = 83; //string
	public const DATA_BALLOON_ATTACHED_ENTITY = 84; //int64, entity unique ID of owner
	public const DATA_PUFFERFISH_SIZE = 85; //byte
	public const DATA_BOAT_BUBBLE_TIME = 86; //int (time in bubble column)
	public const DATA_PLAYER_AGENT_EID = 87; //long
	/* 88 (float) related to panda sitting
	 * 89 (float) related to panda sitting
	 * 90 (unknown) */
	public const DATA_FLAGS2 = 91; //long (extended data flags)
	/* 92 (float) related to panda lying down
	 * 93 (float) related to panda lying down */


	public const DATA_FLAG_ONFIRE = 0;
	public const DATA_FLAG_SNEAKING = 1;
	public const DATA_FLAG_RIDING = 2;
	public const DATA_FLAG_SPRINTING = 3;
	public const DATA_FLAG_ACTION = 4;
	public const DATA_FLAG_INVISIBLE = 5;
	public const DATA_FLAG_TEMPTED = 6;
	public const DATA_FLAG_INLOVE = 7;
	public const DATA_FLAG_SADDLED = 8;
	public const DATA_FLAG_POWERED = 9;
	public const DATA_FLAG_IGNITED = 10;
	public const DATA_FLAG_BABY = 11;
	public const DATA_FLAG_CONVERTING = 12;
	public const DATA_FLAG_CRITICAL = 13;
	public const DATA_FLAG_CAN_SHOW_NAMETAG = 14;
	public const DATA_FLAG_ALWAYS_SHOW_NAMETAG = 15;
	public const DATA_FLAG_IMMOBILE = 16, DATA_FLAG_NO_AI = 16;
	public const DATA_FLAG_SILENT = 17;
	public const DATA_FLAG_WALLCLIMBING = 18;
	public const DATA_FLAG_CAN_CLIMB = 19;
	public const DATA_FLAG_SWIMMER = 20;
	public const DATA_FLAG_CAN_FLY = 21;
	public const DATA_FLAG_WALKER = 22;
	public const DATA_FLAG_RESTING = 23;
	public const DATA_FLAG_SITTING = 24;
	public const DATA_FLAG_ANGRY = 25;
	public const DATA_FLAG_INTERESTED = 26;
	public const DATA_FLAG_CHARGED = 27;
	public const DATA_FLAG_TAMED = 28;
	public const DATA_FLAG_ORPHANED = 29;
	public const DATA_FLAG_LEASHED = 30;
	public const DATA_FLAG_SHEARED = 31;
	public const DATA_FLAG_GLIDING = 32;
	public const DATA_FLAG_ELDER = 33;
	public const DATA_FLAG_MOVING = 34;
	public const DATA_FLAG_BREATHING = 35;
	public const DATA_FLAG_CHESTED = 36;
	public const DATA_FLAG_STACKABLE = 37;
	public const DATA_FLAG_SHOWBASE = 38;
	public const DATA_FLAG_REARING = 39;
	public const DATA_FLAG_VIBRATING = 40;
	public const DATA_FLAG_IDLING = 41;
	public const DATA_FLAG_EVOKER_SPELL = 42;
	public const DATA_FLAG_CHARGE_ATTACK = 43;
	public const DATA_FLAG_WASD_CONTROLLED = 44;
	public const DATA_FLAG_CAN_POWER_JUMP = 45;
	public const DATA_FLAG_LINGER = 46;
	public const DATA_FLAG_HAS_COLLISION = 47;
	public const DATA_FLAG_AFFECTED_BY_GRAVITY = 48;
	public const DATA_FLAG_FIRE_IMMUNE = 49;
	public const DATA_FLAG_DANCING = 50;
	public const DATA_FLAG_ENCHANTED = 51;
	public const DATA_FLAG_SHOW_TRIDENT_ROPE = 52; // tridents show an animated rope when enchanted with loyalty after they are thrown and return to their owner. To be combined with DATA_OWNER_EID
	public const DATA_FLAG_CONTAINER_PRIVATE = 53; //inventory is private, doesn't drop contents when killed if true
	public const DATA_FLAG_TRANSFORMING = 54;
	public const DATA_FLAG_SPIN_ATTACK = 55;
	public const DATA_FLAG_SWIMMING = 56;
	public const DATA_FLAG_BRIBED = 57; //dolphins have this set when they go to find treasure for the player
	public const DATA_FLAG_PREGNANT = 58;
	public const DATA_FLAG_LAYING_EGG = 59;
	public const DATA_FLAG_RIDER_CAN_PICK = 60; //???
	public const DATA_FLAG_TRANSITION_SITTING = 61;
	public const DATA_FLAG_EATING = 62;
	public const DATA_FLAG_LAYING_DOWN = 63;
	public const DATA_FLAG_SNEEZING = 64;
	public const DATA_FLAG_TRUSTING = 65;
	public const DATA_FLAG_ROLLING = 66;
	public const DATA_FLAG_SCARED = 67;
	public const DATA_FLAG_IN_SCAFFOLDING = 68;
	public const DATA_FLAG_OVER_SCAFFOLDING = 69;
	public const DATA_FLAG_FALL_THROUGH_SCAFFOLDING = 70;

	public const SPAWN_PLACEMENT_TYPE = SpawnPlacementTypes::PLACEMENT_TYPE_ON_GROUND;

	/**
	 * @var Player[]
	 */
	protected $hasSpawned = [];

	/** @var int */
	protected $id;

	/** @var DataPropertyManager */
	protected $propertyManager;

	/** @var Chunk|null */
	public $chunk;

	/** @var EntityDamageEvent|null */
	protected $lastDamageCause = null;

	/** @var Block[] */
	protected $blocksAround = [];

	/** @var Location */
	protected $lastLocation;
	/** @var Vector3 */
	protected $motion;
	/** @var Vector3 */
	protected $lastMotion;
	/** @var bool */
	protected $forceMovementUpdate = false;

	/** @var Vector3 */
	public $temporalVector;

	/** @var AxisAlignedBB */
	public $boundingBox;
	/** @var bool */
	public $onGround = false;

	/** @var float */
	public $eyeHeight = null;

	/** @var float */
	public $height;
	/** @var float */
	public $width;

	/** @var float */
	protected $baseOffset = 0.0;

	/** @var float */
	private $health = 20.0;
	private $maxHealth = 20;

	/** @var float */
	protected $ySize = 0.0;
	/** @var float */
	protected $stepHeight = 0.0;
	/** @var bool */
	public $keepMovement = false;

	/** @var float */
	public $fallDistance = 0.0;
	/** @var int */
	public $ticksLived = 0;
	/** @var int */
	public $lastUpdate;
	/** @var int */
	public $fireTicks = 0;
	/** @var bool */
	public $canCollide = true;

	/** @var bool */
	protected $isStatic = false;

	/** @var bool */
	private $savedWithChunk = true;

	/** @var bool */
	public $isCollided = false;
	/** @var bool */
	public $isCollidedHorizontally = false;
	/** @var bool */
	public $isCollidedVertically = false;

	/** @var int */
	public $noDamageTicks = 0;
	/** @var bool */
	protected $justCreated = true;
	/** @var bool */
	private $invulnerable = false;

	/** @var AttributeMap */
	protected $attributeMap;

	/** @var float */
	protected $gravity;
	/** @var float */
	protected $drag;

	/** @var Server */
	protected $server;

	/** @var bool */
	protected $closed = false;
	/** @var bool */
	private $needsDespawn = false;

	/** @var TimingsHandler */
	protected $timings;

	/** @var bool */
	protected $constructed = false;

	/** @var float */
	protected $entityCollisionReduction = 0;

	/** @var Entity */
	protected $ridingEntity = null;
	/** @var Entity */
	protected $riddenByEntity = null;
	/** @var float */
	protected $entityRiderPitchDelta = 0;
	/** @var float */
	protected $entityRiderYawDelta = 0;
	/** @var Entity[] */
	public $passengers = [];
	/** @var Random */
	public $random;
	/** @var UUID|null */
	protected $uuid;

	public function __construct(Level $level, CompoundTag $nbt){
		$this->random = new Random($level->random->nextInt());
		$this->constructed = true;
		$this->timings = Timings::getEntityTimings($this);

		$this->temporalVector = new Vector3();

		if($this->eyeHeight === null){
			$this->eyeHeight = $this->height * 0.85;
		}

		$this->id = EntityFactory::nextRuntimeId();
		$this->server = $level->getServer();

		/** @var float[] $pos */
		$pos = $nbt->getListTag("Pos")->getAllValues();
		/** @var float[] $rotation */
		$rotation = $nbt->getListTag("Rotation")->getAllValues();

		parent::__construct($pos[0], $pos[1], $pos[2], $rotation[0], $rotation[1], $level);
		assert(!is_nan($this->x) and !is_infinite($this->x) and !is_nan($this->y) and !is_infinite($this->y) and !is_nan($this->z) and !is_infinite($this->z));

		$this->boundingBox = new AxisAlignedBB(0, 0, 0, 0, 0, 0);
		$this->recalculateBoundingBox();

		$this->chunk = $this->level->getChunkAtPosition($this, false);
		if($this->chunk === null){
			throw new \InvalidStateException("Cannot create entities in unloaded chunks");
		}

		if($nbt->hasTag("Motion", ListTag::class)){
			/** @var float[] $motion */
			$motion = $nbt->getListTag("Motion")->getAllValues();
			$this->setMotion($this->temporalVector->setComponents(...$motion));
		}

		$this->resetLastMovements();

		$this->propertyManager = new DataPropertyManager();

		$this->propertyManager->setLong(self::DATA_FLAGS, 0);
		$this->propertyManager->setShort(self::DATA_MAX_AIR, 400);
		$this->propertyManager->setString(self::DATA_NAMETAG, "");
		$this->propertyManager->setLong(self::DATA_LEAD_HOLDER_EID, -1);
		$this->propertyManager->setFloat(self::DATA_SCALE, 1);
		$this->propertyManager->setFloat(self::DATA_BOUNDING_BOX_WIDTH, $this->width);
		$this->propertyManager->setFloat(self::DATA_BOUNDING_BOX_HEIGHT, $this->height);

		$this->attributeMap = new AttributeMap();
		$this->addAttributes();

		$this->setGenericFlag(self::DATA_FLAG_AFFECTED_BY_GRAVITY, true);
		$this->setGenericFlag(self::DATA_FLAG_HAS_COLLISION, true);

		$this->initEntity($nbt);
		$this->propertyManager->clearDirtyProperties(); //Prevents resending properties that were set during construction

		$this->chunk->addEntity($this);
		$this->level->addEntity($this);

		$this->lastUpdate = $this->server->getTick();
		(new EntitySpawnEvent($this))->call();

		$this->scheduleUpdate();

	}

	/**
	 * @return string
	 */
	public function getNameTag() : string{
		return $this->propertyManager->getString(self::DATA_NAMETAG);
	}

	/**
	 * @return bool
	 */
	public function isNameTagVisible() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_CAN_SHOW_NAMETAG);
	}

	/**
	 * @return bool
	 */
	public function isNameTagAlwaysVisible() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_ALWAYS_SHOW_NAMETAG);
	}


	/**
	 * @param string $name
	 */
	public function setNameTag(string $name) : void{
		$this->propertyManager->setString(self::DATA_NAMETAG, $name);
	}

	/**
	 * @param bool $value
	 */
	public function setNameTagVisible(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_CAN_SHOW_NAMETAG, $value);
	}

	/**
	 * @param bool $value
	 */
	public function setNameTagAlwaysVisible(bool $value = true) : void{
		$this->propertyManager->setByte(self::DATA_ALWAYS_SHOW_NAMETAG, $value ? 1 : 0);
	}

	/**
	 * @return string|null
	 */
	public function getScoreTag() : ?string{
		return $this->propertyManager->getString(self::DATA_SCORE_TAG);
	}

	/**
	 * @param string $score
	 */
	public function setScoreTag(string $score) : void{
		$this->propertyManager->setString(self::DATA_SCORE_TAG, $score);
	}

	/**
	 * @return float
	 */
	public function getScale() : float{
		return $this->propertyManager->getFloat(self::DATA_SCALE);
	}

	/**
	 * @param float $value
	 */
	public function setScale(float $value) : void{
		if($value <= 0){
			throw new \InvalidArgumentException("Scale must be greater than 0");
		}
		$multiplier = $value / $this->getScale();

		$this->width *= $multiplier;
		$this->height *= $multiplier;
		$this->eyeHeight *= $multiplier;

		$this->recalculateBoundingBox();

		$this->propertyManager->setFloat(self::DATA_SCALE, $value);
	}

	public function isInLove() : bool{
		return $this->getDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_INLOVE);
	}

	public function setInLove(bool $value) : void{
		$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_INLOVE, $value);
	}

	public function isRiding() : bool{
		return $this->getDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_RIDING);
	}

	public function setRiding(bool $value) : void{
		$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_RIDING, $value);
	}

	public function getRidingEntity() : ?Entity{
		return $this->ridingEntity;
	}

	public function setRidingEntity(?Entity $ridingEntity = null) : void{
		$this->ridingEntity = $ridingEntity;
	}

	public function getRiddenByEntity() : ?Entity{
		return $this->riddenByEntity;
	}

	public function setRiddenByEntity(?Entity $riddenByEntity = null) : void{
		$this->riddenByEntity = $riddenByEntity;
	}

	public function isBaby() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_BABY);
	}

	public function setBaby(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_BABY, $value);
		$this->setScale($value ? 0.5 : 1.0);
	}

	/**
	 * @return bool
	 */
	public function isStatic() : bool{
		return $this->isStatic;
	}

	/**
	 * @param bool $static
	 */
	public function setStatic(bool $static) : void{
		$this->isStatic = $static;
	}

	public function getBoundingBox() : AxisAlignedBB{
		return $this->boundingBox;
	}

	protected function recalculateBoundingBox() : void{
		$halfWidth = $this->width / 2;

		$this->boundingBox->setBounds($this->x - $halfWidth, $this->y, $this->z - $halfWidth, $this->x + $halfWidth, $this->y + $this->height, $this->z + $halfWidth);
	}

	public function isAffectedByGravity() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_AFFECTED_BY_GRAVITY);
	}

	public function setAffectedByGravity(bool $value = true){
		$this->setGenericFlag(self::DATA_FLAG_AFFECTED_BY_GRAVITY, $value);
	}

	public function isSneaking() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_SNEAKING);
	}

	public function setSneaking(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_SNEAKING, $value);
	}

	public function isSprinting() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_SPRINTING);
	}

	public function setSprinting(bool $value = true) : void{
		if($value !== $this->isSprinting()){
			$this->setGenericFlag(self::DATA_FLAG_SPRINTING, $value);
			$attr = $this->attributeMap->getAttribute(Attribute::MOVEMENT_SPEED);
			$attr->setValue($value ? ($attr->getValue() * 1.3) : ($attr->getValue() / 1.3), false, true);
		}
	}

	public function isSwimming() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_SWIMMING);
	}

	public function setSwimming(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_SWIMMING, $value);
	}

	public function isSwimmer() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_SWIMMER);
	}

	public function setSwimmer(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_SWIMMER, $value);
	}

	public function isImmobile() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_IMMOBILE);
	}

	public function setImmobile(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_IMMOBILE, $value);
	}

	public function isInvisible() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_INVISIBLE);
	}

	public function setInvisible(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_INVISIBLE, $value);
	}

	public function isGliding() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_GLIDING);
	}

	public function setGliding(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_GLIDING, $value);
	}

	/**
	 * Returns whether the entity is able to climb blocks such as ladders or vines.
	 * @return bool
	 */
	public function canClimb() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_CAN_CLIMB);
	}

	/**
	 * Sets whether the entity is able to climb climbable blocks.
	 *
	 * @param bool $value
	 */
	public function setCanClimb(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_CAN_CLIMB, $value);
	}

	/**
	 * Returns whether the entity is able to fly
	 * @return bool
	 */
	public function canFly() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_CAN_FLY);
	}

	/**
	 * Sets whether the entity is able to fly
	 *
	 * @param bool $value
	 */
	public function setCanFly(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_CAN_FLY, $value);
	}

	/**
	 * Returns whether this entity is climbing a block. By default this is only true if the entity is climbing a ladder or vine or similar block.
	 *
	 * @return bool
	 */
	public function canClimbWalls() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_WALLCLIMBING);
	}

	/**
	 * Sets whether the entity is climbing a block. If true, the entity can climb anything.
	 *
	 * @param bool $value
	 */
	public function setCanClimbWalls(bool $value = true) : void{
		$this->setGenericFlag(self::DATA_FLAG_WALLCLIMBING, $value);
	}

	/**
	 * Returns the entity ID of the owning entity, or null if the entity doesn't have an owner.
	 * @return int|null
	 */
	public function getOwningEntityId() : ?int{
		return $this->propertyManager->getLong(self::DATA_OWNER_EID);
	}

	/**
	 * Returns the owning entity, or null if the entity was not found.
	 * @return Entity|null
	 */
	public function getOwningEntity() : ?Entity{
		$eid = $this->getOwningEntityId();
		if($eid !== null){
			return $this->server->findEntity($eid);
		}

		return null;
	}

	/**
	 * Sets the owner of the entity. Passing null will remove the current owner.
	 *
	 * @param Entity|null $owner
	 *
	 * @throws \InvalidArgumentException if the supplied entity is not valid
	 */
	public function setOwningEntity(?Entity $owner) : void{
		if($owner === null){
			$this->propertyManager->removeProperty(self::DATA_OWNER_EID);
		}elseif($owner->closed){
			throw new \InvalidArgumentException("Supplied owning entity is garbage and cannot be used");
		}else{
			$this->propertyManager->setLong(self::DATA_OWNER_EID, $owner->getId());
		}
	}

	/**
	 * Returns the entity ID of the entity's target, or null if it doesn't have a target.
	 * @return int|null
	 */
	public function getTargetEntityId() : ?int{
		return $this->propertyManager->getLong(self::DATA_TARGET_EID);
	}

	/**
	 * Returns the entity's target entity, or null if not found.
	 * This is used for things like hostile mobs attacking entities, and for fishing rods reeling hit entities in.
	 *
	 * @return Entity|null
	 */
	public function getTargetEntity() : ?Entity{
		$eid = $this->getTargetEntityId();
		if($eid !== null){
			return $this->server->findEntity($eid);
		}

		return null;
	}

	/**
	 * Sets the entity's target entity. Passing null will remove the current target.
	 *
	 * @param Entity|null $target
	 *
	 * @throws \InvalidArgumentException if the target entity is not valid
	 */
	public function setTargetEntity(?Entity $target) : void{
		if($target === null or $target->isClosed()){
			$this->propertyManager->removeProperty(self::DATA_TARGET_EID);
		}else{
			$this->propertyManager->setLong(self::DATA_TARGET_EID, $target->getId());
		}
	}

	/**
	 * Returns whether this entity will be saved when its chunk is unloaded.
	 * @return bool
	 */
	public function canSaveWithChunk() : bool{
		return $this->savedWithChunk;
	}

	/**
	 * Sets whether this entity will be saved when its chunk is unloaded. This can be used to prevent the entity being
	 * saved to disk.
	 *
	 * @param bool $value
	 */
	public function setCanSaveWithChunk(bool $value) : void{
		$this->savedWithChunk = $value;
	}

	public function saveNBT() : CompoundTag{
		$nbt = new CompoundTag();
		if(!($this instanceof Player)){
			$nbt->setString("id", EntityFactory::getSaveId(get_class($this)));

			if($this->getNameTag() !== ""){
				$nbt->setString("CustomName", $this->getNameTag());
				$nbt->setByte("CustomNameVisible", $this->isNameTagVisible() ? 1 : 0);
			}

			if($this->uuid !== null){
				$nbt->setString("UUID", $this->uuid->toString());
			}
		}

		$nbt->setTag(new ListTag("Pos", [
			new DoubleTag("", $this->x),
			new DoubleTag("", $this->y),
			new DoubleTag("", $this->z)
		]));

		$nbt->setTag(new ListTag("Motion", [
			new DoubleTag("", $this->motion->x),
			new DoubleTag("", $this->motion->y),
			new DoubleTag("", $this->motion->z)
		]));

		$nbt->setTag(new ListTag("Rotation", [
			new FloatTag("", $this->yaw),
			new FloatTag("", $this->pitch)
		]));

		$nbt->setFloat("FallDistance", $this->fallDistance);
		$nbt->setShort("Fire", $this->fireTicks);
		$nbt->setShort("Air", $this->propertyManager->getShort(self::DATA_AIR));
		$nbt->setByte("OnGround", $this->onGround ? 1 : 0);
		$nbt->setByte("Invulnerable", $this->invulnerable ? 1 : 0);

		// TODO: Save passengers

		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->fireTicks = $nbt->getShort("Fire", 0);
		if($this->isOnFire()){
			$this->setGenericFlag(self::DATA_FLAG_ONFIRE);
		}

		$this->propertyManager->setShort(self::DATA_AIR, $nbt->getShort("Air", 300));
		$this->onGround = $nbt->getByte("OnGround", 0) !== 0;
		$this->invulnerable = $nbt->getByte("Invulnerable", 0) !== 0;

		$this->fallDistance = $nbt->getFloat("FallDistance", 0.0);

		if($nbt->hasTag("CustomName", StringTag::class)){
			$this->setNameTag($nbt->getString("CustomName"));

			if($nbt->hasTag("CustomNameVisible", StringTag::class)){
				//Older versions incorrectly saved this as a string (see 890f72dbf23a77f294169b79590770470041adc4)
				$this->setNameTagVisible($nbt->getString("CustomNameVisible") !== "");
			}else{
				$this->setNameTagVisible($nbt->getByte("CustomNameVisible", 1) !== 0);
			}
		}

		if($this->uuid === null){
			if($nbt->hasTag("UUID", StringTag::class)){
				$this->uuid = UUID::fromString($nbt->getString("UUID"));
			}else{
				$this->uuid = UUID::fromRandom();
			}
		}
	}

	/**
	 * @return null|UUID
	 */
	public function getUniqueId() : ?UUID{
		return $this->uuid;
	}

	protected function addAttributes() : void{

	}

	/**
	 * @param EntityDamageEvent $source
	 */
	public function attack(EntityDamageEvent $source) : void{
		$source->call();
		if($source->isCancelled()){
			return;
		}

		$this->setLastDamageCause($source);

		$this->setHealth($this->getHealth() - $source->getFinalDamage());
	}

	/**
	 * @param EntityRegainHealthEvent $source
	 */
	public function heal(EntityRegainHealthEvent $source) : void{
		$source->call();
		if($source->isCancelled()){
			return;
		}

		$this->setHealth($this->getHealth() + $source->getAmount());
	}

	public function kill() : void{
		$this->health = 0;
		$this->dismountEntity(true);
		$this->scheduleUpdate();
		$this->onDeath();
	}

	protected function onDeath() : void{
		$ev = new EntityDeathEvent($this, $this->getDrops());
		$ev->call();
		foreach($ev->getDrops() as $drop){
			$this->level->dropItem($this, $drop);
		}
	}

	/**
	 * Called to tick entities while dead. Returns whether the entity should be flagged for despawn yet.
	 *
	 * @param int $tickDiff
	 *
	 * @return bool
	 */
	protected function onDeathUpdate(int $tickDiff) : bool{
		return true;
	}

	public function isAlive() : bool{
		return $this->health > 0;
	}

	/**
	 * @return float
	 */
	public function getHealth() : float{
		return $this->health;
	}

	/**
	 * Sets the health of the Entity. This won't send any update to the players
	 *
	 * @param float $amount
	 */
	public function setHealth(float $amount) : void{
		if($amount == $this->health){
			return;
		}

		if($amount <= 0){
			if($this->isAlive()){
				$this->health = 0;
				$this->kill();
			}
		}elseif($amount <= $this->getMaxHealth() or $amount < $this->health){
			$this->health = $amount;
		}else{
			$this->health = $this->getMaxHealth();
		}
	}

	/**
	 * @return int
	 */
	public function getMaxHealth() : int{
		return $this->maxHealth;
	}

	/**
	 * @param int $amount
	 */
	public function setMaxHealth(int $amount) : void{
		$this->maxHealth = $amount;
	}

	/**
	 * @param EntityDamageEvent|null $type
	 */
	public function setLastDamageCause(?EntityDamageEvent $type) : void{
		$this->lastDamageCause = $type;
	}

	/**
	 * @return EntityDamageEvent|null
	 */
	public function getLastDamageCause() : ?EntityDamageEvent{
		return $this->lastDamageCause;
	}

	public function getAttributeMap() : AttributeMap{
		return $this->attributeMap;
	}

	public function getDataPropertyManager() : DataPropertyManager{
		return $this->propertyManager;
	}

	public function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->ridingEntity instanceof Entity and $this->ridingEntity->isClosed()){
			$this->ridingEntity = null;
			$this->setRiding(false);
		}

		if($this->riddenByEntity instanceof Entity and $this->riddenByEntity->isClosed()){
			$this->riddenByEntity = null;
			unset($this->passengers[array_search($this->riddenByEntity, $this->passengers, true)]);
			$this->setGenericFlag(Entity::DATA_FLAG_WASD_CONTROLLED, false);
		}

		$this->justCreated = false;

		$changedProperties = $this->propertyManager->getDirty();
		if(!empty($changedProperties)){
			$this->sendData($this->hasSpawned, $changedProperties);
			$this->propertyManager->clearDirtyProperties();
		}

		$hasUpdate = false;

		$this->checkBlockCollision();
		$this->checkEntityCollision();

		if($this->y <= -16 and $this->isAlive()){
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_VOID, 10);
			$this->attack($ev);
			$hasUpdate = true;
		}

		if($this->isOnFire() and $this->doOnFireTick($tickDiff)){
			$hasUpdate = true;
		}

		if($this->noDamageTicks > 0){
			$this->noDamageTicks -= $tickDiff;
			if($this->noDamageTicks < 0){
				$this->noDamageTicks = 0;
			}
		}

		if($this->isGliding()) $this->resetFallDistance();

		$this->ticksLived += $tickDiff;

		return $hasUpdate;
	}

	public function isOnFire() : bool{
		return $this->fireTicks > 0;
	}

	public function setOnFire(int $seconds) : void{
		$ticks = $seconds * 20;
		if($ticks > $this->fireTicks){
			$this->fireTicks = $ticks;
		}

		$this->setGenericFlag(self::DATA_FLAG_ONFIRE, true);
	}

	/**
	 * @return int
	 */
	public function getFireTicks() : int{
		return $this->fireTicks;
	}

	/**
	 * @param int $fireTicks
	 */
	public function setFireTicks(int $fireTicks) : void{
		$this->fireTicks = $fireTicks;
	}

	public function extinguish() : void{
		$this->fireTicks = 0;
		$this->setGenericFlag(self::DATA_FLAG_ONFIRE, false);
	}

	public function isFireProof() : bool{
		return false;
	}

	protected function doOnFireTick(int $tickDiff = 1) : bool{
		if($this->isFireProof() and $this->fireTicks > 1){
			$this->fireTicks = 1;
		}else{
			$this->fireTicks -= $tickDiff;
		}

		if(($this->fireTicks % 20 === 0) or $tickDiff > 20){
			$this->dealFireDamage();
		}

		if(!$this->isOnFire()){
			$this->extinguish();
		}else{
			return true;
		}

		return false;
	}

	/**
	 * Called to deal damage to entities when they are on fire.
	 */
	protected function dealFireDamage() : void{
		$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FIRE_TICK, 1);
		$this->attack($ev);
	}

	public function canCollideWith(Entity $entity) : bool{
		return !$this->justCreated and $entity !== $this;
	}

	public function canBeCollidedWith() : bool{
		return $this->isAlive();
	}

	protected function updateMovement(bool $teleport = false) : void{
		$diffPosition = $this->distanceSquared($this->lastLocation);
		$diffRotation = ($this->yaw - $this->lastLocation->yaw) ** 2 + ($this->pitch - $this->lastLocation->pitch) ** 2;

		$diffMotion = $this->motion->subtract($this->lastMotion)->lengthSquared();

		if($teleport or $diffPosition > 0.0001 or $diffRotation > 1.0){
			$this->lastLocation = $this->asLocation();

			$this->broadcastMovement($teleport);
		}

		if($diffMotion > 0.0025 or ($diffMotion > 0.0001 and $this->motion->lengthSquared() <= 0.0001)){ //0.05 ** 2
			$this->lastMotion = clone $this->motion;

			$this->broadcastMotion();
		}
	}

	public function getOffsetPosition(Vector3 $vector3) : Vector3{
		return new Vector3($vector3->x, $vector3->y + $this->baseOffset, $vector3->z);
	}

	protected function broadcastMovement(bool $teleport = false) : void{
		if($this->isValid()){
			$pk = new MoveEntityAbsolutePacket();
			$pk->entityRuntimeId = $this->id;
			$pk->position = $this->getOffsetPosition($this);

			//this looks very odd but is correct as of 1.5.0.7
			//for arrows this is actually x/y/z rotation
			//for mobs x and y are used for pitch and yaw, and z is used for headyaw
			$pk->xRot = $this->pitch;
			$pk->yRot = $this->yaw;
			$pk->zRot = $this->headYaw ?? $this->yaw;

			if($teleport){
				$pk->flags |= MoveEntityAbsolutePacket::FLAG_TELEPORT;
			}
			if($this->onGround){
				$pk->flags |= MoveEntityAbsolutePacket::FLAG_GROUND;
			}

			$this->level->broadcastPacketToViewers($this, $pk);
		}
	}

	protected function broadcastMotion() : void{
		if($this->isValid()){
			$pk = new SetEntityMotionPacket();
			$pk->entityRuntimeId = $this->id;
			$pk->motion = $this->getMotion();

			$this->level->broadcastPacketToViewers($this, $pk);
		}
	}

	/**
	 * Pushes the this entity and other entity
	 *
	 * @param Entity $entity
	 */
	protected function applyEntityCollision(Entity $entity) : void{
		if($this->canBePushed() and !$this->isRiding() and !$entity->isRiding()){
			if(!($entity instanceof Player and $entity->isSpectator()) and !($this instanceof Player and $this->isSpectator())){
				$d0 = $entity->x - $this->x;
				$d1 = $entity->z - $this->z;
				$d2 = abs(max($d0, $d1));

				if($d2 >= 0.009999999776482582){
					$d2 = sqrt($d2);
					$d0 = $d0 / $d2;
					$d1 = $d1 / $d2;
					$d3 = 1 / $d2;

					if($d3 > 1) $d3 = 1;

					$d0 = $d0 * $d3;
					$d1 = $d1 * $d3;
					$d0 = $d0 * 0.05000000074505806;
					$d1 = $d1 * 0.05000000074505806;
					$d0 = $d0 * (1.0 - $this->entityCollisionReduction);
					$d1 = $d1 * (1.0 - $this->entityCollisionReduction);

					$this->motion = $this->motion->subtract($d0, 0, $d1);
					$entity->motion = $entity->motion->add($d0, 0, $d1);
				}
			}
		}
	}

	protected function applyDragBeforeGravity() : bool{
		return false;
	}

	protected function applyGravity() : void{
		$this->motion->y -= $this->gravity;
	}

	protected function tryChangeMovement() : void{
		$friction = 1 - $this->drag;

		if($this->applyDragBeforeGravity()){
			$this->motion->y *= $friction;
		}

		$this->applyGravity();

		if(!$this->applyDragBeforeGravity()){
			$this->motion->y *= $friction;
		}

		if($this->onGround){
			$friction *= $this->level->getBlockAt((int) floor($this->x), (int) floor($this->y - 1), (int) floor($this->z))->getFrictionFactor();
		}

		$this->motion->x *= $friction;
		$this->motion->z *= $friction;
	}

	protected function checkObstruction(float $x, float $y, float $z) : bool{
		if(count($this->level->getCollisionCubes($this, $this->getBoundingBox(), false)) === 0){
			return false;
		}

		$floorX = (int) floor($x);
		$floorY = (int) floor($y);
		$floorZ = (int) floor($z);

		$diffX = $x - $floorX;
		$diffY = $y - $floorY;
		$diffZ = $z - $floorZ;

		if($this->level->getBlockAt($floorX, $floorY, $floorZ)->isSolid()){
			$westNonSolid = !$this->level->getBlockAt($floorX - 1, $floorY, $floorZ)->isSolid();
			$eastNonSolid = !$this->level->getBlockAt($floorX + 1, $floorY, $floorZ)->isSolid();
			$downNonSolid = !$this->level->getBlockAt($floorX, $floorY - 1, $floorZ)->isSolid();
			$upNonSolid = !$this->level->getBlockAt($floorX, $floorY + 1, $floorZ)->isSolid();
			$northNonSolid = !$this->level->getBlockAt($floorX, $floorY, $floorZ - 1)->isSolid();
			$southNonSolid = !$this->level->getBlockAt($floorX, $floorY, $floorZ + 1)->isSolid();

			$direction = -1;
			$limit = 9999;

			if($westNonSolid){
				$limit = $diffX;
				$direction = Facing::WEST;
			}

			if($eastNonSolid and 1 - $diffX < $limit){
				$limit = 1 - $diffX;
				$direction = Facing::EAST;
			}

			if($downNonSolid and $diffY < $limit){
				$limit = $diffY;
				$direction = Facing::DOWN;
			}

			if($upNonSolid and 1 - $diffY < $limit){
				$limit = 1 - $diffY;
				$direction = Facing::UP;
			}

			if($northNonSolid and $diffZ < $limit){
				$limit = $diffZ;
				$direction = Facing::NORTH;
			}

			if($southNonSolid and 1 - $diffZ < $limit){
				$direction = Facing::SOUTH;
			}

			$force = lcg_value() * 0.2 + 0.1;

			if($direction === Facing::WEST){
				$this->motion->x = -$force;

				return true;
			}

			if($direction === Facing::EAST){
				$this->motion->x = $force;

				return true;
			}

			if($direction === Facing::DOWN){
				$this->motion->y = -$force;

				return true;
			}

			if($direction === Facing::UP){
				$this->motion->y = $force;

				return true;
			}

			if($direction === Facing::NORTH){
				$this->motion->z = -$force;

				return true;
			}

			if($direction === Facing::SOUTH){
				$this->motion->z = $force;

				return true;
			}
		}

		return false;
	}

	public function getDirection() : int{
		return Bearing::fromAngle($this->yaw);
	}

	public function getHorizontalFacing() : int{
		return Bearing::toFacing($this->getDirection());
	}

	/**
	 * @return Vector3
	 */
	public function getDirectionVector() : Vector3{
		$y = -sin(deg2rad($this->pitch));
		$xz = cos(deg2rad($this->pitch));
		$x = -$xz * sin(deg2rad($this->yaw));
		$z = $xz * cos(deg2rad($this->yaw));

		return $this->temporalVector->setComponents($x, $y, $z)->normalize();
	}

	public function getDirectionPlane() : Vector2{
		return (new Vector2(-cos(deg2rad($this->yaw) - M_PI_2), -sin(deg2rad($this->yaw) - M_PI_2)))->normalize();
	}

	public function onUpdate(int $currentTick) : bool{
		if($this->closed){
			return false;
		}

		$tickDiff = $currentTick - $this->lastUpdate;
		if($tickDiff <= 0){
			if(!$this->justCreated){
				$this->server->getLogger()->debug("Expected tick difference of at least 1, got $tickDiff for " . get_class($this));
			}

			return true;
		}

		$this->lastUpdate = $currentTick;

		if(!$this->isAlive()){
			if($this->onDeathUpdate($tickDiff)){
				$this->flagForDespawn();
			}

			return true;
		}

		$this->timings->startTiming();

		if($this->hasMovementUpdate()){
			$this->tryChangeMovement();

			if(abs($this->motion->x) <= self::MOTION_THRESHOLD){
				$this->motion->x = 0;
			}
			if(abs($this->motion->y) <= self::MOTION_THRESHOLD){
				$this->motion->y = 0;
			}
			if(abs($this->motion->z) <= self::MOTION_THRESHOLD){
				$this->motion->z = 0;
			}

			if($this->motion->x != 0 or $this->motion->y != 0 or $this->motion->z != 0){
				$this->move($this->motion->x, $this->motion->y, $this->motion->z);
			}

			$this->forceMovementUpdate = false;
		}

		$this->updateMovement();

		Timings::$timerEntityBaseTick->startTiming();
		$hasUpdate = $this->entityBaseTick($tickDiff);
		Timings::$timerEntityBaseTick->stopTiming();

		$this->timings->stopTiming();

		return ($hasUpdate or $this->hasMovementUpdate()) or $this->isStatic();
	}

	final public function scheduleUpdate() : void{
		if($this->closed){
			throw new \InvalidStateException("Cannot schedule update on garbage entity " . get_class($this));
		}
		$this->level->updateEntities[$this->id] = $this;
	}

	public function onNearbyBlockChange() : void{
		$this->setForceMovementUpdate();
		$this->scheduleUpdate();
	}

	/**
	 * Flags the entity as needing a movement update on the next tick. Setting this forces a movement update even if the
	 * entity's motion is zero. Used to trigger movement updates when blocks change near entities.
	 *
	 * @param bool $value
	 */
	final public function setForceMovementUpdate(bool $value = true) : void{
		$this->forceMovementUpdate = $value;

		$this->blocksAround = null;
	}

	/**
	 * Returns whether the entity needs a movement update on the next tick.
	 * @return bool
	 */
	public function hasMovementUpdate() : bool{
		return ($this->forceMovementUpdate or $this->motion->x != 0 or $this->motion->y != 0 or $this->motion->z != 0 or !$this->onGround);
	}

	public function canBePushed() : bool{
		return false;
	}

	public function canTriggerWalking() : bool{
		return true;
	}

	public function resetFallDistance() : void{
		$this->fallDistance = 0.0;
	}

	/**
	 * @param float $distanceThisTick
	 * @param bool  $onGround
	 */
	protected function updateFallState(float $distanceThisTick, bool $onGround) : void{
		$block = $this->level->getBlock($this->subtract(0, 0.20000000298023224, 0));
		if($onGround){
			if($this->fallDistance > 0){
				if($block->isSolid()){
					$block->onEntityFallenUpon($this, $this->fallDistance);
				}

				$this->fall($this->fallDistance);
				$this->resetFallDistance();
			}
		}elseif($distanceThisTick < 0){
			$this->fallDistance -= $distanceThisTick;
		}
	}

	public function handleWaterMovement() : void{
		if($this->isUnderwater()){
			$this->motion->x *= 0.2;
			$this->motion->z *= 0.2;
		}
	}

	public function mountEntity(Entity $entity, int $seatNumber = 0) : bool{
		if($this->ridingEntity == null and $entity !== $this and count($entity->passengers) < $entity->getSeatCount()){
			if(!isset($entity->passengers[$seatNumber])){

				if($seatNumber === 0){
					$entity->setRiddenByEntity($this);

					$this->setRiding(true);
					$entity->setGenericFlag(self::DATA_FLAG_WASD_CONTROLLED, true);
				}

				$this->setRotation($entity->yaw, $entity->pitch);
				$this->setRidingEntity($entity);

				$entity->passengers[$seatNumber] = $this;

				$this->propertyManager->setVector3(self::DATA_RIDER_SEAT_POSITION, $entity->getRiderSeatPosition($seatNumber)->add(0, $this->getMountedYOffset(), 0));
				$this->propertyManager->setByte(self::DATA_CONTROLLING_RIDER_SEAT_NUMBER, $seatNumber);

				$entity->sendLink($entity->getViewers(), $this, EntityLink::TYPE_RIDER);

				$entity->onRiderMount($this);

				return true;
			}
		}
		return false;
	}

	/**
	 * @param Entity $entity
	 */
	public function onRiderMount(Entity $entity) : void{

	}

	/**
	 * @param Entity $entity
	 */
	public function onRiderLeave(Entity $entity) : void{

	}

	/**
	 * @param Player[] $targets
	 * @param Entity   $entity
	 * @param int      $type
	 * @param bool     $immediate
	 */
	public function sendLink(array $targets, Entity $entity, int $type = EntityLink::TYPE_RIDER, bool $immediate = false) : void{
		$pk = new SetEntityLinkPacket();
		$pk->link = new EntityLink($this->id, $entity->getId(), $type, $immediate);

		$this->server->broadcastPacket($targets, $pk);
	}

	public function getMountedYOffset() : float{
		return $this->height * 0.65;
	}

	public function dismountEntity(bool $immediate = false) : bool{
		if($this->ridingEntity !== null){
			$entity = $this->ridingEntity;

			unset($entity->passengers[array_search($this, $entity->passengers, true)]);

			if($this->isRiding()){
				$entity->setRiddenByEntity(null);

				$this->entityRiderYawDelta = 0;
				$this->entityRiderPitchDelta = 0;

				$this->setRiding(false);
				$entity->setGenericFlag(Entity::DATA_FLAG_WASD_CONTROLLED, false);
			}

			$this->propertyManager->removeProperty(self::DATA_RIDER_SEAT_POSITION);
			$this->propertyManager->removeProperty(self::DATA_CONTROLLING_RIDER_SEAT_NUMBER);

			$this->setRidingEntity(null);

			$entity->sendLink($entity->getViewers(), $this, EntityLink::TYPE_REMOVE, $immediate);

			$entity->onRiderLeave($this);

			return true;
		}
		return false;
	}

	public function getRiderSeatPosition(int $seatNumber = 0) : Vector3{
		return new Vector3(0, 0, 0);
	}

	public function getSeatCount() : int{
		return 1;
	}

	public function updateRiderPosition() : void{
		if($this->riddenByEntity !== null){
			$this->riddenByEntity->setPosition($this->add($this->getRiderSeatPosition()));
		}
	}

	public function updateRidden() : void{
		if($this->ridingEntity === null) return;

		if($this->ridingEntity->isClosed()){
			$this->ridingEntity = null;
		}else{
			$this->resetMotion();

			if(!($this instanceof Player)){
				$this->ridingEntity->updateRiderPosition();
			}
			$this->entityRiderYawDelta += $this->yaw - $this->lastLocation->yaw;

			for($this->entityRiderPitchDelta += $this->pitch - $this->lastLocation->pitch; $this->entityRiderYawDelta >= 180; $this->entityRiderYawDelta -= 360){
				//empty
			}

			while($this->entityRiderYawDelta < -180){
				$this->entityRiderYawDelta += 360;
			}

			while($this->entityRiderPitchDelta >= 180){
				$this->entityRiderPitchDelta -= 360;
			}

			while($this->entityRiderPitchDelta < -180){
				$this->entityRiderPitchDelta += 360;
			}

			$d0 = $this->entityRiderYawDelta * 0.5;
			$d1 = $this->entityRiderPitchDelta * 0.5;
			$f = 10;

			$d0 = ($d0 > $f) ? $f : (($d0 < -$f) ? -$f : $d0);
			$d1 = ($d1 > $f) ? $f : (($d1 < -$f) ? -$f : $d1);

			$this->entityRiderYawDelta -= $d0;
			$this->entityRiderPitchDelta -= $d1;
		}
	}

	/**
	 * Called when a falling entity hits the ground.
	 *
	 * @param float $fallDistance
	 */
	public function fall(float $fallDistance) : void{
		if($this->riddenByEntity instanceof Entity){
			$this->riddenByEntity->fall($fallDistance);
		}
	}

	public function getEyeHeight() : float{
		return $this->eyeHeight;
	}

	public function moveFlying(float $strafe, float $forward, float $friction) : bool{
		$f = $strafe * $strafe + $forward * $forward;
		if($f >= 1.0){
			$f = sqrt($f);

			if($f < 1) $f = 1;

			$f = $friction / $f;
			$strafe *= $f;
			$forward *= $f;

			$f1 = sin($this->yaw * pi() / 180);
			$f2 = cos($this->yaw * pi() / 180);

			$this->motion->x += $strafe * $f2 - $forward * $f1;
			$this->motion->z += $forward * $f2 + $strafe * $f1;

			return true;
		}

		return false;
	}

	public function onCollideWithPlayer(Player $player) : void{

	}

	public function onCollideWithEntity(Entity $entity) : void{
		$entity->applyEntityCollision($this);
	}

	public function isUnderwater() : bool{
		$block = $this->level->getBlockAt((int) floor($this->x), (int) floor($y = ($this->y + $this->getEyeHeight())), (int) floor($this->z));

		if($block instanceof Water){
			$f = ($block->y + 1) - ($block->getFluidHeightPercent() - 0.1111111);
			return $y < $f;
		}

		return false;
	}

	public function isWet() : bool{
		// TODO: check weather
		return $this->level->getBlock($this) instanceof Water;
	}

	public function isInsideOfSolid() : bool{
		$block = $this->level->getBlockAt((int) floor($this->x), (int) floor($y = ($this->y + $this->getEyeHeight())), (int) floor($this->z));

		return $block->isSolid() and !$block->isTransparent() and $block->collidesWithBB($this->getBoundingBox());
	}

	public function move(float $dx, float $dy, float $dz) : void{
		$this->blocksAround = null;

		Timings::$entityMoveTimer->startTiming();

		$movX = $dx;
		$movY = $dy;
		$movZ = $dz;

		if($this->keepMovement){
			$this->boundingBox->offset($dx, $dy, $dz);
		}else{
			$this->ySize *= 0.4;

			/*
			if($this->isColliding){ //With cobweb?
				$this->isColliding = false;
				$dx *= 0.25;
				$dy *= 0.05;
				$dz *= 0.25;
				$this->motionX = 0;
				$this->motionY = 0;
				$this->motionZ = 0;
			}
			*/

			$axisalignedbb = clone $this->boundingBox;

			/*$sneakFlag = $this->onGround and $this instanceof Player;

			if($sneakFlag){
				for($mov = 0.05; $dx != 0.0 and count($this->level->getCollisionCubes($this, $this->boundingBox->getOffsetBoundingBox($dx, -1, 0))) === 0; $movX = $dx){
					if($dx < $mov and $dx >= -$mov){
						$dx = 0;
					}elseif($dx > 0){
						$dx -= $mov;
					}else{
						$dx += $mov;
					}
				}

				for(; $dz != 0.0 and count($this->level->getCollisionCubes($this, $this->boundingBox->getOffsetBoundingBox(0, -1, $dz))) === 0; $movZ = $dz){
					if($dz < $mov and $dz >= -$mov){
						$dz = 0;
					}elseif($dz > 0){
						$dz -= $mov;
					}else{
						$dz += $mov;
					}
				}

				//TODO: big messy loop
			}*/

			assert(abs($dx) <= 20 and abs($dy) <= 20 and abs($dz) <= 20, "Movement distance is excessive: dx=$dx, dy=$dy, dz=$dz");

			$list = $this->level->getCollisionCubes($this, $this->level->getTickRate() > 1 ? $this->boundingBox->offsetCopy($dx, $dy, $dz) : $this->boundingBox->addCoord($dx, $dy, $dz), false);

			foreach($list as $bb){
				$dy = $bb->calculateYOffset($this->boundingBox, $dy);
			}

			$this->boundingBox->offset(0, $dy, 0);

			$fallingFlag = ($this->onGround or ($dy != $movY and $movY < 0));

			foreach($list as $bb){
				$dx = $bb->calculateXOffset($this->boundingBox, $dx);
			}

			$this->boundingBox->offset($dx, 0, 0);

			foreach($list as $bb){
				$dz = $bb->calculateZOffset($this->boundingBox, $dz);
			}

			$this->boundingBox->offset(0, 0, $dz);


			if($this->stepHeight > 0 and $fallingFlag and $this->ySize < 0.05 and ($movX != $dx or $movZ != $dz)){
				$cx = $dx;
				$cy = $dy;
				$cz = $dz;
				$dx = $movX;
				$dy = $this->stepHeight;
				$dz = $movZ;

				$axisalignedbb1 = clone $this->boundingBox;

				$this->boundingBox->setBB($axisalignedbb);

				$list = $this->level->getCollisionCubes($this, $this->boundingBox->addCoord($dx, $dy, $dz), false);

				foreach($list as $bb){
					$dy = $bb->calculateYOffset($this->boundingBox, $dy);
				}

				$this->boundingBox->offset(0, $dy, 0);

				foreach($list as $bb){
					$dx = $bb->calculateXOffset($this->boundingBox, $dx);
				}

				$this->boundingBox->offset($dx, 0, 0);

				foreach($list as $bb){
					$dz = $bb->calculateZOffset($this->boundingBox, $dz);
				}

				$this->boundingBox->offset(0, 0, $dz);

				if(($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)){
					$dx = $cx;
					$dy = $cy;
					$dz = $cz;
					$this->boundingBox->setBB($axisalignedbb1);
				}else{
					$this->ySize += 0.5; //FIXME: this should be the height of the block it walked up, not fixed 0.5
				}
			}
		}

		$this->x = ($this->boundingBox->minX + $this->boundingBox->maxX) / 2;
		$this->y = $this->boundingBox->minY - $this->ySize;
		$this->z = ($this->boundingBox->minZ + $this->boundingBox->maxZ) / 2;

		$this->checkChunks();
		$this->checkBlockCollision();
		$this->checkEntityCollision();
		$this->checkGroundState($movX, $movY, $movZ, $dx, $dy, $dz);
		$this->updateFallState($dy, $this->onGround);

		if($movX != $dx){
			$this->motion->x = 0;
		}

		if($movY != $dy){
			$this->motion->y = 0;
		}

		if($movZ != $dz){
			$this->motion->z = 0;
		}

		//TODO: vehicle collision events (first we need to spawn them!)

		Timings::$entityMoveTimer->stopTiming();
	}

	protected function checkGroundState(float $movX, float $movY, float $movZ, float $dx, float $dy, float $dz) : void{
		$this->isCollidedVertically = $movY != $dy;
		$this->isCollidedHorizontally = ($movX != $dx or $movZ != $dz);
		$this->isCollided = ($this->isCollidedHorizontally or $this->isCollidedVertically);
		$this->onGround = ($movY != $dy and $movY < 0);
	}

	/**
	 * @return Block[]
	 */
	public function getBlocksAround() : array{
		if($this->blocksAround === null){
			$inset = 0.001; //Offset against floating-point errors

			$minX = (int) floor($this->boundingBox->minX + $inset);
			$minY = (int) floor($this->boundingBox->minY + $inset);
			$minZ = (int) floor($this->boundingBox->minZ + $inset);
			$maxX = (int) floor($this->boundingBox->maxX - $inset);
			$maxY = (int) floor($this->boundingBox->maxY - $inset);
			$maxZ = (int) floor($this->boundingBox->maxZ - $inset);

			$this->blocksAround = [];

			for($z = $minZ; $z <= $maxZ; ++$z){
				for($x = $minX; $x <= $maxX; ++$x){
					for($y = $minY; $y <= $maxY; ++$y){
						$block = $this->level->getBlockAt($x, $y, $z);
						if($block->hasEntityCollision()){
							$this->blocksAround[] = $block;
						}
					}
				}
			}
		}

		return $this->blocksAround;
	}

	/**
	 * Returns whether this entity can be moved by currents in liquids.
	 *
	 * @return bool
	 */
	public function canBeMovedByCurrents() : bool{
		return true;
	}

	protected function checkBlockCollision() : void{
		$vector = $this->temporalVector->setComponents(0, 0, 0);

		foreach($this->getBlocksAround() as $block){
			$block->onEntityCollide($this);
			$block->addVelocityToEntity($this, $vector);
		}

		$down = $this->level->getBlock($this->getSide(Facing::DOWN));
		if($down->hasEntityCollision()){
			$down->onEntityCollideUpon($this);
		}

		if($vector->lengthSquared() > 0){
			$vector = $vector->normalize();
			$d = 0.014;
			$this->motion->x += $vector->x * $d;
			$this->motion->y += $vector->y * $d;
			$this->motion->z += $vector->z * $d;
		}
	}

	protected function checkEntityCollision() : void{
		if($this->canBePushed()){
			foreach($this->level->getCollidingEntities($this->getBoundingBox()->expandedCopy(0.2, 0, 0.2), $this) as $e){
				$this->onCollideWithEntity($e);
			}
		}
	}

	public function getPosition() : Position{
		return $this->asPosition();
	}

	public function getLocation() : Location{
		return $this->asLocation();
	}

	public function setPosition(Vector3 $pos) : bool{
		if($this->closed){
			return false;
		}

		if($pos instanceof Position and $pos->level !== null and $pos->level !== $this->level){
			if(!$this->switchLevel($pos->getLevel())){
				return false;
			}
		}

		$this->x = $pos->x;
		$this->y = $pos->y;
		$this->z = $pos->z;

		$this->recalculateBoundingBox();

		$this->blocksAround = null;

		$this->checkChunks();

		return true;
	}

	public function setRotation(float $yaw, float $pitch, ?float $headYaw = null) : void{
		$this->yaw = $yaw;
		$this->headYaw = $headYaw;
		$this->pitch = $pitch;
		$this->scheduleUpdate();
	}

	public function setPositionAndRotation(Vector3 $pos, float $yaw, float $pitch) : bool{
		if($this->setPosition($pos)){
			$this->setRotation($yaw, $pitch);

			return true;
		}

		return false;
	}

	protected function checkChunks() : void{
		$chunkX = $this->getFloorX() >> 4;
		$chunkZ = $this->getFloorZ() >> 4;
		if($this->chunk === null or ($this->chunk->getX() !== $chunkX or $this->chunk->getZ() !== $chunkZ)){
			if($this->chunk !== null){
				$this->chunk->removeEntity($this);
			}
			$this->chunk = $this->level->getChunk($chunkX, $chunkZ, true);

			if(!$this->justCreated){
				$newChunk = $this->level->getViewersForPosition($this);
				foreach($this->hasSpawned as $player){
					if(!isset($newChunk[$player->getLoaderId()])){
						$this->despawnFrom($player);
					}else{
						unset($newChunk[$player->getLoaderId()]);
					}
				}
				foreach($newChunk as $player){
					$this->spawnTo($player);
				}
			}

			if($this->chunk === null){
				return;
			}

			$this->chunk->addEntity($this);
		}
	}

	protected function resetLastMovements() : void{
		$this->lastLocation = $this->asLocation();
		$this->lastMotion = clone $this->motion;
	}

	public function getMotion() : Vector3{
		return clone $this->motion;
	}

	public function setMotion(Vector3 $motion) : bool{
		if(!$this->justCreated){
			$ev = new EntityMotionEvent($this, $motion);
			$ev->call();
			if($ev->isCancelled()){
				return false;
			}
		}

		$this->motion = clone $motion;

		if(!$this->justCreated){
			$this->updateMovement();
		}

		return true;
	}

	public function resetMotion() : void{
		$this->motion->setComponents(0, 0, 0);
	}

	public function isOnGround() : bool{
		return $this->onGround;
	}

	/**
	 * @param Vector3|Position|Location $pos
	 * @param float|null                $yaw
	 * @param float|null                $pitch
	 *
	 * @return bool
	 */
	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool{
		if($pos instanceof Location){
			$yaw = $yaw ?? $pos->yaw;
			$pitch = $pitch ?? $pos->pitch;
		}
		$from = Position::fromObject($this, $this->level);
		$to = Position::fromObject($pos, $pos instanceof Position ? $pos->getLevel() : $this->level);
		$ev = new EntityTeleportEvent($this, $from, $to);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}
		$this->ySize = 0;
		$pos = $ev->getTo();

		$this->setMotion($this->temporalVector->setComponents(0, 0, 0));
		$this->dismountEntity(true);

		if($this->setPositionAndRotation($pos, $yaw ?? $this->yaw, $pitch ?? $this->pitch)){
			$this->resetFallDistance();
			$this->onGround = true;

			$this->updateMovement(true);

			return true;
		}

		return false;
	}

	protected function switchLevel(Level $targetLevel) : bool{
		if($this->closed){
			return false;
		}

		if($this->isValid()){
			$ev = new EntityLevelChangeEvent($this, $this->level, $targetLevel);
			$ev->call();
			if($ev->isCancelled()){
				return false;
			}

			$this->dismountEntity(true);

			$this->level->removeEntity($this);
			if($this->chunk !== null){
				$this->chunk->removeEntity($this);
			}
			$this->despawnFromAll();
		}

		$this->setLevel($targetLevel);
		$this->level->addEntity($this);
		$this->chunk = null;

		return true;
	}

	public function getId() : int{
		return $this->id;
	}

	/**
	 * @return Player[]
	 */
	public function getViewers() : array{
		return $this->hasSpawned;
	}

	/**
	 * Called by spawnTo() to send whatever packets needed to spawn the entity to the client.
	 *
	 * @param Player $player
	 */
	protected function sendSpawnPacket(Player $player) : void{
		$pk = new AddEntityPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->type = static::NETWORK_ID;
		$pk->position = $this->asVector3();
		$pk->motion = $this->getMotion();
		$pk->yaw = $this->yaw;
		$pk->headYaw = $this->yaw; //TODO
		$pk->pitch = $this->pitch;
		$pk->attributes = $this->attributeMap->getAll();
		$pk->metadata = $this->propertyManager->getAll();

		if(!empty($this->seats)){
			$id = $this->getId();
			$pk->links = array_walk($this->passengers, function(Entity $entity, int $seat) use ($id){
				return new EntityLink($id, $entity->getId(), EntityLink::TYPE_RIDER);
			});
		}

		$player->sendDataPacket($pk);
	}

	/**
	 * @param Player $player
	 */
	public function spawnTo(Player $player) : void{
		if(!isset($this->hasSpawned[$player->getLoaderId()])){
			$this->hasSpawned[$player->getLoaderId()] = $player;

			$this->sendSpawnPacket($player);
		}
	}

	public function spawnToAll() : void{
		if($this->chunk === null or $this->closed){
			return;
		}
		foreach($this->level->getViewersForPosition($this) as $player){
			if($player->isOnline()){
				$this->spawnTo($player);
			}
		}
	}

	public function respawnToAll() : void{
		foreach($this->hasSpawned as $key => $player){
			unset($this->hasSpawned[$key]);
			$this->spawnTo($player);
		}
	}

	/**
	 * @param Player $player
	 * @param bool   $send
	 */
	public function despawnFrom(Player $player, bool $send = true) : void{
		if(isset($this->hasSpawned[$player->getLoaderId()])){
			if($send){
				$pk = new RemoveEntityPacket();
				$pk->entityUniqueId = $this->id;
				$player->sendDataPacket($pk);
			}
			unset($this->hasSpawned[$player->getLoaderId()]);
		}
	}

	public function despawnFromAll() : void{
		foreach($this->hasSpawned as $player){
			$this->despawnFrom($player);
		}
	}

	/**
	 * Flags the entity to be removed from the world on the next tick.
	 */
	public function flagForDespawn() : void{
		$this->needsDespawn = true;
		$this->scheduleUpdate();
	}

	public function isFlaggedForDespawn() : bool{
		return $this->needsDespawn;
	}

	/**
	 * Returns whether the entity has been "closed".
	 * @return bool
	 */
	public function isClosed() : bool{
		return $this->closed;
	}

	/**
	 * Closes the entity and frees attached references.
	 *
	 * WARNING: Entities are unusable after this has been executed!
	 */
	public function close() : void{
		if(!$this->closed){
			(new EntityDespawnEvent($this))->call();
			$this->closed = true;

			$this->despawnFromAll();
			$this->hasSpawned = [];

			if($this->chunk !== null){
				$this->chunk->removeEntity($this);
				$this->chunk = null;
			}

			if($this->isValid()){
				$this->level->removeEntity($this);
				$this->setLevel(null);
			}

			$this->lastDamageCause = null;
		}
	}

	/**
	 * @param int  $propertyId
	 * @param int  $flagId
	 * @param bool $value
	 * @param int  $propertyType
	 */
	public function setDataFlag(int $propertyId, int $flagId, bool $value = true, int $propertyType = self::DATA_TYPE_LONG) : void{
		if($this->getDataFlag($propertyId, $flagId) !== $value){
			$flags = (int) $this->propertyManager->getPropertyValue($propertyId, $propertyType);
			$flags ^= 1 << $flagId;
			$this->propertyManager->setPropertyValue($propertyId, $propertyType, $flags);
		}
	}

	/**
	 * @param int $propertyId
	 * @param int $flagId
	 *
	 * @return bool
	 */
	public function getDataFlag(int $propertyId, int $flagId) : bool{
		return (((int) $this->propertyManager->getPropertyValue($propertyId, -1)) & (1 << $flagId)) > 0;
	}

	/**
	 * Wrapper around {@link Entity#getDataFlag} for generic data flag reading.
	 *
	 * @param int $flagId
	 *
	 * @return bool
	 */
	public function getGenericFlag(int $flagId) : bool{
		return $this->getDataFlag($flagId >= 64 ? self::DATA_FLAGS2 : self::DATA_FLAGS, $flagId % 64);
	}

	/**
	 * Wrapper around {@link Entity#setDataFlag} for generic data flag setting.
	 *
	 * @param int  $flagId
	 * @param bool $value
	 */
	public function setGenericFlag(int $flagId, bool $value = true) : void{
		$this->setDataFlag($flagId >= 64 ? self::DATA_FLAGS2 : self::DATA_FLAGS, $flagId % 64, $value, self::DATA_TYPE_LONG);
	}

	/**
	 * @param Player[]|Player $player
	 * @param array           $data Properly formatted entity data, defaults to everything
	 */
	public function sendData($player, ?array $data = null) : void{
		if(!is_array($player)){
			$player = [$player];
		}

		$pk = new SetEntityDataPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->metadata = $data ?? $this->propertyManager->getAll();

		foreach($player as $p){
			if($p === $this){
				continue;
			}
			$p->sendDataPacket(clone $pk);
		}

		if($this instanceof Player){
			$this->sendDataPacket($pk);
		}
	}

	public function broadcastEntityEvent(int $eventId, ?int $eventData = null, ?array $players = null) : void{
		$pk = new EntityEventPacket();
		$pk->entityRuntimeId = $this->id;
		$pk->event = $eventId;
		$pk->data = $eventData ?? 0;

		$this->server->broadcastPacket($players ?? $this->getViewers(), $pk);
	}

	public function broadcastAnimation(?array $players, int $animationId) : void{
		$pk = new AnimatePacket();
		$pk->entityRuntimeId = $this->id;
		$pk->action = $animationId;
		$this->server->broadcastPacket($players ?? $this->getViewers(), $pk);
	}

	/**
	 * Called when interacted or tapped by a Player
	 *
	 * @param Player  $player
	 * @param Item    $item
	 * @param Vector3 $clickPos
	 *
	 * @return bool
	 */
	public function onFirstInteract(Player $player, Item $item, Vector3 $clickPos) : bool{
		return false;
	}

	/**
	 * Called when riding by a player
	 *
	 * @param Player $player
	 * @param float  $motX
	 * @param float  $motY
	 * @param bool   $jumping
	 * @param bool   $sneaking
	 */
	public function onRidingUpdate(Player $player, float $motX, float $motY, bool $jumping = false, bool $sneaking = false) : void{

	}

	/**
	 * @return Item[]
	 */
	public function getDrops() : array{
		return [];
	}

	public function __destruct(){
		$this->close();
	}

	public function setMetadata(string $metadataKey, MetadataValue $newMetadataValue){
		$this->server->getEntityMetadata()->setMetadata($this, $metadataKey, $newMetadataValue);
	}

	public function getMetadata(string $metadataKey){
		return $this->server->getEntityMetadata()->getMetadata($this, $metadataKey);
	}

	public function hasMetadata(string $metadataKey) : bool{
		return $this->server->getEntityMetadata()->hasMetadata($this, $metadataKey);
	}

	public function removeMetadata(string $metadataKey, Plugin $owningPlugin){
		$this->server->getEntityMetadata()->removeMetadata($this, $metadataKey, $owningPlugin);
	}

	public function __toString(){
		return (new \ReflectionClass($this))->getShortName() . "(" . $this->getId() . ")";
	}

}