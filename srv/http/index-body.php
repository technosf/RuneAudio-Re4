<?php
include 'logosvg.php';

if ( $login && !$_SESSION[ 'login' ] ) {
?>
<div style="text-align: center">
	<svg viewBox="0 0 480.2 144.2" style="margin: 100px auto 20px; width: 200px;"><?=$logo ?></svg><br>
	<input type="password" id="pwd">
	<a id="login" class="btn btn-primary">Login</a>
</div>
<script src="assets/js/plugin/jquery-2.2.4.min.js"></script>
<script src="assets/js/info.<?=$time?>.js"></script>
<script>
$( '#pwd' ).focus();
$( '#login' ).click( function() {
	$.post( 'commands.php', { login: $( '#pwd' ).val() }, function( data ) {
		data ? location.reload() : info( 'Wrong password' );
	} );
} );
$( '#pwd' ).keypress( function( e ) {
	if ( e.which == 13 ) $( '#login' ).click();
});
</script>

</body>
</html>
<?php
	exit;
}
$color = file_exists( '/srv/http/data/system/color' );
$submenupower = in_array( $_SERVER[ 'REMOTE_ADDR' ], [ '127.0.0.1', '::1' ] ) ? '<i class="fa fa-screenoff submenu"></i>' : '';
// counts
$counts = exec( '/srv/http/bash/mpdcount.sh' );
$counts = json_decode( $counts );
// library home blocks
$modes = [ 'CoverArt', 'SD', 'USB', 'NAS', 'WebRadio', 'Album', 'Artist', 'AlbumArtist', 'Composer', 'Genre', 'Date' ];
$modehtml = '';
foreach( $modes as $mode ) {
    $modeLC = strtolower( $mode );
	$modehtml.= '
		<div class="lib-mode">
			<div id="mode-'.$modeLC.'" class="mode" data-mode="'.$modeLC.'">
				<a class="lipath">'.$mode.'</a>
				<i class="fa fa-'.$modeLC.'"></i>
				'.( $counts->$modeLC ? '<grl>'.number_format( $counts->$modeLC ).'</grl>' : '<grl></grl>' ).'
				<a class="label">'.$mode.'</a>
			</div>
		</div>
	';
}
// bookmarks
$dir = '/srv/http/data/bookmarks';
$files = file_exists( $dir ) ? array_slice( scandir( $dir ), 2 ) : []; // remove ., ..
if ( count( $files ) ) {
	foreach( $files as $file ) {
		$content = file_get_contents( "$dir/$file" );
		if ( substr( $content, 0, 10 ) === 'data:image' ) {
			$iconhtml = '<img class="bkcoverart" src="'.$content.'">';
		} else {
			$iconhtml = '<i class="fa fa-bookmark"></i>'
					   .'<div class="divbklabel"><span class="bklabel label">'.$content.'</span></div>';
		}
		$modehtml.= '
			<div class="lib-mode bookmark">
				<div class="mode mode-bookmark" data-mode="bookmark">
					<a class="lipath">'.str_replace( '|', '/', $file ).'</a>
					'.$iconhtml.'
				</div>
			</div>
		';
	}
}
// browse by coverart
$coverartshtml = '';
$files = array_slice( scandir( '/srv/http/data/coverarts' ), 2 );
if ( count( $files ) ) {
	$thumbbyartist = exec( 'grep -q "thumbbyartist.*true" /srv/http/data/system/display && echo 1 || echo 0' );
	foreach( $files as $file ) {
		$name = substr( $file, 0, -4 );
		$ext = substr( $file, -3 );
		$filename = "$name.$time.$ext";
		// restore /, #, ? replaced by scan.sh
		$name = preg_replace( [ '/\|/', '/{/', '/}/' ], [ '/', '#', '?' ], $name );
		$names = explode( '^^', $name );
		$album = $names[ 0 ];
		$artist = $names[ 1 ];
		$sortalbum = stripLeading( $album );
		$sortartist = stripLeading( $artist );
		$path = isset( $names[ 2 ] ) ? $names[ 2 ] : '';
		if ( $thumbbyartist ) {
			$lists[] = [ $sortartist, $sortalbum, $artist, $album, $filename, $path ];
			$indexes[] = mb_substr( $sortartist, 0, 1, 'UTF-8' );
		} else {
			$lists[] = [ $sortalbum, $sortartist, $album, $artist, $filename, $path ];
			$indexes[] = mb_substr( $sortalbum, 0, 1, 'UTF-8' );
		}
	}
	usort( $lists, function( $a, $b ) {
		return strnatcmp( $a[ 0 ], $b[ 0 ] ) ?: strnatcmp( $a[ 1 ], $b[ 1 ] );
	} );
	$indexes = array_keys( array_flip( $indexes ) );
	$indexarray = range( 'A', 'Z' );
	$indexbar = '<a>#</a>';
	foreach( $indexarray as $i => $char ) {
		$white = in_array( $char, $indexes ) ? 'wh' : '';
		$half = $i % 2 ? ' half' : '';
		$indexbar.= '<a class="'.$white.$half.'">'.$char."</a>\n";
	}
	foreach( $lists as $list ) {
		$lipath = '<a class="lipath">'.$list[ 5 ].'</a>';
		$coverfile = preg_replace( [ '/%/', '/"/', '/#/' ], [ '%25', '%22', '%23' ], $list[ 4 ] );
		// leading + trailing quotes in the same line avoid spaces between divs
		$coverartshtml.= '<div class="coverart" data-index="'.$list[ 0 ][ 0 ].'">
							'.$lipath.'
							<div><img class="lazy" data-src="/data/coverarts/'.$coverfile.'"></div>
							<span class="coverart1">'.$list[ 2 ].'</span>
							<gr class="coverart2">'.( $list[ 3 ] ?: '&nbsp;' ).'</gr>
						</div>';
	}
	$coverartshtml.= '<div id="indexcover">'.$indexbar.'</div>'
					.'<p class="coverlist"></p>';
}
function stripLeading( $string ) {
	$names = strtoupper( strVal( $string ) );
	return preg_replace(
		  [
			'/^A\s+|^AN\s+|^THE\s+|[^\w\p{L}\p{N}\p{Pd} ~]/u',
			'/\s+|^_/'
		]
		, [
			'',  // strip articles | non utf-8 normal alphanumerics | tilde(blank data)
			'-'  // fix: php strnatcmp ignores spaces | sort underscore to before 0
		]
		, $names
	);
}
// context menus
function menuli( $command, $icon, $label, $type = '' ) {
	$iconclass = [ 'refresh-library', 'tag', 'minus-circle', 'lastfm' ];
	$class = in_array( $icon, $iconclass ) ? ' class="'.$icon.'"' : '';
	$submenu = in_array( $label, [ 'Add', 'Random', 'Replace', 'Add similar' ] ) ? '<i class="fa fa-play-plus submenu"></i>' : '';
	return '<a data-cmd="'.$command.'"'.$class.'><i class="fa fa-'.$icon.'"></i>'.$label.$submenu.'</a>';
}
function menudiv( $id, $html ) {
	return '<div id="menu-'.$id.'" class="menu contextmenu hide">'.$html.'</div>';
}
function menucommon( $add, $replace ) {
	$htmlcommon = '<span class="menushadow"></span>';
	$htmlcommon.= '<a data-cmd="'.$add.'"><i class="fa fa-plus-o"></i>Add<i class="fa fa-play-plus submenu" data-cmd="'.$add.'play"></i></a>';
	$htmlcommon.= '<a data-cmd="'.$replace.'" class="replace"><i class="fa fa-replace"></i>Replace<i class="fa fa-play-replace submenu" data-cmd="'.$replace.'play"></i></a>';
	return $htmlcommon;
}

