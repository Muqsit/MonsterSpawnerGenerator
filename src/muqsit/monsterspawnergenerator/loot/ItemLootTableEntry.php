<?php

declare(strict_types=1);

namespace muqsit\monsterspawnergenerator\loot;

use Closure;
use InvalidArgumentException;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\Random;
use function array_rand;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;

final class ItemLootTableEntry{

	/**
	 * @param array<string, mixed> $data
	 * @return self
	 */
	public static function parse(array $data) : self{
		$data["name"] ?? throw new InvalidArgumentException("Entry must have a \"name\" property");
		$data["weight"] ?? throw new InvalidArgumentException("Entry must have a \"weight\" property");
		$data["functions"] ??= [];

		$item = StringToItemParser::getInstance()->parse($data["name"]) ?? match($data["name"]){
			"minecraft:name_tag" => ItemFactory::getInstance()->get(ItemIds::NAME_TAG),
			"minecraft:horsearmoriron" => ItemFactory::getInstance()->get(ItemIds::HORSE_ARMOR_IRON),
			"minecraft:horsearmorgold" => ItemFactory::getInstance()->get(ItemIds::HORSE_ARMOR_GOLD),
			default => null
		} ?? throw new InvalidArgumentException("Invalid item \"{$data["name"]}\"");

		$weight = $data["weight"];
		is_int($weight) || throw new InvalidArgumentException("Weight must be an integer, got " . get_debug_type($weight));
		$weight > 0 || throw new InvalidArgumentException("Weight must be positive, got {$weight}");

		is_array($data["functions"]) || throw new InvalidArgumentException("Functions must be an array, got " . get_debug_type($data["functions"]));

		$functions = [];
		foreach($data["functions"] as $entry){
			$entry["function"] ?? throw new InvalidArgumentException("Function entry must have a \"function\" property");

			$function = $entry["function"];
			is_string($function) || throw new InvalidArgumentException("Function entry's \"function\" must be a string, got " . get_debug_type($entry["function"]));

			$args = $entry;
			unset($args["function"]);

			if($function === "set_count"){
				$min = $args["count"]["min"] ?? throw new InvalidArgumentException("Function set_count must have a count.min property");
				is_int($min) || throw new InvalidArgumentException("Value for function argument set_count(count.min) must be an integer");

				$max = $args["count"]["max"] ?? throw new InvalidArgumentException("Function set_count must have a count.max property");
				is_int($max) || throw new InvalidArgumentException("Value for function argument set_count(count.max) must be an integer");

				$max >= $min || throw new InvalidArgumentException("Value for function argument set_count(count.max) must be >= set_count(count.min)");

				$functions[] = static fn(Item $item, Random $random) : Item => $item->setCount($random->nextRange($min, $max));
			}elseif($function === "enchant_randomly"){
				$enchantments = VanillaEnchantments::getAll();
				$functions[] = static fn(Item $item, Random $random) : Item => $item->addEnchantment(new EnchantmentInstance(
					$enchantments[$index = array_rand($enchantments)],
					1 + $random->nextBoundedInt($enchantments[$index]->getMaxLevel())
				));
			}else{
				throw new InvalidArgumentException("Invalid function \"{$function}\"");
			}
		}

		return new self($item, $functions, $weight);
	}

	/**
	 * @param Item $item
	 * @param list<Closure(Item, Random) : Item> $functions
	 * @param positive-int $weight
	 */
	public function __construct(
		public Item $item,
		public array $functions,
		public int $weight
	){}

	public function generate(Random $random) : Item{
		$item = clone $this->item;
		foreach($this->functions as $function){
			$item = $function($item, $random);
		}
		return $item;
	}
}