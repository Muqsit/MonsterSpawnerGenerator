# MonsterSpawnerGenerator
This plugin demonstrates how to write a generator that generates a monster spawner room with a monster spawner and a chest in PocketMine-MP. It does this by using a function that takes in a `(seed, chunkX, chunkZ)` as input and outputs a `Vector3|null` spawner position.
This method avoids the need for `Generator` and `ChunkPopulateEvent` to exchange arbitrary data.

## How to use this plugin?
1. Install the plugin on your server
2. Make the following changes in your `pocketmine.yml`:
   ```diff
   worlds:
     world:
       generator: normal
   + msp:
   +   generator: normal_msp
   ```
3. Teleport yourself to the world named `msp`
4. Explore the world. The console will generate blue messages with monster spawner room coordinates:
   ```
   [16:07:08.079] [Server thread/NOTICE]: [MonsterSpawnerGenerator] Generated monster spawner room in msp at Vector3(x=68,y=23,z=959)
   ```
