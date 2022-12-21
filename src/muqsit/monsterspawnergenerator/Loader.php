<?php

declare(strict_types=1);

namespace muqsit\monsterspawnergenerator;

use pocketmine\block\tile\Container;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\Listener;
use pocketmine\event\world\ChunkPopulateEvent;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\plugin\PluginBase;
use pocketmine\world\generator\GeneratorManager;
use function assert;
use function mt_rand;

final class Loader extends PluginBase implements Listener{

	private GeneratorManager $manager;

	protected function onLoad() : void{
		$this->manager = GeneratorManager::getInstance();
		$this->manager->addGenerator(MonsterSpawnerGenerator::class, "normal_msp", fn() => null);
	}

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
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

		$position = MonsterSpawnerGenerator::getSpawnerPosition($world->getSeed(), $event->getChunkX(), $event->getChunkZ());
		if($position === null){
			return;
		}

		$block = VanillaBlocks::MONSTER_SPAWNER();
		$world->setBlockAt($position->x, $position->y, $position->z, $block);

		// workaround to set type of monster spawner
		$world->removeTile($world->getTileAt($position->x, $position->y, $position->z));
		$world->addTile(TileFactory::getInstance()->createFromData($world, CompoundTag::create()
			->setString(Tile::TAG_ID, TileFactory::getInstance()->getSaveId($block->getIdInfo()->getTileClass()))
			->setInt(Tile::TAG_X, $position->x)
			->setInt(Tile::TAG_Y, $position->y)
			->setInt(Tile::TAG_Z, $position->z)
			->setString("EntityIdentifier", EntityIds::ZOMBIE)
		));

		$this->getLogger()->notice("Generated monster spawner room in {$world->getFolderName()} at {$position}");

		// add items to chest
		$chestPos = MonsterSpawnerGenerator::getSpawnerChestPosition($position->x, $position->y, $position->z, $side);
		$world->setBlockAt($chestPos->x, $chestPos->y, $chestPos->z, VanillaBlocks::CHEST()->setFacing($side));
		$container = $world->getTileAt($chestPos->x, $chestPos->y, $chestPos->z);
		if(!($container instanceof Container)){
			return;
		}

		$items = [];
		$items[] = VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(1, 3));
		$items[] = VanillaItems::BONE()->setCount(mt_rand(1, 2));
		if(mt_rand(1, 4) === 1){
			$items[] = VanillaItems::IRON_INGOT()->setCount(mt_rand(1, 2));
		}
		if(mt_rand(1, 8) === 1){
			$items[] = VanillaItems::DIAMOND()->setCount(mt_rand(1, 2));
		}
		$container->getInventory()->addItem(...$items);
	}
}