$kid3 = file_exists( '/usr/bin/kid3-cli' );

$menu = '<div id="contextmenu">';

$htmlcommon = menucommon( 'add', 'replace' );

$html = '<span class="menushadow"></span>';
$html.= menuli( 'play',       'play',         'Play' );
$html.= menuli( 'pause',      'pause',        'Pause' );
$html.= menuli( 'stop',       'stop',         'Stop' );
$html.= menuli( 'savedpladd', 'save-plus',    'Add to a playlist' );
$html.= menuli( 'remove',     'minus-circle', 'Remove' );
$html.= menuli( 'similar',    'lastfm',       'Add similar' );
$html.= menuli( 'tag',        'info-circle',  'Track Info' );
//$html.= menuli( 'saveradio',  'save',         'Save to WebRadio' );
$menu.= menudiv( 'plaction', $html );

$menudiv = '';
$html = $htmlcommon;
$html.= menuli( 'bookmark',  'star',            'Bookmark' );
$html.= menuli( 'exclude',   'folder-forbid',   'Exclude directory' );
$html.= menuli( 'update',    'refresh-library', 'Update database' );
$html.= menuli( 'thumbnail', 'coverart',        $counts->coverart ? 'Update thumbnails' : 'Create thumbnails' );
if ( $kid3 ) $html.= menuli( 'tag', 'tag', 'Tags' );
$menu.= menudiv( 'folder', $html );

