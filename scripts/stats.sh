#!/bin/sh

for dir in `cat /etc/proftpd/vpasswd | cut -d : -f 6`; do
#for dir in /home/dalleFTP/reynaud_mila; do
	if [ -d $dir ]; then
		du -s $dir | cut -f 1 >$dir/.size
		chmod 644 $dir/.size

		#mtime=0
		#export IFS=$'\n'
		#for file in `find $dir -type f -not -name .size -not -name .last-modified`; do
		#	timestamp=`stat -c %Y $file`
		#	if [ $timestamp -gt $mtime ]; then
		#		mtime=$timestamp
		#	fi
		#done
		#unset IFS
			
		#echo $mtime >$dir/.lastmodified
		#chmod 644 $dir/.lastmodified
	fi
done

