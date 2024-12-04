<?php

namespace pocketmine\tile;

use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\block\Piston;
use pocketmine\block\StickyPiston;

class PistonArm extends Spawnable {

	public function getSpawnCompound(){
     $sticky = 0;
     $block = $this->level->getBlock($this);
     if($block instanceof Piston) {
        $sticky = 0;
     }

     if($block instanceof StickyPiston) {
         $sticky = 1;
     }

		return new CompoundTag("", [
			new StringTag("id", Tile::PISTON_ARM),
			new IntTag("x", (int)$this->x),
			new IntTag("y", (int)$this->y),
			new IntTag("z", (int)$this->z),
			new FloatTag("Progress", $this->namedtag['Progress']),
			new ByteTag("State", $this->namedtag['State']),
            new ByteTag("Sticky", $sticky),
		]);
	}
}