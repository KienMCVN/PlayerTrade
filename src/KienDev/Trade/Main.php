<?php
/*
██╗  ██╗██╗███████╗███╗   ██╗██████╗ ███████╗██╗   ██╗
██║ ██╔╝██║██╔════╝████╗  ██║██╔══██╗██╔════╝██║   ██║
█████╔╝ ██║█████╗  ██╔██╗ ██║██║  ██║█████╗  ██║   ██║
██╔═██╗ ██║██╔══╝  ██║╚██╗██║██║  ██║██╔══╝  ╚██╗ ██╔╝
██║  ██╗██║███████╗██║ ╚████║██████╔╝███████╗ ╚████╔╝ 
╚═╝  ╚═╝╚═╝╚══════╝╚═╝  ╚═══╝╚═════╝ ╚══════╝  ╚═══╝  
		Copyright © 2024 - 2025 KienDev 
*/

namespace KienDev\Trade;

use pocketmine\Server;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\event\player\{PlayerJoinEvent, PlayerQuitEvent};
use pocketmine\command\{Command, CommandSender, CommandExecutor};
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\math\Vector3;
use KienDev\Trade\Command\TradeCMD;

class Main extends PluginBase implements Listener{

	public const FAKE_ENCHANTMENT = -1;

	/**public static $instance;
	
	public static function getInstance(): Main{
		return self::$instance;
	}**/

	public function onEnable(): void{
		EnchantmentIdMap::getInstance()->register(self::FAKE_ENCHANTMENT, new Enchantment("", -1, 1, ItemFlags::ALL, ItemFlags::NONE));
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->saveDefaultConfig();
		$this->getServer()->getCommandMap()->register("/trade", new TradeCMD($this));
		/**self::$instance = $this;**/
		$this->getLogger()->notice("Plugin PlayerTrade By KienDev Is On Enable");
	}

	public function sendSound(Player $player, string $soundName) {
        $packet = new PlaySoundPacket();
        $packet->soundName = $soundName;
        $packet->x = $player->getPosition()->getX();
        $packet->y = $player->getPosition()->getY();
        $packet->z = $player->getPosition()->getZ();
        $packet->volume = 1;
        $packet->pitch = 1;
        $player->getNetworkSession()->sendDataPacket($packet);
    }

	public function giveItems(Player $player, array $items){
		if(!empty($items)){
			foreach($items as $item){
				if(!$player->getInventory()->canAddItem($item)){
					$pos=new Vector3($player->getPosition()->getX(), $player->getPosition()->getY(), $player->getPosition()->getZ());
					$player->getPosition()->getWorld()->dropItem($pos,$item);
					continue;
				}else{
					$player->getInventory()->addItem($item);
				}
			}
		}
		return;
	}
}
