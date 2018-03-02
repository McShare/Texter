<?php

/**
 * // English
 *
 * Texter, the display FloatingTextPerticle plugin for PocketMine-MP
 * Copyright (c) 2018 yuko fuyutsuki < https://github.com/fuyutsuki >
 *
 * This software is distributed under "MIT license".
 * You should have received a copy of the MIT license
 * along with this program.  If not, see
 * < https://opensource.org/licenses/mit-license >.
 *
 * ---------------------------------------------------------------------
 * // 日本語
 *
 * TexterはPocketMine-MP向けのFloatingTextPerticleを表示するプラグインです。
 * Copyright (c) 2018 yuko fuyutsuki < https://github.com/fuyutsuki >
 *
 * このソフトウェアは"MITライセンス"下で配布されています。
 * あなたはこのプログラムと共にMITライセンスのコピーを受け取ったはずです。
 * 受け取っていない場合、下記のURLからご覧ください。
 * < https://opensource.org/licenses/mit-license >
 */

namespace tokyo\pmmp\Texter\command;

// pocketmine
use pocketmine\{
  Player,
  command\Command,
  command\CommandSender,
  level\Position,
  utils\TextFormat as TF
};

// texter
use tokyo\pmmp\Texter\{
  Core,
  TexterApi,
  text\Text,
  text\FloatingText as FT
};

// mcbeformapi
use tokyo\pmmp\MCBEFormAPI\{
  FormApi,
  element\Button,
  element\Input,
  element\Label,
  element\StepSlider
};

/**
 * TxtCommandClass
 */
class TxtCommand extends Command {

  /** @var string */
  const COMMAND = "txt";
  const PERMISSION = "texter.command.txt";

  /** @var int */
  const ADD_KEY_FTNAME = 1;
  const ADD_KEY_TITLE = 3;
  const ADD_KEY_TEXT = 4;
  const EDIT_KEY_FTNAME = 1;
  const EDIT_KEY_TYPE = 2;
  const EDIT_TITLE = 0;
  const EDIT_TEXT = 1;
  const EDIT_KEY_CONTENT = 4;
  const MOVE_KEY_FTNAME = 1;
  const REMOVE_KEY_FTNAME = 1;
  const LIST_KEY_EDIT = 0;
  const LIST_KEY_MOVE = 1;
  const LIST_KEY_REMOVE = 2;

  /** @var int */
  const SESSION_PHASE1 = 0;
  const SESSION_PHASE2 = 1;

  /** @var ?Core */
  private $core = null;
  /** @var ?BaseLang */
  private $lang = null;
  /** @var array */
  private $session = [];
  /** @var array */
  private $fts = [];

  public function __construct(Core $core) {
    $this->core = $core;
    $this->texterApi = $core->getTexterApi();
    $this->lang = $core->getLang();
    $this->cdm = $core->getConfigDataManager();
    //
    $description = $this->lang->translateString("command.txt.description");
    $usage = $this->lang->translateString("command.txt.usage");
    parent::__construct(self::COMMAND, $description, $usage);
    $this->setPermission(self::PERMISSION);
  }

  public function execute(CommandSender $sender, string $label, array $args) {
    if (!$this->core->isEnabled()) return false;
    if (!$this->testPermission($sender)) return false;
    if ($sender instanceof Player) {
      if (TexterApi::canEdit($sender)) {
        if (isset($args[0])) {
          switch (strtolower($args[0])) {
            case 'add':
            case 'a':
              $this->addCommand($sender);
            break;

            case 'edit':
            case 'e':
              $this->editCommand($sender);
            break;

            case 'move':
            case 'm':
              $this->moveCommand($sender);
            break;

            case 'remove':
            case 'r':
              $this->removeCommand($sender);
            break;

            case 'list':
            case 'l':
              $name = strtolower($sender->getName());
              $this->session[$name] = self::SESSION_PHASE1;
              $this->listCommand($sender);
            break;

            default:
              $message = $this->lang->translateString("command.txt.usage");
              $sender->sendMessage(Core::PREFIX.$message);
            break;
          }
        }else {
          $message = $this->lang->translateString("command.txt.usage");
          $sender->sendMessage(Core::PREFIX.$message);
        }
      }
    }else {
      $message = $this->lang->translateString("error.console");
      $this->core->getLogger()->info(TF::RED.$message);
    }
  }

  private function addCommand(Player $player, string $default = "") {
    $custom = $this->core->getFormApi()->makeCustomForm([$this, "addReceive"]);
    $description = $this->lang->translateString("form.add.description");
    $tips = $this->lang->translateString("command.txt.usage.indent");
    $ftName = $this->lang->translateString("form.ftname.unique");
    $title = $this->lang->translateString("form.title");
    $text = $this->lang->translateString("form.text");
    $custom->setTitle(Core::PREFIX."/txt a(dd)")
    ->addElement(new Label($description))
    ->addElement(new Input($ftName, $ftName))
    ->addElement(new Label($tips))
    ->addElement(new Input($title, $title))
    ->addElement(new Input($text, $text))
    ->sendToPlayer($player);
  }

