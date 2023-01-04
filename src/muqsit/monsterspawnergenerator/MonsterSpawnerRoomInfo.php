<?php

declare(strict_types=1);

namespace muqsit\monsterspawnergenerator;

use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;

final class MonsterSpawnerRoomInfo{

	public const HEIGHT = 6;

	public static function create(int $chunkX, int $chunkZ, Random $random, int $numChests = 2, int $numChestAttempts = 3) : ?self{
		// generate unique values for given seed, chunkX, chunkZ
		$spawnerPos = new Vector3(
			($chunkX << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16),
			5 + $random->nextBoundedInt(32),
			($chunkZ << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16)
		);

		// width of the room is 3 to 4 blocks from spawner position
		$widthX = $random->nextRange(3, 4);
		$widthZ = $random->nextRange(3, 4);

		$possibleChestPos = [];
		$chestPosRandom = new Random((($spawnerPos->z & SubChunk::COORD_MASK) << SubChunk::COORD_BIT_SIZE) | ($spawnerPos->x & SubChunk::COORD_MASK));
		for($i = 0; $i < $numChests; ++$i){
			for($j = 0; $j < $numChestAttempts; ++$j){
				$possibleChestPos[$i][$j] = $spawnerPos->add($chestPosRandom->nextRange(-$widthX, $widthX), 0, $chestPosRandom->nextRange(-$widthZ, $widthZ));
			}
		}

		return new self($widthX, $widthZ, $spawnerPos, $possibleChestPos);
	}

	/**
	 * @param positive-int $widthX
	 * @param positive-int $widthZ
	 * @param Vector3 $spawnerPos
	 * @param list<list<Vector3>> $possibleChestPos
	 */
	public function __construct(
		public int $widthX,
		public int $widthZ,
		public Vector3 $spawnerPos,
		public array $possibleChestPos
	){}
}