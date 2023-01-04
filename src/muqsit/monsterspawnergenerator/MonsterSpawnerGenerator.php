<?php

declare(strict_types=1);

namespace muqsit\monsterspawnergenerator;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\utils\Utils;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\normal\Normal;

final class MonsterSpawnerGenerator extends Generator{

	public const ROOM_SIZE = 3;

	public static function getRandomGenerator(int $seed, int $chunkX, int $chunkZ) : Random{
		return new Random(Utils::javaStringHash("{$chunkX}{$chunkZ}{$seed}"));
	}

	public static function getSpawnerPosition(int $seed, int $chunkX, int $chunkZ) : ?Vector3{
		// generate unique values for given seed, chunkX, chunkZ
		$random = self::getRandomGenerator($seed, $chunkX, $chunkZ);
		return $random->nextFloat() > 0.01 ? null : new Vector3( // 1% chance of generation
			($chunkX << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16),
			5 + $random->nextBoundedInt(32),
			($chunkZ << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16)
		);
	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param-out Facing::EAST|Facing::WEST|Facing::SOUTH|Facing::NORTH $side
	 * @return Vector3
	 */
	public static function getSpawnerChestPosition(int $x, int $y, int $z, int &$side = null) : Vector3{
		// generate unique values for given x, z
		$random = new Random((($z & SubChunk::COORD_MASK) << SubChunk::COORD_BIT_SIZE) | ($x & SubChunk::COORD_MASK));

		if($random->nextBoolean()){
			$dx = $random->nextBoolean() ? self::ROOM_SIZE : -self::ROOM_SIZE;
			$side = $dx === self::ROOM_SIZE ? Facing::WEST : Facing::EAST;
			return new Vector3($x + $dx, $y, $z);
		}

		$dz = $random->nextBoolean() ? self::ROOM_SIZE : -self::ROOM_SIZE;
		$side = $dz === self::ROOM_SIZE ? Facing::NORTH : Facing::SOUTH;
		return new Vector3($x, $y, $z + $dz);
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
		$position = self::getSpawnerPosition($this->seed, $chunkX, $chunkZ);
		if($position === null){
			return;
		}

		$size_p1 = self::ROOM_SIZE + 1;
		$x = $position->x;
		$y = $position->y;
		$z = $position->z;

		$air = VanillaBlocks::AIR();
		$moss = VanillaBlocks::MOSSY_COBBLESTONE();
		for($dx = -$size_p1; $dx <= $size_p1; ++$dx){
			for($dz = -$size_p1; $dz <= $size_p1; ++$dz){
				for($dy = -1; $dy <= $size_p1; ++$dy){
					if(
						$dx === -$size_p1 ||
						$dx === $size_p1 ||
						$dz === -$size_p1 ||
						$dz === $size_p1 ||
						$dy === -1 ||
						$dy === $size_p1
					){
						$block = $moss;
					}else{
						$block = $air;
					}
					$world->setBlockAt($x + $dx, $y + $dy, $z + $dz, $block);
				}
			}
		}

		$chestPos = self::getSpawnerChestPosition($x, $y, $z, $side);
		$world->setBlockAt($chestPos->x, $chestPos->y + 2, $chestPos->z, VanillaBlocks::TORCH()->setFacing($side));
	}
}