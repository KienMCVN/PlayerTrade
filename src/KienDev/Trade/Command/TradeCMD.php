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

namespace KienDev\Trade\Command;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginOwned;
use pocketmine\command\{Command, CommandSender};
use pocketmine\item\enchantment\{Enchantment, StringToEnchantmentParser};
use pocketmine\scheduler\Task;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\{Item, ItemBlock, StringToItemParser, LegacyStringToItemParser, LegacyStringToItemParserException};
use pocketmine\inventory\Inventory;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\{InvMenuTransaction,InvMenuTransactionResult};
use muqsit\invmenu\type\InvMenuTypeIds;
use Closure;
use KienDev\Trade\{Main,TradeManager};

class TradeCMD extends Command implements PluginOwned{

	private Main $plugin;
	private $requests=[];

	public function getOwningPlugin(): Main{
		return $this->plugin;
	}
	
	 public function __construct(Main $plugin){
		$this->plugin=$plugin;
		parent::__construct("trade", "Trade With Other Players", null, []);
		$this->setPermission("playertrade.command");
	}

	public function execute(CommandSender $player, string $label, array $args){
		if(!$player instanceof Player){
			$player->sendMessage("Use Command In Game");
			return;
		}
		if(!isset($args[0])){
			$player->sendMessage("Use: /trade <name> Or /trade <accept | deny>");
			return;
		}
		if($args[0]==="accept"){
	            $this->acceptRequest($player);
	            return;
	        }
	        if ($args[0]==="deny") {
	            $this->denyRequest($player);
	            return;
	        }
		if($args[0]==$player->getName()){
	        	$player->sendMessage("You Can't Send A Request Yourself");
	        	return;
	        }
		if(!$this->plugin->getServer()->getPlayerByPrefix($args[0])){
			$player->sendMessage("Player Isn't Online");
			return;
		}
		$player2=$this->plugin->getServer()->getPlayerByPrefix($args[0]);
		$this->sendRequest($player, $player2);
	}

	private function sendRequest(Player $sender, Player $target): void {
        $requestId = uniqid();
        $this->requests[$requestId] = [
            "sender" => $sender->getName(),
            "target" => $target->getName(),
            "timestamp" => time()
        ];
        $sender->sendMessage("Your Request Was Sent Successfully, Wait For 30s");
        $target->sendMessage($sender->getName() . " Has Sent You A Request. Use /trade accept To Accept Or /trade deny To Deny");
        $scheduler=$this->plugin->getScheduler();
        $scheduler->scheduleDelayedTask(new class($requestId, $this, $this->plugin) extends Task{
            private $requestId;
            private $tradecmd;
	    private $plugin;

            public function __construct(string $requestId, TradeCMD $tradecmd, Main $plugin) {
                $this->requestId = $requestId;
                $this->tradecmd = $tradecmd;
		$this->plugin = $plugin;
            }

            public function onRun(): void {
                if (isset($this->tradecmd->requests[$this->requestId])) {
                    unset($this->tradecmd->requests[$this->requestId]);
                    $target = $this->plugin->getServer()->getPlayerExact($this->plugin->requests[$this->requestId]["target"]);
                    if ($target instanceof Player) {
                        $target->sendMessage("The Request Has Expired");
                    }
                }
            }
        }, 20 * 30);
    }

    private function acceptRequest(Player $player): void {
        foreach ($this->requests as $requestId => $request) {
            if ($request["target"] === $player->getName()) {
                $sender = $this->plugin->getServer()->getPlayerExact($request["sender"]);
                if ($sender instanceof Player) {
                    $player->sendMessage("You Have Accepted The Request From " . $request["sender"] . ".");
                    $sender->sendMessage($player->getName() . " Has Accepted Your Request.");
                    $this->playersTrade($player, $sender);
                    unset($this->requests[$requestId]);
                    return;
                }
            }
        }
        $player->sendMessage("You Don't Have Any Pending Requests.");
    }