$menudiv = '';
$html = menucommon( 'add', 'replace' );
$html.= menuli( 'similar', 'lastfm', 'Add similar' );
if ( $kid3 ) $html.= menuli( 'tag', 'tag', 'Tags' );
$menu.= menudiv( 'file', $html );

$menudiv = '';
$html = $htmlcommon;
$menu.= menudiv( 'filepl', $html );

$menudiv = '';
$html = $htmlcommon;
$html.= menuli( 'similar',       'lastfm',       'Add similar' );
$html.= menuli( 'savedplremove', 'minus-circle', 'Remove' );
if ( $kid3 ) $html.= menuli( 'tag', 'tag', 'Tags' );
$menu.= menudiv( 'filesavedpl', $html );

$menudiv = '';
$html = menucommon( 'add', 'replace' );
$menu.= menudiv( 'radio', $html );

$menudiv = '';
$html = menucommon( 'wradd', 'wrreplace' );
$html.= menuli( 'wredit',     'edit-circle',  'Edit' );
$html.= menuli( 'wrcoverart', 'coverart',     'Change coverart' );
$html.= menuli( 'wrdelete',   'minus-circle', 'Delete' );
$menu.= menudiv( 'webradio', $html );

$menudiv = '';
$html = '<span class="menushadow"></span>';
$html.= menucommon( 'pladd', 'plreplace' );
$html.= menuli( 'plrename', 'edit-circle',  'Rename' );
$html.= menuli( 'pldelete', 'minus-circle', 'Delete' );
$menu.= menudiv( 'playlist', $html );

$menudiv = '';
$html = menucommon( 'albumadd', 'albumreplace' );
$menu.= menudiv( 'album', $html );

$menudiv = '';
$html = menucommon( 'artistadd', 'artistreplace' );
$menu.= menudiv( 'artist', $html );

$menudiv = '';
$html = menucommon( 'composeradd', 'composerreplace' );
$menu.= menudiv( 'composer', $html );

$menudiv = '';
$html = menucommon( 'genreadd', 'genrereplace' );
$menu.= menudiv( 'genre', $html );

$menu.= '</div>';
$snapclient = file_exists( '/srv/http/data/system/snapclient' );
$addonsupdate = @file_get_contents( '/srv/http/data/addons/update' ) ?: false;
?>
<div id="splash"><svg viewBox="0 0 480.2 144.2"><?=$logo?></svg></div>
<div id="loader" class="hide"><svg viewBox="0 0 480.2 144.2"><?=$logo?></svg></div>
<div id="bar-top" class="hide">
	<a id="logo" href="http://www.runeaudio.com/forum/raspberry-pi-f7.html" target="_blank">
		<svg class="logo" viewBox="0 0 480.2 144.2"><?=$logo ?></svg>
	</a>
	<i id="button-settings" class="fa fa-gear"></i><?=( $addonsupdate ? '<span id="badge"></span>' : '' )?>
	<div id="playback-controls">
		<i id="previous" class="btn btn-default btn-cmd fa fa-previous"></i>
		<i id="stop" class="btn btn-default btn-cmd fa fa-stop"></i>
		<i id="play" class="btn btn-default btn-cmd fa fa-play"></i>
		<i id="pause" class="btn btn-default btn-cmd fa fa-pause"></i>
		<i id="next" class="btn btn-default btn-cmd fa fa-next"></i>
	</div>