  private function editCommand(Player $player, string $default = "") {
    $custom = $this->core->getFormApi()->makeCustomForm([$this, "editReceive"]);
    $description = $this->lang->translateString("form.edit.description");
    $ftName = $this->lang->translateString("form.ftname");
    $type = $this->lang->translateString("form.edit.type");
    $title = $this->lang->translateString("form.title");
    $text = $this->lang->translateString("form.text");
    $tips = $this->lang->translateString("command.txt.usage.indent");
    $content = $this->lang->translateString("form.edit.content");
    $custom->setTitle(Core::PREFIX."/txt e(dit)")
    ->addElement(new Label($description))
    ->addElement(new Input($ftName, $ftName, $default))
    ->addElement(new StepSlider($type, [$title, $text]))
    ->addElement(new Label($tips))
    ->addElement(new Input($content, $content))
    ->sendToPlayer($player);
  }

  private function moveCommand(Player $player, string $default = "") {
    $custom = $this->core->getFormApi()->makeCustomForm([$this, "moveReceive"]);
    $description = $this->lang->translateString("form.move.description");
    $ftName = $this->lang->translateString("form.ftname");
    $custom->setTitle(Core::PREFIX."/txt m(ove)")
    ->addElement(new Label($description))
    ->addElement(new Input($ftName, $ftName, $default))
    ->sendToPlayer($player);
  }

  private function removeCommand(Player $player, string $default = "") {
    $custom = $this->core->getFormApi()->makeCustomForm([$this, "removeReceive"]);
    $description = $this->lang->translateString("form.remove.description");
    $ftName = $this->lang->translateString("form.ftname");
    $custom->setTitle(Core::PREFIX."/txt r(emove)")
    ->addElement(new Label($description))
    ->addElement(new Input($ftName, $ftName, $default))
    ->sendToPlayer($player);
  }

  private function listCommand(Player $player, int $phase = self::SESSION_PHASE1) {
    $name = strtolower($player->getName());
    switch ($phase) {
      case self::SESSION_PHASE1:
        $this->fts[$name] = [];
        $list = $this->core->getFormApi()->makeListForm([$this, "listReceive"]);
        $description = $this->lang->translateString("form.list.description.1");
        $list->setTitle(Core::PREFIX."/txt l(ist)")
        ->setContents($description);
        $fts = $this->texterApi->getFtsByLevel($player->getLevel());
        foreach ($fts as $textName => $ft) {
          if ($player->distance($ft->getPosition()) <= 10 &&
             ($player->isOp() || $name === $ft->getOwner())) {
            $list->addButton(new Button($ft->getName()));
            $this->fts[$name][] = $ft;
          }
        }
        $list->sendToPlayer($player);
      break;

      case self::SESSION_PHASE2:
        $list = $this->core->getFormApi()->makeListForm([$this, "listReceive"]);
        $description = $this->lang->translateString("form.list.description.2", [
          $this->fts[$name]->getName()
        ]);
        $list->setTitle(Core::PREFIX."/txt l(ist)")
        ->setContents($description)
        ->addButton(new Button("edit"))
        ->addButton(new Button("move"))
        ->addButton(new Button("remove"))
        ->sendToPlayer($player);
      break;
    }
  }

  /****************************************************************************
   * Callback functions
   */

  public function addReceive(Player $player, $response) {
    if (!FormApi::formCancelled($response)) {
      $level = $player->getLevel();
      if (!empty($response[self::ADD_KEY_FTNAME])) {
        $exists = $this->texterApi->getFtByLevel($level, $response[self::ADD_KEY_FTNAME]);
        if ($exists === null) {
          $ft = new FT(
            $response[self::ADD_KEY_FTNAME],
            Position::fromObject($player->add(0, 1, 0), $level),
            $response[self::ADD_KEY_TITLE],
            $response[self::ADD_KEY_TEXT],
            $player->getName()
          );
          if (TexterApi::canEdit($player, $ft)) {
            $ft->sendToLevel($level);
            $this->texterApi->registerText($ft);
            $message = $this->lang->translateString("command.txt.add.success", [
              TF::clean($response[self::ADD_KEY_FTNAME])
            ]);
            $player->sendMessage(TF::GREEN.Core::PREFIX.$message);
          }
        }else {
          $message = $this->lang->translateString("error.ftname.exists", [
            TF::clean($response[self::ADD_KEY_FTNAME])
          ]);
          $player->sendMessage(TF::RED.Core::PREFIX.$message);
        }
      }else {
        $message = $this->lang->translateString("error.ftname.not.specified");
        $player->sendMessage(TF::RED.Core::PREFIX.$message);
      }
    }
  }

