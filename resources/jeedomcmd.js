var nap = require('./nodealarmproxy.js');

var password = '';
var port = '';
var zone = '';
var partition = '';
var command = '';

// print process.argv
process.argv.forEach(function(val, index, array) {

	switch ( index ) {
		case 2 : password = val; break;
		case 3 : port = val; break;
		case 4 : zone = val; break;
    case 5 : partition = val; break;
		case 6 : command = val; break;
	}

});

var alarm = nap.initConfig({ password:password,
	actualhost:'127.0.0.1',
	actualport:port,
	zone:zone,
	partition:partition,
});

//nap.getCurrent(function(alarmdata){ console.log(alarmdata); });

nap.manualCommand(command,function(){
	 		console.log('command send ', command);
});