</div>
<div id="settings" class="menu hide">
	<span class="menushadow"></span>
	<a id="mpd" class="settings"><i class="fa fa-mpd"></i>MPD<i id="update" class="fa fa-refresh-library submenu"></i></a>
	<a id="network" class="settings"><i class="fa fa-network"></i>Network</a>
	<a id="sources" class="settings"><i class="fa fa-folder-cascade"></i>Sources<?=( $snapclient ? '<i id="snapclient" class="fa fa-snapcast submenu"></i>' : '' )?></a>
	<a id="system" class="settings"><i class="fa fa-sliders"></i>System<i id="credits" class="fa fa-rune submenu"></i></a>
		<?php if ( $login ) { ?>
	<a id="logout"><i class="fa fa-lock"></i>Logout</a>
		<?php } ?>
	<a id="power"><i class="fa fa-power"></i>Power<?=$submenupower ?></a>
		<?php if ( file_exists( '/srv/http/gpiosettings.php' ) ) { ?>
	<a id="gpio"><i class="fa fa-gpio"></i>GPIO<i class="fa fa-gear submenu"></i></a>
		<?php }
			  if ( file_exists( '/srv/http/aria2' ) ) {
					$ariaenable = exec( '/usr/bin/systemctl is-enabled aria2 &> /dev/null && echo 1 || echo 0' );
					$ariaactive = exec( '/usr/bin/systemctl is-active aria2 &> /dev/null && echo 1 || echo 0' ); ?>
	<a id="aria2" class="pkg" data-enabled="<?=$ariaenable?>" data-active="<?=$ariaactive?>">
		<img src="/assets/img/addons/thumbaria.<?=$time?>.png" class="iconimg<?=( $ariaactive ? ' on' : '' )?>">Aria2
		<i class="fa fa-gear submenu imgicon"></i>
	</a>
		<?php }
			  if ( file_exists( '/etc/systemd/system/transmission.service.d' ) ) {
					$tranenable = exec( '/usr/bin/systemctl is-enabled transmission &> /dev/null && echo 1 || echo 0' );
					$tranactive = exec( '/usr/bin/systemctl is-active transmission &> /dev/null && echo 1 || echo 0' ); ?>
	<a id="transmission" class="pkg" data-enabled="<?=$tranenable?>" data-active="<?=$tranactive?>">
		<img src="/assets/img/addons/thumbtran.<?=$time?>.png" class="iconimg<?=( $tranactive ? ' on' : '' )?>">Transmission
		<i class="fa fa-gear submenu imgicon"></i>
	</a>
		<?php } ?>
	<a id="displaylibrary"><i class="fa fa-library"></i>Library<i id="displaylibrary2" class="fa fa-gear submenu"></i></a>
	<a id="displayplayback"><i class="fa fa-play-circle"></i>Playback<i class="submenu"><canvas id="iconrainbow"></i></a>
	<a id="addons"><i class="fa fa-addons"></i><?=( $addonsupdate ? '<span id="badgeaddons">'.$addonsupdate.'</span>' : '' )?>Addons<i class="fa fa-question-circle submenu"></i></a>
</div>

