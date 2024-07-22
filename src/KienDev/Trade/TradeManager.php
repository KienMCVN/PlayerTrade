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

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use pocketmine\item\{Item, ItemBlock};
use KienDev\Trade\Main;

final class TradeManager{

	//use SingletonTrait;

	private Player $player;
	private Player $sender;
	private Main $plugin;
	public bool $playerTrade=false;
	public bool $senderTrade=false;

	public function __construct(Player $player, Player $sender, Main $plugin){
		$this->plugin=$plugin;
		$this->player=$player;
		$this->sender=$sender;
	}

	public function setPlayerTrade(bool $data){
		$this->playerTrade=$data;
	}

	public function setSenderTrade(bool $data){
		$this->senderTrade=$data;
	}

	public function getPlayerTrade(): bool{
		return $this->playerTrade;
	}

	public function getSenderTrade(): bool{
		return $this->senderTrade;
	}

	public function returnItem(array $playerItems, array $senderItems){
		$this->plugin->giveItems($this->sender, $senderItems);
		$this->plugin->giveItems($this->player, $playerItems);
	}

	public function tradeItem(array $senderItems, array $playerItems){
		$this->plugin->giveItems($this->player, $senderItems);
		$this->plugin->giveItems($this->sender, $playerItems);
	}
}