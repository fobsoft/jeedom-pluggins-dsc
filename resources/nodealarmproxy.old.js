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

Date.prototype.getFullDay = function () {
   if (this.getDate() < 10) {
       return '0' + this.getDate();
   }
   return this.getDate();
};

Date.prototype.getFullMonth = function () {
   t = this.getMonth() + 1;
   if (t < 10) {
       return '0' + t;
   }
   return t;
};

Date.prototype.getFullHours = function () {
   if (this.getHours() < 10) {
       return '0' + this.getHours();
   }
   return this.getHours();
};

Date.prototype.getFullMinutes = function () {
   if (this.getMinutes() < 10) {
       return '0' + this.getMinutes();
   }
   return this.getMinutes();
};

Date.prototype.getFullSeconds = function () {
   if (this.getSeconds() < 10) {
       return '0' + this.getSeconds();
   }
   return this.getSeconds();
};

function LogDate(Type, Message) {
   var ceJour = new Date();
//       var ceJourJeedom = ceJour.getDate() + "/" + ceJour.getMonth() + "/" + ceJour.getFullYear() + " " + ceJour.getHours() + ":" + ceJour.getMinutes() + ":" + ceJour.getSeconds();
       var ceJourJeedom = ceJour.getFullDay() + "-" + ceJour.getFullMonth() + "-" + ceJour.getFullYear() + " " + ceJour.getFullHours() + ":" + ceJour.getFullMinutes() + ":" + ceJour.getFullSeconds();
       console.log(ceJourJeedom + " | " + Type + " | " + Message);
}

