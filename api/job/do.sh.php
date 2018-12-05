#!/bin/bash
#<?php die; ?>
#*/1 * * * * /bin/bash ${path}/api/job/do.sh.php > /dev/null 2>&1 &
umask 002
cd $(cd "$(dirname "$0")" && pwd)
readonly path=$(dirname "$(dirname "$(pwd)")")/
readonly minute=$((10#$(date +%M)))

#每1分钟

#每5分钟
if [ "0" -eq "$(($minute % 5))" ]; then
    echo
fi
