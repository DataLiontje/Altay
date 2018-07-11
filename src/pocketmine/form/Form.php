<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

/**
 * API for Minecraft: Bedrock custom UI (forms)
 */
namespace pocketmine\form;

use pocketmine\Player;

/**
 * Base class for a custom form. Forms are serialized to JSON data to be sent to clients.
 */
abstract class Form implements \JsonSerializable{

	public const TYPE_MODAL = "modal";
	public const TYPE_MENU = "form";
	public const TYPE_CUSTOM_FORM = "custom_form";

	/** @var string */
	protected $title;

	public function __construct(string $title){
		$this->title = $title;
	}

	/**
	 * Returns the type used to show this form to clients
	 * @return string
	 */
	abstract public function getType() : string;

	/**
	 * Returns the text shown on the form title-bar.
	 * @return string
	 */
	public function getTitle() : string{
		return $this->title;
	}

	/**
	 * Handles a form response from a player. Plugins should not override this method. Override the appropriate onSubmit
	 * for each form instead.
	 *
	 * @param Player $player
	 * @param mixed  $data
	 *
	 * @return Form|null a form which will be opened immediately (before queued forms) as a response to this form, or null if not applicable.
	 */
	abstract public function handleResponse(Player $player, $data) : ?Form;

	/**
	 * Serializes the form to JSON for sending to clients.
	 *
	 * @return array
	 */
	final public function jsonSerialize() : array{
		$ret = $this->serializeFormData();
		$ret["type"] = $this->getType();
		$ret["title"] = $this->getTitle();

		return $ret;
	}

	/**
	 * Serializes additional data needed to show this form to clients.
	 * @return array
	 */
	abstract protected function serializeFormData() : array;

}