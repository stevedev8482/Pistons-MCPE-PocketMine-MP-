<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\Player;
use pocketmine\tile\Tile;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\level\sound\MinecraftSound;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\nbt\tag\StringTag;

class Piston extends Solid{
	
	protected $id = self::PISTON;
	
	public $meta = 0;

    private $sticky = false;
	
	public function __construct($meta = 0){
		$this->meta = $meta;
	}

    public function getSticky() : Bool {
       return false;
    }

    public function setSticky(Bool $value) : Void {
        $this->sticky = $value;
    }

    public function isSticky() : Bool {
        return false;
    }

	public function getHardness(){
		return 1;
	}
	
	public function getToolType(){
		return Tool::TYPE_AXE;
	}
	
	public function getName(){
		return "Piston";
	}

    /**
     * @return int
     */
	public function getFace() {
		return $this->meta & 0x07; // first 3 bits is face
	}

    /**
     * @return null
     */
	public function getExtendSide() {
		$face = $this->getFace();
		switch ($face) {
			case 0:
				return self::SIDE_DOWN;
			case 1:
				return self::SIDE_UP;
			case 2:
				return self::SIDE_SOUTH;
			case 3:
				return self::SIDE_NORTH;
			case 4:
				return self::SIDE_EAST;
			case 5:
				return self::SIDE_WEST;
		}
		return null;
	}

    /**
     * @param Item $item
     * @param \pocketmine\block\Block $block
     * @param \pocketmine\block\Block $target
     * @param $face
     * @param $fx
     * @param $fy
     * @param $fz
     * @param Player|null $player
     * @return true
     */
	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null){
		$this->meta = $face;

		if ($player->pitch > 45) {
			$this->meta = 1;
		} else if ($player->pitch < -45) {
			$this->meta = 0;
		} else {
			if ($player->yaw <= 45 || $player->yaw > 315) {
				$this->meta = 3;
			} else if ($player->yaw > 45 && $player->yaw <= 135) {
				$this->meta = 4;
			} else if ($player->yaw > 135 && $player->yaw <= 225) {
				$this->meta = 2;
			} else {
				$this->meta = 5;
			}
		}

		$isWasPlaced = $this->getLevel()->setBlock($this, $this, true, true);
        $sticky = $this->getSticky();

		if ($isWasPlaced) {
			$nbt = new CompoundTag("", [
				new StringTag("id", Tile::PISTON_ARM),
				new IntTag("x", $this->x),
				new IntTag("y", $this->y),
				new IntTag("z", $this->z),
				new FloatTag("Progress", 0.0),
				new ByteTag("State", 0),
				new ByteTag("HaveCharge", 0)
			]);
			$chunk = $this->getLevel();
			$tile = Tile::createTile(Tile::PISTON_ARM, $chunk, $nbt);
		}

		$this->getLevel()->setBlock($block, $this, true, true);
		return true;
	}

    /**
     * @return false|void
     */
    public function activate()
    {

        $nbt = new CompoundTag("", [
            new StringTag("id", Tile::PISTON_ARM),
            new IntTag("x", $this->x),
            new IntTag("y", $this->y),
            new IntTag("z", $this->z),
            new FloatTag("Progress", 1),
            new ByteTag("State", 2),
            new ByteTag("HaveCharge", 0)
        ]);
        $chunk = $this->getLevel();
        Tile::createTile(Tile::PISTON_ARM, $chunk, $nbt);

        $extendBlock = $this->getSide((int)$this->getExtendSide());
        $deep = 2;

        $nextBlock = $this->getSide((int)$this->getExtendSide(), $deep);

        $pos = new Vector3($this->x, $this->y, $this->z);

        foreach(Server::getInstance()->getOnlinePlayers() as $players)
        {
            if($players->distance($pos) <= 6) {
                $pk = new LevelSoundEventPacket();
                $pk->sound = LevelSoundEventPacket::SOUND_PISTON_IN;
                $pk->x = $players->x;
                $pk->y = $players->y;
                $pk->z = $players->z;
                $players->dataPacket($pk);
            }
        }

        if($nextBlock->getId() === 0){
            return false;
        }

        $next = $nextBlock->getSide((int)$this->getExtendSide(), $deep);

        $chunk->setBlock($extendBlock, Block::get(BlockIds::AIR));

        $chunk->setBlock($extendBlock, $nextBlock, true, true);

    }

    /**
     * @return Void
     */
    public function deactive() : Void {
        $nbt = new CompoundTag("", [
            new StringTag("id", Tile::PISTON_ARM),
            new IntTag("x", $this->x),
            new IntTag("y", $this->y),
            new IntTag("z", $this->z),
            new FloatTag("Progress", 0.0),
            new ByteTag("State", 1),
            new ByteTag("HaveCharge", 0)
        ]);
        $chunk = $this->getLevel();
        Tile::createTile(Tile::PISTON_ARM, $chunk, $nbt);

        $pos = new Vector3($this->x, $this->y, $this->z);

        foreach(Server::getInstance()->getOnlinePlayers() as $players)
        {
            if($players->distance($pos) <= 6) {
                $pk = new LevelSoundEventPacket();
                $pk->sound = LevelSoundEventPacket::SOUND_PISTON_OUT;
                $pk->x = $players->x;
                $pk->y = $players->y;
                $pk->z = $players->z;
                $players->dataPacket($pk);
            }
        }
    }
}