#!/bin/sh

directory=$1

if [ -d "$directory" ]; then
  echo -e -n "1"
else
  echo -e -n "0"
fi
