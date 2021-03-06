var G = {};
var local = 0;
var intervalcputime;
var intervalscan;
var page = location.href.split( '=' ).pop();
if ( page === 'credits' ) { // no script file to get reboot data for credits page
	$.post( 'commands.php', { bash: 'cat /srv/http/data/tmp/reboot' }, function( reboot ) {
		G.reboot = reboot !== -1 ? reboot[ 0 ].split( '\n' ) : [];
	}, 'json' );
}
$( '#close' ).click( function() {
	if ( G.reboot.length ) {
		var cmdpower = [ 'rm -f /srv/http/data/tmp/reboot' ];
		if ( $( '#gpio' ).length ) cmdpower.push( '/usr/local/bin/gpiooff.py' );
		cmdpower.push(
			  '/usr/local/bin/ply-image /srv/http/assets/img/splash.png'
			, 'mount | grep -q /mnt/MPD/NAS && umount -l /mnt/MPD/NAS/* &> /dev/null && sleep 3'
			, 'rm -f /srv/http/data/tmp/*'
			, 'shutdown -r now'
		);
		info( {
			  icon    : 'sliders'
			, title   : 'System Setting'
			, message : 'Reboot required for:'
					   +'<br><br><w>'+ G.reboot.join( '<br>' ) +'</w>'
			, cancel  : function() {
				G.reboot = [];
				$.post( 'commands.php', { bash: 'rm -f /srv/http/data/tmp/*' } );
			}
			, ok      : function() {
				$.post( 'commands.php', { bash: cmdpower } );
				notify( 'Rebooting ...', '', 'reboot blink', -1 );
			}
		} );
	} else {
		if ( page === 'system' ) {
			$.post( 'commands.php', { bash: 'rm -f /srv/http/data/tmp/backup.xz' } );
		} else if ( page === 'network' ) {
			if ( $( '#listinterfaces li' ).hasClass( 'bt' ) ) $.post( 'commands.php', { bash: 'bluetoothctl scan off' } );
		}
		location.href = '/';
	}
} );
$( '.page-icon' ).click( function() {
	location.reload();
} );
$( '#help' ).click( function() {
	var eltop = $( 'heading' ).filter( function() {
		return this.getBoundingClientRect().top > 0
	} )[ 0 ]; // return 1st element
	var offset0 = eltop.getBoundingClientRect().top;
	$( this ).toggleClass( 'blue' );
	$( '.help-block' ).toggleClass( 'hide' );
	$( window ).scrollTop( eltop.offsetTop - offset0 );
} );

onVisibilityChange( function( visible ) {
	if ( page === 'credits' ) return
	
	if ( visible ) {
		refreshData();
	} else {
		if ( page === 'network' ) {
			clearInterval( intervalscan );
		} else if ( page === 'system' ) {
			clearInterval( intervalcputime );
			$( '#refresh i' ).removeClass( 'blink' );
		}
	}
} );
var pushstream = new PushStream( { modes: 'websocket' } );
var streams = [ 'refresh', 'reload', 'restore', ];
streams.forEach( function( stream ) {
	pushstream.addChannel( stream );
} );
pushstream.connect();
pushstream.onstatuschange = function( status ) {
	if ( status === 2 ) {
		if ( !$.isEmptyObject( G ) ) {
			$( '#loader' ).addClass( 'hide' );
			refreshData();
		}
	} else {
		$( '#loader' ).removeClass( 'hide' );
		bannerHide();
	}
}
pushstream.onmessage = function( data, id, channel ) {
	if ( local ) return
	
	switch( channel ) {
		case 'ip':      psIp( data );      break;
		case 'refresh': psRefresh( data ); break;
		case 'reload':  psReload();        break;
		case 'restore': psRestore( data ); break;
	}
}
function psRefresh( data ) {
	if ( data.page === page ) refreshData();
}
function psReload() {
	if ( [ 'localhost', '127.0.0.1' ].indexOf( location.hostname ) !== -1 ) location.reload();
}
function psRestore( data ) {
	if ( data.restore === 'reload' ) {
		location.reload();
	} else if ( data.restore === 'done' ) {
		$( '#loader' ).addClass( 'hide' );
		notify( 'Restore Settings', 'Done', 'sliders' );
	} else {
		$( '#loader' ).removeClass( 'hide' );
		banner( 'Restore Settings', 'Restart '+ data.restore +' ...', 'sliders blink', -1 );
	}
}

function banner( title, message, icon ) {
	if ( typeof message === 'boolean' || typeof message === 'number' ) var message = message ? 'Enable ...' : 'Disable ...';
	notify( title, message, icon +' blink', -1 );
}
function curlPage( page ) {
	return 'curl -s -X POST "http://127.0.0.1/pub?id=refresh" -d \'{ "page": "'+ page +'" }\''
}
function onVisibilityChange( callback ) {
    var visible = 1;
    function focused() {
        if ( !visible ) callback( visible = 1 );
    }
    function unfocused() {
        if ( visible ) callback( visible = 0 );
    }
    document.addEventListener( 'visibilitychange', function() {
		document.hidden ? unfocused() : focused();
	} );
    window.onpageshow = window.onfocus = focused;
    window.onpagehide = window.onblur = unfocused;
}
function resetlocal( ms ) {
	local = 0;
	setTimeout( function() {
		$( '#bannerTitle i' ).removeClass( 'blink' );
		$( '#bannerMessage' ).text( 'Done' );
	}, ms ? ms -2000 : 0 );
	setTimeout( bannerHide, ms || 2000 );
}
function showContent() {
	setTimeout( function() {
		$( '#loader' ).addClass( 'hide' );
		$( '.head, .container' ).removeClass( 'hide' );
	}, 300 );
}
if ( page === 'credits' ) showContent();
