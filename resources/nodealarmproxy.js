// Override de la fonction console.log
(function() {
  if (console.log) {
    var old = console.log;
    console.log = function() {
      function pad(s) {
        return (s<10)?'0'+s:s;
      }
      
      date = new Date();
      dateStr = [date.getFullYear(), pad(date.getMonth()+1),pad(date.getDate())].join('-') + ' ' + [pad(date.getHours()), pad(date.getMinutes()), pad(date.getSeconds())].join(':');
      Array.prototype.unshift.call(arguments, '[' + dateStr + ']' + '[NODE]');
      old.apply(this, arguments)
    }
  }  
})();

var net = require('net');
var elink = require('./envisalink.js');
var events = require('events');
var eventEmitter = new events.EventEmitter();
//var config = require('./config.js');
var connections = [];
var alarmdata = {
	zone:{},
	partition:{},
	user:{}
};

var actual, server, config;

exports.initConfig = function(initconfig) {

	config = initconfig;
	if (!config.actualport) {
		config.actualport = 4025;
	}
	if (!config.proxyenable) {
		config.proxyenable = false;
	}

	actual = net.connect({port: config.actualport, host:config.actualhost}, function() {
		console.log('actual connected');
	});

	if (config.proxyenable) {
		if (!config.serverport) {
			config.serverport = 4025;
		}
		if (!config.serverhost) {
			config.serverhost = '0.0.0.0';
		}
		if (!config.serverpassword) {
			config.serverpassword = config.actualpassword;
		}
		var server = net.createServer(function(c) { //'connection' listener
			console.log('server connected');
			connections.push(c);

			c.on('end', function() {
				var index = connections.indexOf(c);
				if ( ~index ) connections.splice(index,1);
				console.log('server disconnected:',connections);
			});

			c.on('data', function(data) {
				//console.log(data.toString());
				var dataslice = data.toString().replace(/[\n\r]/g, ',').split(',');

				for (var i = 0; i < dataslice.length; i++) {
					var rec = elink.applicationcommands[dataslice[i].substring(0,3)];
					if (rec) {
						if (rec.bytes === '' || rec.bytes === 0) {
							console.log(rec.pre,rec.post);
						} else {
							console.log(rec.pre,dataslice[i].substring(3,dataslice[i].length-2),rec.post);
						}
						if (rec.action == 'checkpassword') {
							checkpassword(c,dataslice[i]);
						}
						console.log(rec.action);
						if (rec.action == 'forward') {
							sendforward(dataslice[i].substring(0,dataslice[i].length-2));
						}
						sendcommand(c,rec.send);
					}
				}
			});

			c.write('505300');
			c.pipe(c);
		});
		server.listen(config.serverport,config.serverhost, function() { //'listening' listener
			console.log('server bound');
		});

		var checkpassword = function (c,data) {
			if (data.substring(3,data.length-2) == config.serverpassword) {
				console.log('Correct Password! :)');
				sendcommand(c,'5051');
			} else {
				console.log('Incorrect Password :(');
				sendcommand(c,'5050');
				c.end();
			}
		};

		var sendforward = function (data) {
			console.log('sendforward:',data);
			sendcommand(actual,data);
		};

		var broadcastresponse = function (response) {
			if (connections.length > 0) {
				for (var i = 0; i<connections.length; i++) {
					sendcommand(connections[i],response);
				}
			}
		};
	}

	function loginresponse(data) {
		var loginStatus = data.substring(3, 4);
		if (loginStatus == '0') {
			console.log('Incorrect Password :(');
		} else if (loginStatus == '1') {
			console.log('successfully logged in!  getting current data...');
			sendcommand(actual,'001');
		} else if (loginStatus == '2') {
			console.log('Request for Password Timed Out :(');
		} else if (loginStatus == '3') {
			console.log('login requested... sending response...');
			sendcommand(actual,'005'+config.password);
		}
	}

	function updatezone(tpi,data) {
    var Intcode = parseInt(data.substring(0,3));
		var Intzone = parseInt(data.substring(3,6));
    
    console.log('updatezone:',tpi.pre,data.substring(3,data.length-2),tpi.post,'(' + data + ')');

		if (Intzone <= config.zone) {
      if (typeof alarmdata.zone[Intzone] === "undefined" || alarmdata.zone[Intzone].code != data) {
			  alarmdata.zone[Intzone] = {'send':tpi.send,'name':tpi.name,'code':data};
        requestData.addRequest({'messageType':'zone','id':Intzone,'value':Intcode,'eventDesc':tpi.pre + " " + tpi.post});
		  }
	  }
  }

	function updatepartition(tpi,data) {
    var Intcode =       parseInt(data.substring(0,3));
    var Intpartition =  parseInt(data.substring(3,4));
    
    console.log('updatepartition:',tpi.pre,Intpartition,tpi.post,data.substring(4,data.length-2),'(' + data + ')');    

    if (Intpartition <= config.partition) {
      if (typeof alarmdata.partition[Intpartition] === "undefined" || alarmdata.partition[Intpartition].code != data) {
        alarmdata.partition[Intpartition] = {'send':tpi.send,'name':tpi.name,'code':data};     
        requestData.addRequest({'messageType':'partition','id':Intpartition,'value':Intcode,'eventCode':data.substring(4,5),'eventDesc':tpi.pre + " " + tpi.post});
      }
    }
	}

	function updatepartitionuser(tpi,data,Intpartition) {
    var Intcode =       parseInt(data.substring(0,3));
    var Intpartition =  parseInt(data.substring(3,4));
		var Intuser =       parseInt(data.substring(4,8));
    
    console.log('updatepartitionuser: Partition ',Intpartition,tpi.post,data.substring(4,data.length-2),'(' + data + ')');
    
    if (Intpartition <= config.partition) {
      if (typeof alarmdata.user[Intuser] === "undefined" || alarmdata.user[Intuser].code != data) {
        alarmdata.user[Intuser] = {'send':tpi.send,'name':tpi.name,'code':data};
        requestData.addRequest({'messageType':'partitionuser','id':Intpartition,'value':Intcode,'user':Intuser,'eventDesc':tpi.pre + " " + tpi.post});
      }
    }
	}

	function updatesystem(tpi,data) {
    var Intcode =       parseInt(data.substring(0,3));
    var Intpartition =  parseInt(data.substring(3,4));
    
    console.log('updatesystem:',tpi.pre,tpi.post,'(' + data + ')');
    
    if (data.length < 4 || Intpartition !== parseInt(Intpartition, 10) || Intpartition <= config.partition) {
      if (typeof alarmdata.system === "undefined" || alarmdata.system.code != data) {
        alarmdata.system =  {'send':tpi.send,'name':tpi.name,'code':data};
        requestData.addRequest({'messageType':'system','value':Intcode,'eventDesc':tpi.pre + " " + tpi.post});
      }
    }
	}

	function updatetrouble(tpi,data) {
    var Intcode =     parseInt(data.substring(0,3));
		var IntHexData =  parseInt(data.substring(3,5));
    
    console.log('updatetrouble => ','pre::',tpi.pre,'data::',data.substring(3,data.length-2),'post::',tpi.post,'(' + data + ')');
    
		for (var i = 0; i < 7; i++) {
			var Bit = IntHexData & Math.pow(2,i);
			if (Bit != 0 && typeof requestData !== "undefined") {
        if (typeof alarmdata.trouble === "undefined" || alarmdata.trouble.code != i) {
          alarmdata.trouble =  {'send':tpi.send,'name':tpi.name,'code':i};
          requestData.addRequest({'messageType':'trouble','value':Intcode,'eventCode':i,'eventDesc':elink.tpitrouble[Intcode][i].desc});
        }
			}
		}
	}
  
  function coderequired(tpi,data) {
    var Intcode =     parseInt(data.substring(0,3));
    
    console.log('updatesystem:',tpi.pre,tpi.post,'(' + data + ')');
    
    if (typeof alarmdata.system === "undefined" || alarmdata.system.code != data) {
      alarmdata.system = {'send':tpi.send,'name':tpi.name,'code':data};
      //if (typeof requestData !== "undefined") {
        requestData.addRequest({'messageType':'system','value':Intcode,'eventDesc':tpi.pre + " " + tpi.post});
      //}
    }
  }  
  
  function forward(tpi,data) {
    var Intcode =     parseInt(data.substring(0,3));

    console.log('updatesystem:',tpi.pre,tpi.post,'(' + data + ')');
    
    if (typeof alarmdata.system === "undefined" || alarmdata.system.code != data) {
      alarmdata.system = {'send':tpi.send,'name':tpi.name,'code':data};
      //if (typeof requestData !== "undefined") {
        requestData.addRequest({'messageType':'system','value':Intcode,'eventDesc':tpi.pre + " " + tpi.post});
      //}
    }
  }  

	actual.on('data', function(data) {
		dataString = data.toString().replace(/[\n\r]/g, ',');
    dataString = dataString.replace(/,,/g, ',');
    
    var dataslice = dataString.split(',');
    
		for (var i = 0; i<dataslice.length; i++) {
			var datapacket = dataslice[i];
			if (datapacket !== '') {
				var tpi = elink.tpicommands[datapacket.substring(0,3)];
				if (tpi) {

					if (tpi.action === 'updatezone') {
						updatezone(tpi,datapacket);
					}
					else if (tpi.action === 'updatepartition') {
						updatepartition(tpi,datapacket);
					}
					else if (tpi.action === 'updatepartitionuser') {
						updatepartitionuser(tpi,datapacket);
					}
					else if (tpi.action === 'updatesystem') {
            updatesystem(tpi,datapacket);
					}
          else if (tpi.action === 'updatetrouble') {
            updatetrouble(tpi,datapacket);      
          }
          else if (tpi.action === 'coderequired') {
            coderequired(tpi,datapacket);      
          }
          else if (tpi.action === 'forward') {
            forward(tpi,datapacket);      
          }
					else if (tpi.action === 'loginresponse') {
						loginresponse(datapacket);
					}
            
					if (config.proxyenable) {
						broadcastresponse(datapacket.substring(0,datapacket.length-2));
					}
				}
			}
		}
		//actual.end();
	});
	actual.on('end', function() {
		console.log('actual disconnected');
	});

	return eventEmitter;
};

function sendcommand(addressee,command) {
	var checksum = 0;
	for (var i = 0; i<command.length; i++) {
		checksum += command.charCodeAt(i);
	}
	checksum = checksum.toString(16).slice(-2);
	addressee.write(command+checksum+'\r\n');
}

exports.manualCommand = function(command) {
	if (actual) {
		sendcommand(actual,command);
	} else {
		//not initialized
	}
};

exports.getCurrent = function() {
	eventEmitter.emit('data',alarmdata);
};
