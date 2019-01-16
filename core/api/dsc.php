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

 $messageType = init('messagetype');
 $id = init('id');
 $value = init('value');
 switch ($messageType) {
   case 'zone' : dsc::eventZone($id,$value); break;
   case 'partition' : dsc::eventPartition($id,$value); break;
 }
 log::add('dsc', 'debug', 'Event ' . $messageType . ' id ' . $id . ' value ' . $value );

 return true;

?>
