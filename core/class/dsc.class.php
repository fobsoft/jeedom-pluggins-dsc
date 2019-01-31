<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class dsc extends eqLogic {
  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'dsc_install';
    $request = realpath(dirname(__FILE__) . '/../../resources/node_modules/request');
    $return['progress_file'] = '/tmp/dsc_dep';
    if (is_dir($request)) {
      $return['state'] = 'ok';
    } else {
      $return['state'] = 'nok';
    }
    return $return;
  }

  public static function dependancy_install() {
    $install_path = dirname(__FILE__) . '/../../resources';
    passthru('/bin/bash ' . $install_path . '/nodejs.sh ' . $install_path . ' dsc >> ' . log::getPathToLog('dsc_install') . ' 2>&1 &');
  }

  public static function deamon_start($_debug = false) {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    log::add('dsc', 'info', 'Lancement du démon dsc');

    $password = config::byKey('password', 'dsc');
    $addr = config::byKey('addr', 'dsc');
    $port = config::byKey('port', 'dsc');
    $zone = config::byKey('zone', 'dsc');
    $partition = config::byKey('partition', 'dsc');
    log::add('dsc','debug','Récupération de la configuration : Host ' . $addr . ' Port ' . $port . ' Zones ' . $zone . ' Partitions ' . $partition);

    $url = network::getNetworkAccess('internal') . '/plugins/dsc/core/api/dsc.php?apikey=' . jeedom::getApiKey('dsc');

    $sensor_path = realpath(dirname(__FILE__) . '/../../resources');
    $cmd = 'nice -n 19 nodejs ' . $sensor_path . '/jeedomdsc.js ' . $url . ' ' . $password . ' ' . $addr . ' ' . $port . ' ' . $zone . ' ' . $partition;

    //log::add('dsc', 'debug', 'Lancement démon dsc : ' . $cmd);
    $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('dsc_node') . ' 2>&1 &');
    if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
      log::add('dsc', 'error', $result);
      return false;
    }
    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add('dsc', 'error', 'Impossible de lancer un démon dsc, vérifiez le port', 'unableStartDeamon');
      return false;
    }
    message::removeAll('dsc', 'unableStartDeamon');
    log::add('dsc', 'info', 'Démons dsc lancé');
    return true;
  }

  public static function deamon_info() {
    $return = array();
    $return['log'] = 'dsc_node';
    $return['launchable'] = 'ok';
    $pid = trim( shell_exec ('ps ax | grep "dsc/resources/jeedomdsc.js" | grep -v "grep" | wc -l') );
    if ($pid != '' && $pid != '0') {
      $return['state'] = 'ok';
    } else {
      $return['state'] = 'nok';
    }
    if (config::byKey('password', 'dsc') == '' || config::byKey('addr', 'dsc') == '' || config::byKey('port', 'dsc') == '' || config::byKey('zone', 'dsc') == '' || config::byKey('zone', 'dsc') == '') {
      $return['launchable'] = 'nok';
    }
    return $return;
  }

  public static function deamon_stop() {
    exec('kill $(ps aux | grep "/jeedomdsc.js" | awk \'{print $2}\')');
    log::add('dsc', 'info', 'Arrêt du service dsc');
    $deamon_info = self::deamon_info();
    if (count($deamon_info['launched']) != 0) {
      sleep(1);
      exec('kill -9 $(ps aux | grep "/jeedomdsc.js" | awk \'{print $2}\')');
    }
    $deamon_info = self::deamon_info();
    if (count($deamon_info['launched']) != 0) {
      sleep(1);
      exec('sudo kill -9 $(ps aux | grep "/jeedomdsc.js" | awk \'{print $2}\')');
    }
  }

  public static function populate() {
    $zone = config::byKey('zone', 'dsc');
    $partition = config::byKey('partition', 'dsc');
    if ($zone != '' || $partition != '') {
      /********************HOME MAIN************************/
      if ($partition > 1) {
        $dsc = self::byLogicalId('system', 'dsc');
        
        if (!is_object($dsc)) {
          log::add('dsc', 'info', 'Equipement n existe pas, création objet system');
          $dsc = new dsc();
          $dsc->setEqType_name('dsc');
          $dsc->setLogicalId('system');
          $dsc->setName('system');
          $dsc->setIsEnable(true);
          $dsc->save();
        }
      }
      else {
        $dsc = self::byLogicalId('partition'.$partition, 'dsc');
        
        if (!is_object($dsc)) {
          log::add('dsc', 'info', 'Equipement n existe pas, création ' . $logical);
          $dsc = new dsc();
          $dsc->setEqType_name('dsc');
          $dsc->setLogicalId($logical);
          $dsc->setName('Partition ' . $partition);
          $dsc->setIsEnable(true);
          $dsc->save();
        }
      }
      
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'BatterieEtat');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setIsHistorized(0);
        $dscCmd->setName( 'Batterie Etat' );
        $dscCmd->setType('info');
        $dscCmd->setLogicalId('BatterieEtat');
        $dscCmd->setSubType('binary');
        $dscCmd->setIsVisible(0);
        $dscCmd->setConfiguration('value', '0' );
        $dscCmd->save();
      }
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'AlimentationSecondaire');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setIsHistorized(0);
        $dscCmd->setName( 'Alimentation Secondaire' );
        $dscCmd->setType('info');
        $dscCmd->setLogicalId('AlimentationSecondaire');
        $dscCmd->setSubType('binary');
        $dscCmd->setIsVisible(0);
        $dscCmd->setConfiguration('value', '0' );
        $dscCmd->save();
      }
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'panicfeu');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setLogicalId('panicfeu');
        $dscCmd->setName( 'Assistance Incendie' );
        $dscCmd->setConfiguration('request', '0601');
        $dscCmd->setType('action');
        $dscCmd->setSubType('other');
        $dscCmd->save();
      }
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'panicambulance');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setLogicalId('panicambulance');
        $dscCmd->setName( 'Assistance Medical' );
        $dscCmd->setConfiguration('request', '0602');
        $dscCmd->setType('action');
        $dscCmd->setSubType('other');
        $dscCmd->save();
      }
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'panicpolice');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setLogicalId('panicpolice');
        $dscCmd->setName( 'Assistance Policiere' );
        $dscCmd->setConfiguration('request', '0603');
        $dscCmd->setType('action');
        $dscCmd->setSubType('other');
        $dscCmd->save();
      }
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'lastevent');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setName( 'Dernier evenement' );
        $dscCmd->setIsVisible(0);
        $dscCmd->setIsHistorized(0);
        $dscCmd->setSubType('string');
        $dscCmd->setLogicalId('lastevent');
        $dscCmd->setType('info');
        $dscCmd->save();
      }
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'notifyevent');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setName( 'Notify evenement' );
        $dscCmd->setIsVisible(0);
        $dscCmd->setIsHistorized(0);
        $dscCmd->setSubType('string');
        $dscCmd->setLogicalId('notifyevent');
        $dscCmd->setType('info');
        $dscCmd->save();
      }

      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'status');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setName( 'Statut Alarme' );
        $dscCmd->setIsVisible(0);
        $dscCmd->setIsHistorized(0);
        $dscCmd->setSubType('numeric');
        $dscCmd->setLogicalId('status');
        $dscCmd->setType('info');
        $dscCmd->save();
      }
      
      /********************HOME MAIN************************/

      $izone = 1;
      $ipart = 1;
      while ($izone <= $zone) {
        $logical = 'zone' . $izone;
        $dsc = self::byLogicalId($logical, 'dsc');
        if (!is_object($dsc)) {
          log::add('dsc', 'info', 'Equipement n existe pas, création ' . $logical);
          $dsc = new dsc();
          $dsc->setEqType_name('dsc');
          $dsc->setLogicalId($logical);
          $dsc->setName('Zone ' . $izone);
          $dsc->setIsEnable(true);
          $dsc->save();
        }
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'alarm');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setIsHistorized(0);
          $dscCmd->setName( 'Alarme' );
          $dscCmd->setType('info');
          $dscCmd->setLogicalId('alarm');
          $dscCmd->setSubType('binary');
          $dscCmd->setIsVisible(0);
          $dscCmd->setConfiguration('value', '0' );
          $dscCmd->setTemplate("dashboard",'alert' );
          $dscCmd->setTemplate("mobile",'alert' );
          $dscCmd->setDisplay('generic_type','SABOTAGE');
          $dscCmd->save();
        }

        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'tamper');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setIsHistorized(0);
          $dscCmd->setName( 'Sabotage' );
          $dscCmd->setIsVisible(0);
          $dscCmd->setSubType('binary');
          $dscCmd->setLogicalId('tamper');
          $dscCmd->setType('info');
          $dscCmd->setConfiguration('value', '0' );
          $dscCmd->setTemplate("dashboard",'lock' );
          $dscCmd->setTemplate("mobile",'lock' );
          $dscCmd->setDisplay('generic_type','LOCK_STATE');
          $dscCmd->save();
        }

        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'fault');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setIsHistorized(0);
          $dscCmd->setName( 'Erreur' );
          $dscCmd->setIsVisible(0);
          $dscCmd->setSubType('binary');
          $dscCmd->setLogicalId('fault');
          $dscCmd->setType('info');
          $dscCmd->setConfiguration('value', '0' );
          $dscCmd->setTemplate("dashboard",'bell' );
          $dscCmd->setTemplate("mobile",'bell' );
          $dscCmd->setDisplay('generic_type','SABOTAGE');
          $dscCmd->save();
        }

        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'activity');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setName( 'Activité' );
          $dscCmd->setIsHistorized(0);
          $dscCmd->setIsVisible(1);
          $dscCmd->setSubType('binary');
          $dscCmd->setLogicalId('activity');
          $dscCmd->setType('info');
          $dscCmd->setConfiguration('value', '0' );
          $dscCmd->setTemplate("dashboard",'bell' );
          $dscCmd->setTemplate("mobile",'bell' );
          $dscCmd->setDisplay('generic_type','OPENING');
          $dscCmd->save();
        }

        //incrémentation
        $izone++;
      }
      while ($ipart <= $partition) {
        $logical = 'partition' . $ipart;
        $dsc = self::byLogicalId($logical, 'dsc');
        if (!is_object($dsc)) {
          log::add('dsc', 'info', 'Equipement n existe pas, création ' . $logical);
          $dsc = new dsc();
          $dsc->setEqType_name('dsc');
          $dsc->setLogicalId($logical);
          $dsc->setName('Partition ' . $ipart);
          $dsc->setIsEnable(true);
          $dsc->save();
        }
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'status');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setName( 'Statut Alarme' );
          $dscCmd->setIsVisible(0);
          $dscCmd->setIsHistorized(0);
          $dscCmd->setSubType('numeric');
          $dscCmd->setLogicalId('status');
          $dscCmd->setType('info');
          $dscCmd->save();
        }

        /*$dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'state');
        if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setName( 'Etat Activation' );
        $dscCmd->setIsVisible(1);
        $dscCmd->setIsHistorized(0);
        $dscCmd->setSubType('numeric');
        $dscCmd->setLogicalId('state');
        $dscCmd->setType('info');
        $dscCmd->save();
      }*/

      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'alarmoff');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setLogicalId('alarmoff');
        $dscCmd->setName( 'Désactivation Alarme' );
        $dscCmd->setConfiguration('request', '040'.$ipart);
        $dscCmd->setType('action');
        $dscCmd->setSubType('other');
        $dscCmd->save();
      }

      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'alarmon');
      if (is_object($dscCmd)) {
        $dscCmd->remove();
      }

      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'alarmstay');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setLogicalId('alarmstay');
        $dscCmd->setName( 'Activation Stay' );
        $dscCmd->setConfiguration('request', '031'.$ipart);
        $dscCmd->setType('action');
        $dscCmd->setSubType('other');
        $dscCmd->save();
      }

      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'alarmzero');
      if (!is_object($dscCmd)) {
        $dscCmd = new dscCmd();
        $dscCmd->setEqLogic_id($dsc->getId());
        $dscCmd->setEqType('dsc');
        $dscCmd->setLogicalId('alarmzero');
        $dscCmd->setName( 'Activation Zero Entry' );
        $dscCmd->setConfiguration('request', '032'.$ipart);
        $dscCmd->setType('action');
        $dscCmd->setSubType('other');
        $dscCmd->save();
      }

        /********************HOME MAIN************************/
        // 1 si l'alarme est declenche sinon 0
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'AlarmeEtat');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setName( 'Alarme Etat' );
          $dscCmd->setIsVisible(0);
          $dscCmd->setIsHistorized(0);
          $dscCmd->setSubType('binary');
          $dscCmd->setLogicalId('AlarmeEtat');
          $dscCmd->setConfiguration('value', '0' );
          $dscCmd->setTemplate("dashboard",'bell' );
          $dscCmd->setTemplate("mobile",'bell' );
          $dscCmd->setType('info');
          $dscCmd->save();
        }
        
        // Source du declenchement
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'AlarmeSource');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setName( 'Alarme Source' );
          $dscCmd->setIsVisible(0);
          $dscCmd->setIsHistorized(0);
          $dscCmd->setSubType('string');
          $dscCmd->setLogicalId('AlarmeSource');
          $dscCmd->setType('info');
          $dscCmd->save();
        }
        
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'TroubleSysteme');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setIsHistorized(0);
          $dscCmd->setName( 'Trouble Systeme' );
          $dscCmd->setType('info');
          $dscCmd->setLogicalId('TroubleSysteme');
          $dscCmd->setSubType('binary');
          $dscCmd->setIsVisible(1);
          $dscCmd->setConfiguration('value', '0' );
          $dscCmd->setTemplate("dashboard",'bell' );
          $dscCmd->setTemplate("mobile",'bell' );
          $dscCmd->setDisplay('generic_type','OPENING');
          $dscCmd->save();
        }

        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'TroubleSource');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setName( 'Trouble Source' );
          $dscCmd->setIsVisible(0);
          $dscCmd->setIsHistorized(0);
          $dscCmd->setSubType('string');
          $dscCmd->setLogicalId('TroubleSource');
          $dscCmd->setType('info');
          $dscCmd->save();
        }
        
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'PGM1');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setLogicalId('PGM1');
          $dscCmd->setName( 'Activation PGM1' );
          $dscCmd->setConfiguration('request', '020'.$ipart.'1');
          $dscCmd->setType('action');
          $dscCmd->setSubType('other');
          $dscCmd->save();
        }
        
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'PGM2');
        if (!is_object($dscCmd)) {
          $dscCmd = new dscCmd();
          $dscCmd->setEqLogic_id($dsc->getId());
          $dscCmd->setEqType('dsc');
          $dscCmd->setLogicalId('PGM2');
          $dscCmd->setName( 'Activation PGM2' );
          $dscCmd->setConfiguration('request', '020'.$ipart.'2');
          $dscCmd->setType('action');
          $dscCmd->setSubType('other');
          $dscCmd->save();
        }
        /********************HOME MAIN************************/
        
        //incrémentation
        $ipart++;
      }
  
    }
    return true;
  }

  public static function eventZone($data) {
    $logical = 'zone' . $data['id'];
    switch ($data['value']) {
      case '601':
      $cmd='alarm';
      $code='1';
      break;

      case '602':
      $cmd='alarm';
      $code='0';
      break;

      case '603':
      $cmd='tamper';
      $code='1';
      break;

      case '604':
      $cmd='tamper';
      $code='0';
      break;

      case '605':
      $cmd='fault';
      $code='1';
      break;

      case '606':
      $cmd='fault';
      $code='0';
      break;

      case '609':
      $cmd='activity';
      $code='1';
      break;

      case '610':
      $cmd='activity';
      $code='0';
      break;
      default:
        $cmd =  null;
        $code = null;
      break;
    }

    if ($code == 1) {
      // Construction du lastEvent
      $dsc =      self::byLogicalId($logical, 'dsc');
      $object =	  $dsc -> getObject();
      $dscCmd =   dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),$cmd);
      
      switch ($dscCmd -> getDisplay('generic_type')) {
        case 'PRESENCE':
        case 'MOTION':
          $lastEvent =  'Détection volumétrique, ' . strtolower ($object -> getName());
          break;
        case 'OPENING':
        case 'OPENING_WINDOW':
          $lastEvent =  'Détection périmétrique, ' . strtolower ($dsc -> getName() . ' ' . $object -> getName());        
          break;
        case 'PANIQUE':
          $lastEvent =  'Demande d\'assistance, ' . strtolower ($dsc -> getName() . ' ' . $object -> getName());        
          break;
        case 'SMOKE':
          $lastEvent =  'Détection de fumer, ' . strtolower ($object -> getName());
          break;
        default:
          $lastEvent =  'Détection ' . $dscCmd -> getDisplay('generic_type') . ', ' . strtolower ($object -> getName());
          break;
      }
    }
    else {
      $lastEvent = null;
    }

    log::add('dsc', 'info', 'Evènement sur zone : ' . $logical . ' commande : ' . $cmd . ' code : ' . $code . ' (' . $data['value'] . ')');
    dsc::switchStatus($logical,$cmd,$code,$lastEvent);
  }

  public static function eventPartition($data) {
    $systemLogicalId =  dsc::getSourceSystemId();
    $logical = 'partition' . $data['id'];
    $userName =         '';
    $notifyEvent =      false;

    if (!isset($data['user'])) {
      log::add('dsc', 'info', 'Evènement sur partition ' . $logical . ' de type ' . $data['value']);
    }
    else {
      $userName = ' par '.config::byKey('user' . $data['user'], 'dsc');
      
      if ($userName == ' par ') {
        $userName = ' par user' . $data['user'];
      }
      
      log::add('dsc', 'info', 'Evènement sur partition ' . $logical . ' de type ' . $data['value'] . $userName);
    }

    switch ($data['value']) {
      case '650':
        // Partition Ready
        $data['eventDesc'] = null;
      
        // Si il y avait alarme, on reset la source de l'alarme
        $dsc = self::byLogicalId($logical, 'dsc');
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),'AlarmeEtat');
        if ($dscCmd->getConfiguration('value') == 1) {
          dsc::switchStatus($logical,'AlarmeEtat','0');
          dsc::switchStatus($logical,'AlarmeSource','');
          dsc::switchStatus($systemLogicalId,'notifyEvent','');
        }
        break;                                      
      case '651':
        // Partition is NOT Ready
        $data['eventDesc'] = null;
        break;
      case '652':
        $data['eventDesc'] = null; //Activation de l\'arlarme complété';
        break;
      case '653':
        // Partition is Ready and Force Arming is Enabled
        $data['eventDesc'] = null;
        break;
      case '654':
        dsc::switchStatus($logical,'AlarmeEtat','1');
        $data['eventDesc'] = 'Veuillez rester sur placee';
        $notifyEvent = true;
        break;
      case '655':
        $data['eventDesc'] = 'Alarme désarmé';
        break;
      case '656':
        $data['eventDesc'] = 'Activation de l\'arlarme en cour';
        $notifyEvent = true;
        break;
      case '657':
        dsc::switchStatus($logical,'AlarmeEtat','1');
        $data['eventDesc'] = 'Vous avez 60 secondes pour désarmer le système, dans le cas contraire une assistance policière sera demandé.';
        $notifyEvent = true;
        break;
      case '700':
      case '701':
        $data['eventDesc'] = 'Activation de l\'alarme complété' . $userName;
        $notifyEvent = true;
        break;
      case '750':
      case '751':
        $data['eventDesc'] = 'Désactivation de l\'alarme complété' . $userName;
        $notifyEvent = true;
        dsc::switchStatus($logical,'AlarmeEtat','0');
        dsc::switchStatus($logical,'AlarmeSource','');
        break;
    }

    dsc::switchStatus($logical,'status',$data['value'],$data['eventDesc'], $notifyEvent);
  }

  public static function eventSystem($data) {
    $systemLogicalId = dsc::getSourceSystemId();
    $notifyEvent = true;
    $cmd = null;
    
    switch ($data['value']) {
      case '502':
        break;
      case '800':
        $cmd = 'BatterieEtat';
        $value = '1';
        break;
      case '801':
        $cmd = 'BatterieEtat';
        $value = '0';
        break;
      case '802':
        $cmd = 'AlimentationSecondaire';
        $value = '1';
        $data['eventDesc'] = 'Alimentation du module de sécurité via l\'alimentation secondaire';
        break;
      case '803':
        $cmd = 'AlimentationSecondaire';
        $value = '0';
        $data['eventDesc'] = 'Alimentation du module de sécurité via l\'alimentation principal';
        break;
      case '840':
        $cmd = 'TroubleSysteme';
        $value = '1';
        break;
      case '841':
        $cmd = 'TroubleSysteme';
        $value = '0';
        
        // Aucun lastEvent requis pour cette action
        $data['eventDesc'] = null;
        $notifyEvent = false;
        
        // Supression du message de trouble
        dsc::switchStatus($systemLogicalId,'TroubleSource','');
        
        // Si la batterie etait sur allimentation secondaire
        $dsc = self::byLogicalId($systemLogicalId, 'dsc');
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc -> getId(),'AlimentationSecondaire');
        if ($dscCmd -> getConfiguration('value') == 1) {
          dsc::switchStatus($systemLogicalId,'AlimentationSecondaire','0','Alimentation du module de sécurité via l\'alimentation principal',1);
        }
        break;
      default:
        $cmd = null;
        $data['eventDesc'] = null;
        break;
    }
    
    dsc::switchStatus($systemLogicalId,'status',$data['value'],$data['eventDesc'],$notifyEvent);
    if (isset($cmd)) {
      dsc::switchStatus($systemLogicalId,$cmd,$value);
    }
  }

  public static function eventTrouble($data) {
    $systemLogicalId = dsc::getSourceSystemId();

    dsc::switchStatus($systemLogicalId,'status',$data['value']);

    switch ($data['value']) {
      case '849':
        switch ($data['eventCode']) {
          case 1:
            $troubleSource = 'Perte de l\'alimentation principal';
            dsc::switchStatus($systemLogicalId,'AlimentationSecondaire',1);
            break;
          case 2:
            $troubleSource = 'Perte de la liaison téléphonique';
            break;
          case 3:
            $troubleSource = 'Erreur lors de la communication avec la central';
            break;
          case 4: //Sensor/Zone Fault
          case 5: //Sensor/Zone Tamper
          case 6: //Sensor/Zone Low Battery
          case 7: //Loff Of Time
            $troubleSource = $data['eventDesc'];
            break;
          default:
            $troubleSource = 'Source (' . $data['eventCode'] . ') non defini';
            break;
        }
        break;
      case '1000':
        switch ($data['eventCode']) {
          default:
            $troubleSource =  'Erreur request jeedomdsc (' . $data['eventCode'] . ')';
            break;
        }
        break;
      default:
        $troubleSource =  'Value ' . $data['value'] . ' non defini';
        break;
    }

    dsc::switchStatus($systemLogicalId,'TroubleSysteme',1,$troubleSource);
    dsc::switchStatus($systemLogicalId,'TroubleSource',$troubleSource);
  }

  public static function switchStatus($id,$cmd,$value,$lastEvent = null,$notifyEvent = 0) {
    if (isset($cmd)) {
      // Set source alarm
      if ($cmd == 'AlarmeEtat' && $value == 1) {
        $systemLogicalId =  dsc::getSourceSystemId();

        // Rechercher de la valeur de LastEvent pour obtenir la source de l'alarme
        $systemDsc = self::byLogicalId($systemLogicalId, 'dsc');
        $dscLastEventCmd = dscCmd::byEqLogicIdAndLogicalId($systemDsc->getId(),'lastevent');
        dsc::switchStatus($id,'AlarmeSource',$dscLastEventCmd->getConfiguration('value'));
      }
      
      $dsc = self::byLogicalId($id, 'dsc');
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dsc->getId(),$cmd);

      if ($dscCmd->getConfiguration() != $value) {
        $dscCmd->setConfiguration('value', $value );
        $dscCmd->save();
        $dscCmd->event($value);
        log::add('dsc', 'info', 'Changement de valeur de ' . $dsc -> getName() . '->'. $dscCmd -> getName() . ' pour ' . $value . ' ('.$lastEvent.')');
      }
      else {
        log::add('dsc', 'info', 'Valeur de ' . $dsc -> getName() . ':'. $dscCmd -> getName() . ' deja a ' . $value);
      }
    }
    elseif (!isset($lastEvent)) {
      log::add('dsc', 'info', 'Changement du lastEvent pour un code inconnue: ' . $value .' de '. $id);
    }
    
    if (isset($lastEvent)) {
      $dscSystemId = dsc::getSourceSystemId();
      
      $dscSystem = self::byLogicalId($dscSystemId, 'dsc');
      $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dscSystem->getId(),'lastevent');
      $dscCmd->setConfiguration('value', $lastEvent);
      $dscCmd->save();
      $dscCmd->event($lastEvent);
      log::add('dsc', 'info', 'Changement du lastEvent pour: ' . $lastEvent);
      
      if ($notifyEvent) {
        $dscCmd = dscCmd::byEqLogicIdAndLogicalId($dscSystem->getId(),'notifyevent');
        $dscCmd->setConfiguration('value', $lastEvent);
        $dscCmd->save();
        $dscCmd->event($lastEvent);
        log::add('dsc', 'info', 'Changement du notifyevent pour: ' . $lastEvent);
      }
    }
  }

  public static function getSourceSystemId() {
    $partition = config::byKey('partition', 'dsc');

    if ($partition > 1) {
      return 'system';
    }
    else {
      return 'partition1';
    }
  }

  public static function sendCommand($id,$value) {
    log::add('dsc', 'info', 'Envoi commande ' . $value . ' pour ' . $id);

    $password = config::byKey('password', 'dsc');
    $port = config::byKey('port', 'dsc');
    $zone = config::byKey('zone', 'dsc');
    $partition = config::byKey('partition', 'dsc');
    log::add('dsc','debug','Récupération de la configuration : Host ' . $addr . ' Port ' . $port . ' Zones ' . $zone . ' Partitions ' . $partition);

    $sensor_path = realpath(dirname(__FILE__) . '/../../resources');
    $cmd = 'nice -n 19 nodejs ' . $sensor_path . '/jeedomcmd.js ' . $password . ' ' . $port . ' ' . $zone . ' ' . $partition . ' ' . $value;

    log::add('dsc', 'debug', 'Lancement commande : ' . $cmd);

     $result = exec($cmd . ' >> ' . log::getPathToLog('dsc_command') . ' 2>&1 &');

  }
}

class dscCmd extends cmd {
  public function execute($_options = null) {
    switch ($this->getType()) {
      case 'info' :
      return $this->getConfiguration('value');
      break;

      case 'action' :
      $eqLogic = $this->getEqLogic();
      dsc::sendCommand($eqLogic->getLogicalId(),$this->getConfiguration('request'));
      return true;
    }
  }
}

?>
