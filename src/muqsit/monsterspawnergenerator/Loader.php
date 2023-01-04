<?php

declare(strict_types=1);

namespace muqsit\monsterspawnergenerator;

use InvalidArgumentException;
use muqsit\monsterspawnergenerator\loot\LootTable;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Opaque;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\Listener;
use pocketmine\event\world\ChunkPopulateEvent;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\plugin\PluginBase;
use pocketmine\world\generator\GeneratorManager;
use RuntimeException;
use Webmozart\PathUtil\Path;
use function array_push;
use function assert;
use function count;
use function file_get_contents;
use function get_debug_type;
use function is_array;
use function json_decode;

final class Loader extends PluginBase implements Listener{

	private GeneratorManager $manager;

	/** @var list<LootTable> */
	private array $loot_table_pools = [];

	protected function onLoad() : void{
		$this->manager = GeneratorManager::getInstance();
		$this->manager->addGenerator(MonsterSpawnerGenerator::class, "normal_msp", fn() => null);
	}

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->saveResource("loot_table_monster_room.json");
		$_loot_table_config = file_get_contents(Path::join($this->getDataFolder(), "loot_table_monster_room.json"));
		$_loot_table_config !== false || throw new RuntimeException("Failed to retrieve contents of loot table config");

		$loot_table_config = json_decode($_loot_table_config, true, 512, JSON_THROW_ON_ERROR);
		$loot_table_config["pools"] ?? throw new InvalidArgumentException("Loot table config must have a \"pools\" property");
		is_array($loot_table_config["pools"]) || throw new InvalidArgumentException("Loot table config \"pools\" must be an array, got " . get_debug_type($loot_table_config["pools"]));
		foreach($loot_table_config["pools"] as $entry){
			$this->loot_table_pools[] = LootTable::parse($entry);
		}
	}

	protected function onDisable() : void{
	}

	/**
	 * @param ChunkPopulateEvent $event
	 * @private LOWEST
	 */
	public function onChunkPopulate(ChunkPopulateEvent $event) : void{
		$world = $event->getWorld();

		$generator = $this->manager->getGenerator($world->getProvider()->getWorldData()->getGenerator());
		assert($generator !== null);

		if($generator->getGeneratorClass() !== MonsterSpawnerGenerator::class){
			return;
		}

		$seed = $world->getSeed();
		$chunkX = $event->getChunkX();
		$chunkZ = $event->getChunkZ();
		$info = MonsterSpawnerGenerator::getSpawnerInfo($seed, $chunkX, $chunkZ);
		if($info === null){
			return;
		}

		$block = VanillaBlocks::MONSTER_SPAWNER();
		$world->setBlockAt($info->spawnerPos->x, $info->spawnerPos->y, $info->spawnerPos->z, $block);

		// workaround to set type of monster spawner
		$world->removeTile($world->getTileAt($info->spawnerPos->x, $info->spawnerPos->y, $info->spawnerPos->z));
		$world->addTile(TileFactory::getInstance()->createFromData($world, CompoundTag::create()
			->setString(Tile::TAG_ID, TileFactory::getInstance()->getSaveId($block->getIdInfo()->getTileClass()))
			->setInt(Tile::TAG_X, $info->spawnerPos->x)
			->setInt(Tile::TAG_Y, $info->spawnerPos->y)
			->setInt(Tile::TAG_Z, $info->spawnerPos->z)
			->setString("EntityIdentifier", EntityIds::ZOMBIE)
		));

		$this->getLogger()->notice("Generated monster spawner room in {$world->getFolderName()} at {$info->spawnerPos}");

		// generate chests
		$random = MonsterSpawnerGenerator::getRandomGenerator($seed, $chunkX, $chunkZ);
		foreach($info->possibleChestPos as $positions){
			foreach($positions as $position){
				$chosenBlock = $world->getBlockAt($position->x, $position->y, $position->z);
				if($chosenBlock->getId() !== BlockLegacyIds::AIR){
					continue;
				}

				$airs = [];
				$opaques = [];
				foreach(Facing::HORIZONTAL as $side){
					$sidePos = $position->getSide($side);
					$sideBlock = $world->getBlockAt($sidePos->x, $sidePos->y, $sidePos->z);
					if($sideBlock->getId() === BlockLegacyIds::AIR){
						$airs[] = $side;
					}elseif($sideBlock instanceof Opaque){
						$opaques[] = $side;
					}
				}

				if(count($airs) !== 3 || count($opaques) !== 1){
					continue;
				}

				$face = Facing::opposite($opaques[0]);
				$world->setBlockAt($position->x, $position->y + 2, $position->z, VanillaBlocks::TORCH()->setFacing($face));
				$world->setBlockAt($position->x, $position->y, $position->z, VanillaBlocks::CHEST()->setFacing($face));

				$container = $world->getTileAt($position->x, $position->y, $position->z);
				if(!($container instanceof Container)){
					continue;
				}

				// add items to chest
				$items = [];
				$loot_table_pools_c = count($this->loot_table_pools);
				for($i = 0; $i < 3; ++$i){
					array_push($items, ...$this->loot_table_pools[$i % $loot_table_pools_c]->generate($random));
				}
				$container->getInventory()->addItem(...$items);
				break;
			}
		}
	}
}