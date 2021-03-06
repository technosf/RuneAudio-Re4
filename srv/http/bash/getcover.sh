#!/bin/bash

path="$1"
size=$2
# get coverfile in directory
[[ -d "$path" ]] && dir="$path" || dir=$( dirname "$path" )
for name in cover folder front thumb album; do
	for ext in jpg png gif; do
		coverfile="$dir/$name.$ext"
		[[ -e "$coverfile" ]] && found=1 && break
		coverfile="$dir/${name^}.$ext" # capitalize
		[[ -e "$coverfile" ]] && found=1 && break
	done
	[[ $found == 1 ]] && break
done

# get embedded in file
if [[ $found != 1 ]]; then
	if [[ -f "$path" ]]; then
		file="$path"
	else
		files=$( mpc ls "${path:9}" )
		readarray -t files <<<"$files"
		for file in "${files[@]}"; do
			file="/mnt/MPD/$file"
			[[ -f "$file" ]] && break
		done
	fi
	tmpfile=/srv/http/data/tmp/coverart.jpg
	rm -f $tmpfile
	kid3-cli -c "select \"$file\"" -c "get picture:$tmpfile" &> /dev/null # suppress '1 space' stdout
	[[ -e $tmpfile ]] && found=1 && coverfile=/data/tmp/coverart.jpg
fi

[[ $found != 1 ]] && exit

[[ -z $size || $ext == gif ]] && echo -n ${coverfile%.*}.$( date +%s ).${coverfile/*.} && exit

# resize
base64file=/srv/http/data/tmp/base64
convert "$coverfile" -thumbnail ${size}x${size} -unsharp 0x.5 inline:$base64file
cat $base64file

