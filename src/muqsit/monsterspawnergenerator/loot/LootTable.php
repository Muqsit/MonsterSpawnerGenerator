<?php

declare(strict_types=1);

namespace muqsit\monsterspawnergenerator\loot;

use AssertionError;
use Generator;
use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\utils\Random;
use function array_map;
use function array_sum;
use function get_debug_type;
use function is_array;
use function is_int;

final class LootTable{

	/**
	 * @param array<string, mixed> $data
	 * @return self
	 */
	public static function parse(array $data) : self{
		$data["rolls"] ?? throw new InvalidArgumentException("Entry must have a \"rolls\" property");
		$data["entries"] ?? throw new InvalidArgumentException("Entry must have an \"entries\" property");

		$rolls = $data["rolls"];
		is_int($rolls) || throw new InvalidArgumentException("Rolls must be an integer, got " . get_debug_type($rolls));
		$rolls > 0 || throw new InvalidArgumentException("Rolls must be positive, got {$rolls}");

		is_array($data["entries"]) || throw new InvalidArgumentException("Entries must be an array, got " . get_debug_type($data["entries"]));
		$entries = [];
		foreach($data["entries"] as $entry){
			$entries[] = ItemLootTableEntry::parse($entry);
		}
		return new self($rolls, $entries);
	}

	/** @var positive-int */
	private int $weight_total;

	/**
	 * @param positive-int $rolls
	 * @param ItemLootTableEntry[] $entries
	 */
	public function __construct(
		public int $rolls,
		public array $entries
	){
		$this->weight_total = array_sum(array_map(fn(ItemLootTableEntry $entry) : int => $entry->weight, $this->entries));
	}

	public function generateOne(Random $random) : Item{
		$rnd = $random->nextBoundedInt($this->weight_total);
		foreach($this->entries as $entry){
			if($rnd < $entry->weight){
				return $entry->generate($random);
			}
			$rnd -= $entry->weight;
		}
		throw new AssertionError("Unreachable statement");
	}

	/**
	 * @param Random $random
	 * @return Generator<Item>
	 */
	public function generate(Random $random) : Generator{
		for($i = 0; $i < $this->rolls; $i++){
			yield $this->generateOne($random);
		}
	}
}