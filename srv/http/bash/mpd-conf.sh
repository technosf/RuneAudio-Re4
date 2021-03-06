#!/bin/bash

# remove all output the reinstate each output:
# - get devices data from 'mpd-devices.sh'
# - assign common paramters
# - mixer_type    - from file if manually set | hardware if hwmixer | software
# - mixer_control - from file if manually set | hwmixer | null
# - mixer_device  - card index
# - dop           - if set

systemctl -q is-active nginx || exit 0 # udev rule trigger on startup

dirsystem=/srv/http/data/system
audiooutput=$( cat $dirsystem/audio-output )
audioaplayname=$( cat $dirsystem/audio-aplayname )
mpdfile=/etc/mpd.conf
mpdconf=$( sed '/audio_output/,/}/ d' $mpdfile ) # remove all outputs

. /srv/http/bash/mpd-devices.sh

for (( i=0; i < $cardL; i++ )); do
	card=${Acard[i]}
	dop=${Adop[i]}
	hw=${Ahw[i]}
	hwmixer=${Ahwmixer[i]}
	mixermanual=${Amixermanual[i]}
	mixertype=${Amixertype[i]}
	name=${Aname[i]}
	aplayname=${Aaplayname[i]}
########
	mpdconf+='

audio_output {
	name           "'$name'"
	device         "'$hw'"
	type           "alsa"
	auto_resample  "no"
	auto_format    "no"
	mixer_type     "'$mixertype'"'
	
	if [[ $mixertype != none ]]; then
		if [[ -n $mixermanual ]]; then # mixer_device must be card index
########
			mpdconf+='
	mixer_control  "'$mixermanual'"
	mixer_device   "hw:'$card'"'
		
		elif [[ -n $hwmixer ]]; then
			device=$( amixer -c $card scontrols | cut -d',' -f2 )
########
			mpdconf+='
	mixer_control  "'$hwmixer'"
	mixer_device   "hw:'$card'"'
		
		fi
	fi
	
	if [[ $dop == 1 && $aplayname != 'bcm2835 ALSA' ]]; then
########
		mpdconf+='
	dop            "yes"'
	
	fi
########
	mpdconf+='
}'
done

if systemctl -q is-active snapserver; then
	mpdconf+='

audio_output {
	type           "fifo"
	name           "Snapcast"
	path           "/tmp/snapfifo"
	format         "48000:16:2"
	mixer_type     "software"
}'
fi

if [[ -e /srv/http/data/system/streaming ]]; then
	mpdconf+='

audio_output {
	type           "httpd"
	name           "Streaming"
	encoder        "flac"
	port           "8000"
	quality        "5.0"
	format         "44100:16:1"
	always_on      "yes"
}'
fi

if [[ $1 == bt && -n $2 ]]; then
	if [[ $2 == udev ]]; then
		macs=( $( bluetoothctl paired-devices | cut -d' ' -f2 ) )
		for mac in "${macs[@]}"; do
			bluetoothctl info $mac | grep -q 'Connected: yes' && break
		done
	else
		mac=$2
	fi
	name=$( bluetoothctl paired-devices | grep $mac | cut -d' ' -f3- )
		mpdconf+='

audio_output {
	name           "'$name'"
	device         "bluealsa:DEV='$mac',PROFILE=a2dp"
	type           "alsa"
	mixer_type     "software"
}'
fi

echo "$mpdconf" > $mpdfile

usbdacfile=/srv/http/data/system/usbdac

systemctl restart mpd  # "restart" while not running = start + stop + start
curl -s -X POST 'http://127.0.0.1/pub?id=refresh' -d '{ "page": "mpd" }'

# udev rules - usb dac
if [[ $# -gt 0 && $1 != bt ]]; then
	if [[ $1 == remove ]]; then
		name=$audiooutput
		card=$( echo "$aplay" \
			| grep "$audioaplayname" \
			| head -1 \
			| cut -c6 )
		hwmixer=$( amixer -c $card scontents \
			| grep -B1 'pvolume' \
			| head -1 \
			| cut -d"'" -f2 )
		rm -f $usbdacfile
	else # added usb dac - last one
		[[ $mixertype == 'none' && -n $hwmixer ]] && amixer -c $card sset "$hwmixer" 0dB
		echo $aplayname > $usbdacfile # flag - active usb
	fi
	
	curl -s -X POST 'http://127.0.0.1/pub?id=notify' -d '{ "title": "Audio Output", "text": "'"$name"'", "icon": "output" }'
else
	aplayname=$audioaplayname
fi

if [[ -e /usr/bin/shairport-sync ]]; then
	for i in "${!Aaplayname[@]}"; do # get current aplay card number
		[[ ${Aaplayname[$i]} == $aplayname ]] && break
	done
	if [[ -n ${Amixermanual[i]} ]]; then
		hwmixer="${Amixermanual[i]}"
	elif [[ -n ${Ahwmixer[i]} ]]; then
		hwmixer="${Ahwmixer[i]}"
	fi
	alsa='alsa = {
	output_device = "hw:'${Acard[i]}'";'
	
	[[ -n $hwmixer ]] && alsa+='
	mixer_control_name = "'$hwmixer'";'
	
	alsa+='
}
	'
	sed -i '/^alsa =/,$ d' /etc/shairport-sync.conf
	echo "$alsa" >> /etc/shairport-sync.conf

	curl -s -X POST 'http://127.0.0.1/pub?id=airplay' -d '{"stop":"switchoutput"}'
	systemctl try-restart shairport-sync
fi

if [[ -e /usr/bin/spotifyd ]]; then
	if [[ -e $dirsystem/spotify-device ]]; then
		device=$( cat $dirsystem/spotify-device )
	else
		cardname=$( aplay -l | grep "^card ${Acard[i]}:" | head -1 | cut -d' ' -f3 ) # 'head -1" for on-board
		device=$( aplay -L | grep "^default.*$cardname" )
	fi
	sed -i "s/^\(device = \).*/\1$device/" /etc/spotifyd.conf
	systemctl try-restart spotifyd
fi
