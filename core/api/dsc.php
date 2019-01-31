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
 require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

 if (!jeedom::apiAccess(init('apikey'), 'dsc')) {
 	echo __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (dsc)', __FILE__);
 	die();
 }

 $data['messageType'] = init('messageType');
 $data['id'] =          init('id');
 $data['value'] =       init('value');
 $data['user'] =        init('user',null);
 $data['eventCode'] =   init('eventCode');
 $data['eventDesc'] =   init('eventDesc');

 switch ($data['messageType']) {
   case 'zone' :      dsc::eventZone($data); 
                      break;
    case 'partitionuser' :
   case 'partition' : dsc::eventPartition($data); 
                      break;
    case 'system' :         dsc::eventSystem($data); 
                            break;
    case 'trouble' :        dsc::eventTrouble($data);
                            break;
 }
 log::add('dsc', 'debug', 'Event ' . json_encode($data));

 return true;

?>