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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>


<form class="form-horizontal">
  <div class="form-group">
    <fieldset>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{Adresse IP de la carte Envisalink : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="addr" style="margin-top:5px" placeholder="192.168.1.1"/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{Port TCP de la carte Envisalink : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="port" style="margin-top:5px" placeholder="4025"/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{Password de la carte Envisalink : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" type="password" class="configKey form-control" data-l1key="password" style="margin-top:5px" placeholder="12345"/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{Nombre de Zones : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="zone" style="margin-top:5px" placeholder="7"/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{Nombre de Partitions : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="partition" style="margin-top:5px" placeholder="1"/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{Master : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0040" style="margin-top:5px" placeholder="User 0040          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0001 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0001" style="margin-top:5px" placeholder="User 0001          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0002 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0002" style="margin-top:5px" placeholder="User 0002          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0003 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0003" style="margin-top:5px" placeholder="User 0003          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0004 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0004" style="margin-top:5px" placeholder="User 0004          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0005 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0005" style="margin-top:5px" placeholder="User 0005          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0006 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0006" style="margin-top:5px" placeholder="User 0006          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0007 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0007" style="margin-top:5px" placeholder="User 0007          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0008 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0008" style="margin-top:5px" placeholder="User 0008          "/>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-4 control-label">{{User 0009 : }}</label>
        <div class="col-lg-4">
          <input id="manual_port" class="configKey form-control" data-l1key="user0009" style="margin-top:5px" placeholder="User 0009          "/>
        </div>
      </div>
      
      <script>


      function dsc_postSaveConfiguration(){
        $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "plugins/dsc/core/ajax/dsc.ajax.php", // url du fichier php
        data: {
          action: "postSave",
        },
        dataType: 'json',
        error: function (request, status, error) {
          handleAjaxError(request, status, error);
        },
        success: function (data) { // si l'appel a bien fonctionné
        if (data.state != 'ok') {
          $('#div_alert').showAlert({message: data.result, level: 'danger'});
          return;
        }
      }
    });
  }


  </script>
</div>
</fieldset>
</form>