<div id="page-playback" class="page">
	<div class="emptyadd hide"><i class="fa fa-plus-circle"></i></div>
	<i id="guide-bio" class="map guide fa fa-bio"></i>
	<i id="guide-lyrics" class="map guide fa fa-lyrics"></i>
	<i id="guide-album" class="map guide fa fa-album"></i>
	<div id="info">
		<div id="divartist">
			<span id="artist"></span>
		</div>
		<div id="divsong">
			<span id="song"></i></span>
		</div>
		<div id="divalbum">
			<span id="album"></span>
		</div>
		<div id="infoicon">
			<i id="i-airplay" class="fa fa-airplay wh hide"></i>
			<i id="i-snapclient" class="fa fa-snapclient wh hide"></i>
			<i id="i-spotify" class="fa fa-spotify wh hide"></i>
			<i id="i-upnp" class="fa fa-upnp wh hide"></i>
			<i id="i-webradio" class="fa fa-webradio wh hide"></i>
			<span id="progress"></span>
			<span id="modeicon">
				<i id="i-random" class="fa fa-random hide"></i>
				<i id="i-repeat" class="fa fa-repeat hide"></i>
				<i id="i-repeat1" class="fa fa-repeat-single hide"></i>
				<i id="i-consume" class="fa fa-flash hide"></i>
				<i id="i-librandom" class="fa fa-dice hide"></i>
				<i id="i-update" class="fa fa-library blink hide"></i>
				<i id="i-addons" class="fa fa-addons hide"></i>
				<i id="i-gpio" class="fa fa-gpio hide"></i>
			</span>
		</div>
		<div id="sampling"></div>
	</div>
	<div id="playback-row" class="row">
		<div id="time-knob">
			<div id="time"></div>
			<div id="timeicon">
				<i id="ti-random" class="fa fa-random hide"></i>
				<i id="ti-repeat" class="fa fa-repeat hide"></i>
				<i id="ti-repeat1" class="fa fa-repeat-single hide"></i>
				<i id="ti-consume" class="fa fa-flash hide"></i>
				<i id="ti-librandom" class="fa fa-dice hide"></i>
				<i id="ti-update" class="fa fa-library blink hide"></i>
				<i id="ti-addons" class="fa fa-addons hide"></i>
				<i id="ti-gpio" class="fa fa-gpio hide"></i>
			</div>
			<span id="elapsed" class="controls1"></span>
			<span id="total" class="controls1"></span>
			<div id="timemap">
				<i id="timeTL" class="map timemap fa fa-coverart"></i>
				<i id="timeT" class="map timemap fa fa-guide"></i>
				<i id="timeTR" class="map timemap fa fa-gear"></i>
				<i id="timeL" class="map timemap fa fa-previous"></i>
				<div id="timeM" class="map timemap"><i class="fa fa-play"></i>&nbsp;<i class="fa fa-pause"></i></div>
				<i id="timeR" class="map timemap fa fa-next"></i>
				<i id="timeBL" class="map timemap fa fa-random"></i>
				<i id="timeB" class="map timemap fa fa-stop"></i>
				<i id="timeBR" class="map timemap fa fa-repeat"></i>
			</div>
			<div id="play-group">
				<div class="btn-group">
					<i id="repeat" class="btn btn-default btn-cmd btn-toggle fa fa-repeat"></i>
					<i id="random" class="btn btn-default btn-cmd btn-toggle fa fa-random"></i>
					<i id="single" class="btn btn-default btn-cmd btn-toggle fa fa-single"></i>
				</div>
			</div>
		</div>
		<div id="coverart-block">
			<div id="divcover" class="cover">
				<div id="time-bar"></div>
				<div id="time-band" class="band transparent"></div>
				
				<img id="coverart" class="cover hide">
				<div id="covermap">
					<i id="coverTL" class="map covermap r1 c1 ws hs fa fa-scale-dn"></i>
					<i id="coverT" class="map covermap r1 c2 wl hs fa fa-guide"></i>
					<i id="coverTR" class="map covermap r1 c3 ws hs fa fa-gear"></i>
					<i id="coverL" class="map covermap r2 c1 ws hl fa fa-previous"></i>
					<div id="coverM" class="map covermap r2 c2 wl hl"><i class="fa fa-play"></i>&emsp;<i class="fa fa-pause"></i></div>
					<i id="coverR" class="map covermap r2 c3 ws hl fa fa-next"></i>
					<i id="coverBL" class="map covermap r3 c1 ws hs fa fa-random"></i>
					<i id="coverB" class="map covermap r3 c2 wl hs fa fa-stop"></i>
					<i id="coverBR" class="map covermap r3 c3 ws hs fa fa-repeat"></i>
				</div>
				<div id="volume-text" class="hide"></div>
				<div id="volume-bar" class="hide"></div>
				<i id="volume-band" class="volumeband band fa fa-volume transparent"></i>
				<i id="volume-band-dn" class="volumeband band fa fa-minus transparent"></i>
				<i id="volume-band-up" class="volumeband band fa fa-plus transparent"></i>
			</div>
		</div>
		<div id="volume-knob">
			<div id="volume"></div>
			<div id="volmap">
				<i id="volT" class="map volmap fa fa-plus"></i>
				<i id="volL" class="map volmap fa fa-minus"></i>
				<i id="volM" class="map volmap fa fa-volume"></i>
				<i id="volR" class="map volmap fa fa-plus"></i>
				<i id="volB" class="map volmap fa fa-minus"></i>
			</div>
			<div id="vol-group">
				<div class="btn-group">
					<i id="voldn" class="btn btn-default fa fa-minus"></i>
					<i id="volmute" class="btn btn-default fa fa-volume"></i>
					<i id="volup" class="btn btn-default fa fa-plus"></i>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="page-library" class="page hide">
	<div class="content-top">
		<i id="button-library" class="fa fa-library active"></i>
		<i id="button-lib-search" class="fa fa-search"></i>
		<div id="lib-search" class="hide">
			<div class="input-group">
				<input id="lib-search-input" class="form-control input" type="text">
				<span class="input-group-btn">
					<button id="lib-search-btn" class="btn btn-default"><i class="fa fa-search"></i></button>
				</span>
			</div>
		</div>
		<div id="lib-search-close"></div>
		<div id="lib-path">
			<i id="button-lib-back" class="fa fa-arrow-left"></i>
			<span id="lib-breadcrumbs"></span>
			<span class="lipath"></span>
		</div>
	</div>
	<div id="lib-mode-list" class="list" data-count="<?=$counts->song?>"><?=$modehtml?></div>
	<ul id="lib-list" class="list"></ul>
	<div id="lib-index" class="hide"></div>
	<div id="lib-cover-list" class="list hide"><?=$coverartshtml?></div>
