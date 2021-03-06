#!/bin/bash

curlPost() {
	curl -s -X POST 'http://127.0.0.1/pub?id='$1 -d "$2"
}

mpc idleloop | while read changed; do
	case $changed in
		database )
			curlPost mpddatabase 1
			;;
		options )
			curlPost mpdoptions "$( /srv/http/bash/status.sh statusonly )"
			;;
		player )
			if [[ -e /srv/http/data/tmp/nostatus ]]; then
				rm /srv/http/data/tmp/nostatus
				continue
			fi
			
			status=$( /srv/http/bash/status.sh )
			if [[ ! -e /srv/http/data/system/player-snapclient ]]; then
				curlPost mpdplayer "$status"
			else
				sed -i '/^$/d' $snapclientfile # remove blank lines
				if [[ -s $snapclientfile ]]; then
					mapfile -t clientip < $snapclientfile
					for ip in "${clientip[@]}"; do
						curl -s -X POST "http://$ip/pub?id=mpdplayer" -d "$status"
					done
				else
					rm $snapclientfile
				fi
			fi
			;;
		playlistplayer )
			status=$( /srv/http/bash/status.sh )
			curlPost mpdplayer "$status"
			;;
		playlist )
			curlPost playlist '{"playlist":"playlist"}'
			;;
		update )
			if mpc | grep -q '^Updating DB ('; then
				curlPost mpdupdate 1
			else
				curlPost mpdupdate "$( /srv/http/bash/mpdcount.sh )"
			fi
			;;
	esac
done