  public function editReceive(Player $player, $response) {
    if (!FormApi::formCancelled($response)) {
      $level = $player->getLevel();
      if (!empty($response[self::EDIT_KEY_FTNAME])) {
        $ft = $this->texterApi->getFtByLevel($level, $response[self::EDIT_KEY_FTNAME]);
        if ($ft !== null) {
          switch ($response[self::EDIT_KEY_TYPE]) {
            case self::EDIT_TITLE:
              $check = clone $ft;
              $check->setTitle($response[self::EDIT_KEY_CONTENT]);
              if (TexterApi::canEdit($player, $check)) {
                $ft->setTitle($response[self::EDIT_KEY_CONTENT])
                ->sendToLevel($level, Text::SEND_TYPE_EDIT);
                $this->core->getFtsDataManager()->saveTextByLevel($level, $ft);
                $message = $this->lang->translateString("command.txt.edit.success", [
                  TF::clean($response[self::EDIT_KEY_FTNAME]),
                  $this->lang->translateString("form.title")
                ]);
                $player->sendMessage(TF::GREEN.Core::PREFIX.$message);
              }
            break;

            case self::EDIT_TEXT:
              $check = clone $ft;
              $check->setText($response[self::EDIT_KEY_CONTENT]);
              if (TexterApi::canEdit($player, $check)) {
                $ft->setText($response[self::EDIT_KEY_CONTENT])
                ->sendToLevel($level, Text::SEND_TYPE_EDIT);
                $this->core->getFtsDataManager()->saveTextByLevel($level, $ft);
                $message = $this->lang->translateString("command.txt.edit.success", [
                  TF::clean($response[self::EDIT_KEY_FTNAME]),
                  $this->lang->translateString("form.text")
                ]);
                $player->sendMessage(TF::GREEN.Core::PREFIX.$message);
              }
            break;
          }
        }else {
          $message = $this->lang->translateString("error.ftname.not.exists");
          $player->sendMessage(TF::RED.Core::PREFIX.$message);
        }
      }else {
        $message = $this->lang->translateString("error.ftname.not.specified");
        $player->sendMessage(TF::RED.Core::PREFIX.$message);
      }
    }
  }

  public function moveReceive(Player $player, $response) {
    if (!FormApi::formCancelled($response)) {
      $level = $player->getLevel();
      if (!empty($response[self::MOVE_KEY_FTNAME])) {
        $ft = $this->texterApi->getFtByLevel($level, $response[self::MOVE_KEY_FTNAME]);
        if ($ft !== null) {
          if (TexterApi::canEdit($player, $ft)) {
            $ft->setPosition(Position::fromObject($player->add(0, 2, 0), $level))
            ->sendToLevel($level, Text::SEND_TYPE_MOVE);
            $this->core->getFtsDataManager()->saveTextByLevel($level, $ft);
            $message = $this->lang->translateString("command.txt.move.success", [
              TF::clean($response[self::MOVE_KEY_FTNAME]),
              $this->lang->translateString("form.move.here")
            ]);
            $player->sendMessage(TF::GREEN.Core::PREFIX.$message);
          }
        }else {
          $message = $this->lang->translateString("error.ftname.not.exists");
          $player->sendMessage(TF::RED.Core::PREFIX.$message);
        }
      }else {
        $message = $this->lang->translateString("error.ftname.not.specified");
        $player->sendMessage(TF::RED.Core::PREFIX.$message);
      }
    }
  }

  public function removeReceive(Player $player, $response) {
    if (!FormApi::formCancelled($response)) {
      $level = $player->getLevel();
      if (!empty($response[self::MOVE_KEY_FTNAME])) {
        $ft = $this->texterApi->getFtByLevel($level, $response[self::REMOVE_KEY_FTNAME]);
        if ($ft !== null) {
          if (TexterApi::canEdit($player, $ft)) {
            $ft->sendToLevel($level, Text::SEND_TYPE_REMOVE);
            $this->texterApi->removeFtByLevel($level, $ft->getName());
            $message = $this->lang->translateString("command.txt.remove.success", [
              TF::clean($response[self::REMOVE_KEY_FTNAME])
            ]);
            $player->sendMessage(TF::GREEN.Core::PREFIX.$message);
          }
        }else {
          $message = $this->lang->translateString("error.ftname.not.exists");
          $player->sendMessage(TF::RED.Core::PREFIX.$message);
        }
      }else {
        $message = $this->lang->translateString("error.ftname.not.specified");
        $player->sendMessage(TF::RED.Core::PREFIX.$message);
      }
    }
  }

  public function listReceive(Player $player, $response) {
    $name = strtolower($player->getName());
    if (!FormApi::formCancelled($response)) {
      switch ($this->session[$name]) {
        case self::SESSION_PHASE1:
          $ft = $this->fts[$name][$response];
          $this->fts[$name] = $ft;
          $this->session[$name] = self::SESSION_PHASE2;
          $this->listCommand($player, self::SESSION_PHASE2);
        break;

        case self::SESSION_PHASE2:
          switch ($response) {
            case self::LIST_KEY_EDIT:
              $this->editCommand($player, $this->fts[$name]->getName());
            break;

            case self::LIST_KEY_MOVE:
              $this->moveCommand($player, $this->fts[$name]->getName());
            break;

            case self::LIST_KEY_REMOVE:
              $this->removeCommand($player, $this->fts[$name]->getName());
            break;
          }
          $this->session[$name] = self::SESSION_PHASE1;
          $this->fts[$name] = [];
        break;
      }
    }else {
      $this->session[$name] = self::SESSION_PHASE1;
      $this->fts[$name] = [];
    }
  }
}