    private function denyRequest(Player $player): void {
        foreach ($this->requests as $requestId => $request) {
            if ($request["target"] === $player->getName()) {
                $sender = $this->plugin->getServer()->getPlayerExact($request["sender"]);
                if ($sender instanceof Player) {
                    $player->sendMessage("You Have denied The Request From " . $request["sender"] . ".");
                    $sender->sendMessage($player->getName() . " Has Denied Your Request.");
                    unset($this->requests[$requestId]);
                    return;
                }
            }
        }
        $player->sendMessage("You Don't Have Any Pending Requests.");
    }

    public function playersTrade(Player $player, Player $sender){
    	$trade=new TradeManager($player, $sender, $this->plugin);
    	$this->menuTrade($player, $sender, $trade);
    }

    public function menuTrade(Player $player, Player $sender, TradeManager $trade){
    	$menu=InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$menu->setName("§bTrade");
		$menu->setListener(function (InvMenuTransaction $transaction) use ($player, $sender, $trade) {
			return $this->menuTradeListener($transaction, $player, $sender, $trade);
		});
		$menu->setInventoryCloseListener(function (Player $p, Inventory $inventory) use ($player, $sender, $trade){
			return $this->tradeCloseListener($p, $inventory, $player, $sender, $trade);
		});
		$inv=$menu->getInventory();
		$itemsSlotArray=[4,13,22,31,40,45,46,47,48,49,50,51,52,53];
		foreach($itemsSlotArray as $slot){
			if($slot==45){
				$item=LegacyStringToItemParser::getInstance()->parse("397:3")->setCustomName($player->getName())->setCount(1);
				$item->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(Main::FAKE_ENCHANTMENT)));
			}elseif($slot==53){
				$item=LegacyStringToItemParser::getInstance()->parse("397:3")->setCustomName($sender->getName())->setCount(1);
				$item->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(Main::FAKE_ENCHANTMENT)));
			}elseif($slot==48){
				$item=LegacyStringToItemParser::getInstance()->parse("331")->setCustomName("§cDeny")->setCount(1);
				$item->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(Main::FAKE_ENCHANTMENT)));
			}elseif($slot==50){
				$item=LegacyStringToItemParser::getInstance()->parse("388")->setCustomName("§aAccept")->setCount(1);
				$item->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(Main::FAKE_ENCHANTMENT)));
			}else{
				$item=LegacyStringToItemParser::getInstance()->parse("339")->setCustomName(" ")->setCount(1);
				$item->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(Main::FAKE_ENCHANTMENT)));
			}
			$inv->setItem($slot, $item);
		}
		$menu->send($player);
		$menu->send($sender);
	}

	public function tradeCloseListener(Player $p, Inventory $inventory, Player $player, Player $sender, TradeManager $trade){
		$playerItems=[];
        $senderItems=[];
		for($slott=0;$slott<=44;$slott++){
			$item=$inventory->getItem($slott);
			if($item!==null){
				if(in_array($slott,[0,1,2,3,9,10,11,12,18,19,20,21,27,28,29,30,36,37,38,39])){
					$playerItems[]=$item;
				}
				if(in_array($slott,[5,6,7,8,14,15,16,17,23,24,25,26,32,33,34,35,41,42,43,44])){
					$senderItems[]=$item;
				}
			}
		}
		if($trade->getPlayerTrade()!==null || $trade->getSenderTrade()!==null) return;
		if($p->getName()==$player->getName()){
			if($sender->getCurrentWindow()==null) return;
			$sender->sendMessage($player->getName()." Denied The Trade");
			$p->sendMessage("You Denied The Trade");
			$sender->removeCurrentWindow();
			$trade->returnItem($playerItems,$senderItems);
		}else{
			if($player->getCurrentWindow()==null) return;
			$player->sendMessage($sender->getName()." Denied The Trade");
			$p->sendMessage("You Denied The Trade");
			$player->removeCurrentWindow();
			$trade->returnItem($playerItems,$senderItems);
		}
	}

	public function menuTradeListener(InvMenuTransaction $transaction, Player $player, Player $sender, TradeManager $trade){
    	$p=$transaction->getPlayer();
		$action=$transaction->getAction();
		$inv=$action->getInventory();
		$slot=$action->getSlot();
		$itemClicked=$transaction->getItemClicked();
        $itemClickedWith=$transaction->getItemClickedWith();
        $playerItems=[];
        $senderItems=[];
        for($slott=0;$slott<=44;$slott++){
    		$item=$inv->getItem($slott);
    		if($item!==null){
        		if(in_array($slott,[0,1,2,3,9,10,11,12,18,19,20,21,27,28,29,30,36,37,38,39])){
        			$playerItems[]=$item;
        		}
        		if(in_array($slott,[5,6,7,8,14,15,16,17,23,24,25,26,32,33,34,35,41,42,43,44])){
        			$senderItems[]=$item;
        		}
    		}
		}
        if($p->getName()==$player->getName()){
        	if($itemClicked->getName()=="§aAccept"){
        		$trade->setPlayerTrade(true);
				if($trade->getSenderTrade()){
					$trade->tradeItem($playerItems, $senderItems);
					$player->sendMessage("Traded Successfully");
					$sender->sendMessage("Traded Successfully");
					$this->plugin->sendSound($player, "random.levelup");
					$this->plugin->sendSound($sender, "random.levelup");
				}else{
					$p->sendMessage("Please Wait");
					$sender->sendMessage($p->getName()." Accepted The Trade");
				}
				$p->removeCurrentWindow();
				return $transaction->discard();
        	}
        	if($itemClicked->getName()=="§cDeny"){
        		$trade->setPlayerTrade(false);
        		$trade->setSenderTrade(false);
				$sender->sendMessage($player->getName()." Denied The Trade");
				$p->sendMessage("You Denied The Trade");
				$sender->removeCurrentWindow();
				$trade->returnItem($playerItems,$senderItems);
				$p->removeCurrentWindow();
				return $transaction->discard();
			}
			if(in_array($slot,[0,1,2,3,9,10,11,12,18,19,20,21,27,28,29,30,36,37,38,39])){
				return $transaction->continue();
			}
        	if(!in_array($slot,[5,6,7,8,14,15,16,17,23,24,25,26,32,33,34,35,41,42,43,44])){
        		$this->plugin->sendSound($p, "mob.horse.angry");
        		return $transaction->discard();
        	}
        }else{
        	if($itemClicked->getName()=="§aAccept"){
        		$trade->setSenderTrade(true);
				if($trade->getPlayerTrade()){
					$trade->tradeItem($playerItems, $senderItems);
					$player->sendMessage("Traded Successfully");
					$sender->sendMessage("Traded Successfully");
					$this->plugin->sendSound($player, "random.levelup");
					$this->plugin->sendSound($sender, "random.levelup");
				}else{
					$p->sendMessage("Please Wait");
					$player->sendMessage($p->getName()." Accepted The Trade");
				}
				$p->removeCurrentWindow();
				return $transaction->discard();
        	}
        	if($itemClicked->getName()=="§cDeny"){
        		$trade->setPlayerTrade(false);
        		$trade->setSenderTrade(false);
				$player->sendMessage($sender->getName()." Denied The Trade");
				$p->sendMessage("You Denied The Trade");
				$player->removeCurrentWindow();
				$trade->returnItem($playerItems,$senderItems);
				$p->removeCurrentWindow();
				return $transaction->discard();
			}
			if(in_array($slot,[5,6,7,8,14,15,16,17,23,24,25,26,32,33,34,35,41,42,43,44])){
				return $transaction->continue();
			}
        	if(!in_array($slot,[0,1,2,3,9,10,11,12,18,19,20,21,27,28,29,30,36,37,38,39])){
        		$this->plugin->sendSound($p, "mob.horse.angry");
        		return $transaction->discard();
        	}
        }
        return $transaction->discard();
    }
}
