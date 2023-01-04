<?php

declare(strict_types=1);

namespace muqsit\monsterspawnergenerator;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\utils\Utils;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\normal\Normal;

final class MonsterSpawnerGenerator extends Generator{

	public static function getRandomGenerator(int $seed, int $chunkX, int $chunkZ) : Random{
		return new Random(Utils::javaStringHash("{$chunkX}{$chunkZ}{$seed}"));
	}

	public static function getSpawnerInfo(int $seed, int $chunkX, int $chunkZ) : ?MonsterSpawnerRoomInfo{
		// generate unique values for given seed, chunkX, chunkZ
		$random = self::getRandomGenerator($seed, $chunkX, $chunkZ);
		return $random->nextFloat() > 0.01 ? null : MonsterSpawnerRoomInfo::create($chunkX, $chunkZ, $random);
	}

	private Generator $inner;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
		$this->inner = new Normal($seed, $preset);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->inner->generateChunk($world, $chunkX, $chunkZ);
		// When the function generateChunk is called, the chunk that it generates may be empty,
		// and the surrounding chunks may also be empty. It is not possible to generate a monster
		// spawner room because the room could potentially span multiple chunks.
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->inner->populateChunk($world, $chunkX, $chunkZ);

		// When the function populateChunk is called, the surrounding chunks have already been
		// generated. So it is now possible to build a monster spawner room that spans across
		// multiple chunks.
		$info = self::getSpawnerInfo($this->seed, $chunkX, $chunkZ);
		if($info === null){
			return;
		}

		$x = $info->spawnerPos->x;
		$y = $info->spawnerPos->y;
		$z = $info->spawnerPos->z;

		$minX = -$info->widthX - 1;
		$maxX = $info->widthX + 1;
		$minZ = -$info->widthZ - 1;
		$maxZ = $info->widthZ + 1;

		$air = VanillaBlocks::AIR();
		$cobble = VanillaBlocks::COBBLESTONE();
		$moss = VanillaBlocks::MOSSY_COBBLESTONE();
		for($dx = $minX; $dx <= $maxX; ++$dx){
			for($dz = $minZ; $dz <= $maxZ; ++$dz){
				for($dy = -1; $dy <= MonsterSpawnerRoomInfo::HEIGHT; ++$dy){
					if(
						$dx === $minX ||
						$dx === $maxX ||
						$dz === $minZ ||
						$dz === $maxZ ||
						$dy === -1 ||
						$dy === MonsterSpawnerRoomInfo::HEIGHT
					){
						$block = $dy === -1 && $this->random->nextBoundedInt(4) === 0 ? $moss : $cobble;
					}else{
						$block = $air;
					}
					$world->setBlockAt($x + $dx, $y + $dy, $z + $dz, $block);
				}
			}
		}
	}
}