exports.initConfig = function(initconfig) {

	config = initconfig;
	if (!config.actualport) {
		config.actualport = 4025;
	}
	if (!config.proxyenable) {
		config.proxyenable = false;
	}

	actual = net.connect({port: config.actualport, host:config.actualhost}, function() {
		//console.log('actual connected');
		LogDate("debug", 'actual connected');
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
			//console.log('server connected');
			LogDate("debug", 'server connected');
			connections.push(c);

			c.on('end', function() {
				var index = connections.indexOf(c);
				if ( ~index ) connections.splice(index,1);
				//console.log('server disconnected:',connections);
				LogDate("debug", 'server connected : ' + connections);
			});

			c.on('data', function(data) {
				//console.log(data.toString());
				var dataslice = data.toString().replace(/[\n\r]/g, ',').split(',');

				for (var i = 0; i < dataslice.length; i++) {
					var rec = elink.applicationcommands[dataslice[i].substring(0,3)];
					if (rec) {
						if (rec.bytes === '' || rec.bytes === 0) {
							//console.log(rec.pre,rec.post);
							LogDate("debug", rec.pre + rec.post);
						} else {
							//console.log(rec.pre,dataslice[i].substring(3,dataslice[i].length-2),rec.post);
							LogDate("debug", rec.pre,dataslice[i].substring(3,dataslice[i].length-2),rec.post);
						}
						if (rec.action == 'checkpassword') {
							checkpassword(c,dataslice[i]);
						}
						//console.log(rec.action);
						LogDate("debug", rec.action);
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
			//console.log('server bound');
			LogDate("debug", 'server bound');
		});

		var checkpassword = function (c,data) {
			if (data.substring(3,data.length-2) == config.serverpassword) {
				//console.log('Correct Password! :)');
				LogDate("debug", 'Correct Password! :)');
				sendcommand(c,'5051');
			} else {
				//console.log('Incorrect Password :(');
				sendcommand(c,'5050');
				LogDate("debug", 'Incorrect Password :(');
				c.end();
			}
		};

		var sendforward = function (data) {
			//console.log('sendforward:',data);
			LogDate("debug", 'sendforward : ' + data);
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
			//console.log('Incorrect Password :(');
			LogDate("debug", 'Incorrect Password :(');
		} else if (loginStatus == '1') {
			//console.log('successfully logged in!  getting current data...');
			LogDate("debug", 'successfully logged in!  getting current data...');
			sendcommand(actual,'001');
		} else if (loginStatus == '2') {
			//console.log('Request for Password Timed Out :(');
			LogDate("debug", 'Request for Password Timed Out :(');
		} else if (loginStatus == '3') {
			//console.log('login requested... sending response...');
			LogDate("debug", 'login requested... sending response...');
			sendcommand(actual,'005'+config.password);
		}
	}

	function updatezone(tpi,data) {
		var zone = parseInt(data.substring(3,6));
		var initialUpdate = alarmdata.zone[zone] === undefined;
		if (zone <= config.zone) {
			alarmdata.zone[zone] = {'send':tpi.send,'name':tpi.name,'code':data};
			if (config.atomicEvents && !initialUpdate) {
				//eventEmitter.emit('zoneupdate', [zone, alarmdata.zone[zone]]);
				eventEmitter.emit('zoneupdate',{zone:parseInt(data.substring(3,6)),code:data.substring(0,3)});
			} else {
				eventEmitter.emit('data',alarmdata);
			}
		}
	}
	function updatepartition(tpi,data) {
		var partition = parseInt(data.substring(3,4));
		var initialUpdate = alarmdata.partition[partition] === undefined;
		if (partition <= config.partition) {
			alarmdata.partition[partition] = {'send':tpi.send,'name':tpi.name,'code':data};
			if (config.atomicEvents && !initialUpdate) {
				//eventEmitter.emit('partitionupdate', [partition, alarmdata.partition[partition]]);
				if (data.substring(0,3) == "652") {
						eventEmitter.emit('partitionupdate',{partition:parseInt(data.substring(3,4)),code:data.substring(0,3),mode:data.substring(4,5)});
				} else {
					eventEmitter.emit('partitionupdate',{partition:parseInt(data.substring(3,4)),code:data.substring(0,3)});
				}
			} else {
				eventEmitter.emit('data',alarmdata);
			}
		}
	}
	function updatepartitionuser(tpi,data) {
		var partition = parseInt(data.substring(3,4));
		var user = parseInt(data.substring(4,8));
		var initialUpdate = alarmdata.user[user] === undefined;
		if (partition <= config.partition) {
			alarmdata.user[user] = {'send':tpi.send,'name':tpi.name,'code':data};
			if (config.atomicEvents && !initialUpdate) {
				eventEmitter.emit('partitionuserupdate', [user, alarmdata.user[user]]);
			} else {
				eventEmitter.emit('data',alarmdata);
			}
		}
	}
	function updatesystem(tpi,data) {
		var partition = parseInt(data.substring(3,4));
		var initialUpdate = alarmdata.system === undefined;
		if (partition <= config.partition) {
			alarmdata.system = {'send':tpi.send,'name':tpi.name,'code':data};
			if (config.atomicEvents && !initialUpdate) {
				eventEmitter.emit('systemupdate', alarmdata.system);
			} else {
				eventEmitter.emit('data',alarmdata);
			}
		}
	}

	actual.on('data', function(data) {
		var dataslice = data.toString().replace(/[\n\r]/g, ',').split(',');

		for (var i = 0; i<dataslice.length; i++) {
			var datapacket = dataslice[i];
			if (datapacket !== '') {
				var tpi = elink.tpicommands[datapacket.substring(0,3)];
				if (tpi) {
					if (tpi.bytes === '' || tpi.bytes === 0) {
						//console.log(tpi.pre,tpi.post);
						LogDate("debug", tpi.pre + ' ' + tpi.post);
					} else {
						//console.log(tpi.pre,datapacket.substring(3,datapacket.length-2),tpi.post);
						LogDate("debug", tpi.pre + ' ' + datapacket.substring(3,datapacket.length-2) + ' ' + tpi.post);
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
							updatepartitionuser(tpi,datapacket);
						}
						else if (tpi.action === 'loginresponse') {
							loginresponse(datapacket);
						}
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
		//console.log('actual disconnected');
		LogDate("debug", 'actual disconnected');
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