</div>

<div id="page-playlist" class="page hide">
	<div class="emptyadd hide"><i class="fa fa-plus-circle"></i></div>
	<div class="content-top">
		<i id="button-playlist" class="fa fa-list-ul active"></i>
		<i id="button-pl-back" class="fa fa-arrow-left hide"></i>
		<i id="button-pl-search" class="fa fa-search pllength"></i>
		<form id="pl-search" class="hide" method="post" onSubmit="return false;">
			<div class="input-group">
				<input id="pl-search-input" class="form-control input" type="text">
				<span class="input-group-btn">
					<button id="pl-search-btn" class="btn btn-default" type="button"><i class="fa fa-search"></i></button>
				</span>
			</div>
		</form>
		<button id="pl-search-close" class="btn hide" type="button"></button>
		<div id="pl-manage" class="playlist">
			<i id="button-pl-open" class="fa fa-folder-open"></i>
			<i id="button-pl-save" class="fa fa-save pllength"></i>
			<i id="button-pl-consume" class="fa fa-flash"></i>
			<i id="button-pl-random" class="fa fa-dice"></i>
			<i id="button-pl-shuffle" class="fa fa-shuffle pllength"></i>
			<i id="button-pl-crop" class="fa fa-crop pllength"></i>
			<i id="button-pl-clear" class="fa fa-minus-circle pllength"></i>
		</div>
		<span id="pl-count" class="playlist hide"></span>
		<span id="pl-path" class="hide"></span>
	</div>
	<ul id="pl-list" class="list playlist"></ul>
	<ul id="pl-savedlist" class="list hide"></ul>
	<div id="pl-index" class="hide"></div>
</div>

<?=$menu?>

<div id="colorpicker" class="hide">
	<div id="divcolor">
	<i id="colorcancel" class="fa fa-times fa-2x"></i>
	<canvas id="canvascolor"></canvas><br>
<?php if ( file_exists( '/srv/http/data/system/color' ) ) { ?>
	<a id="colorreset" class="btn">Default</a>&ensp;
<?php } ?>
	<a id="colorok" class="btn btn-primary">Set</a>
	</div>
</div>
<div id="bio" class="hide">
	<div class="container">
		<img id="biobanner">
		<div id="bioimg"></div>
		<div id="biocontent"></div>
	</div>
</div>
<div id="lyrics" class="hide">
	<div id="divlyricstitle">
		<span id="lyricstitle"></span>
		<i id="lyricsclose" class="fa fa-times"></i>
	</div>
	<div id="divlyricsartist">
		<span id="lyricsartist"></span><i id="lyricsedit" class="fa fa-edit-circle"></i>
		<div id="lyricseditbtngroup" class="hide">
			<i id="lyricsundo" class="fa fa-undo hide"></i>
			<i id="lyricssave" class="fa fa-save hide"></i>
			<i id="lyricsdelete" class="fa fa-minus-circle"></i>
			<i id="lyricsback" class="fa fa-arrow-left"></i>
		</div>
	</div>
	<div id="lyricstextoverlay">
		<div id="lyricstext" class="lyricstext"></div>
	</div>
	<textarea id="lyricstextarea" class="lyricstext"></textarea>
	<div id="lyricsfade"></div>
</div>
	<?php if ( $localhost ) { ?>
<input class="input hide">
<div id="keyboard" class="hide"><div class="simple-keyboard"></div></div>
	<?php } ?>

<div id="swipebar" class="transparent">
	<i id="swipeL" class="fa fa-left fa-2x"></i>
	<i class="fa fa-reload fa-2x"></i><i class="fa fa-swipe fa-2x"></i><i class="fa fa-gear fa-2x"></i>
	<i id="swipeR" class="fa fa-right fa-2x"></i>
</div>
<div id="bar-bottom" class="hide">
	<i id="tab-library" class="fa fa-library"></i>
	<i id="tab-playback" class="active fa fa-play-circle"></i>
	<i id="tab-playlist" class="fa fa-list-ul"></i>
</div>
