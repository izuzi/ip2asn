#!/usr/bin/env bash
#
# Cache file purging script.
# This script is part of ip2asn PHP library.
#
# @version    2020-09-27 12:12:00 UTC
# @author     Peter Kahl <https://github.com/peterkahl>
# @copyright  2015-2020 Peter Kahl
# @license    Apache License, Version 2.0
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#      <http://www.apache.org/licenses/LICENSE-2.0>
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

# Disable unicode
LC_ALL=C
LANG=C

TSTARTM="$(date +"%s.%N")"

# Cache directory
CACHEDIR="/srv/bgp"

# Cache time in seconds
CACHETIME="1209600" # 14 days

# If a files has more than MAX_LINES, it will be tailed to $REDUCETO_LINES
MAX_LINES="500000"

REDUCETO_LINES="100000"

MODULENAME="ip2asn"

SUBNAME="purger"

# Whether to provide debug info?
# 0 ..... only errors
# 1 ..... medium
# 2 ..... every useless detail
LOG_LEVEL="2"

debugLog="${CACHEDIR}/${MODULENAME}_debug.log"

TLIMIT="$(($(date +"%s")-CACHETIME))"

# ====================================================================

function lineExists()
{
  # lineExists filename string
  cat "$1" | grep "$2" >/dev/null 2>&1 && \
    return 0 || \
    return 1
}

function milliStopwatch()
{
  local intval="$(echo "$(date +"%s.%N")-$1" | bc -l)"
  local seconds
  local ninechars
  IFS="." read -r seconds ninechars <<< "$intval"
  (( seconds < 1 )) && \
    printf %s "$(echo "$ninechars" | cut -b1-3 | sed 's/^00*//').$(echo "$ninechars" | cut -b4-5)ms" || \
    printf %s "${seconds}.$(echo "$ninechars" | cut -b1-2)s"
}

function sec2days()
{
  local secs="$1"
  if (( secs >= 86400 ))
  then
    printf %s "$((secs/86400))d"
  elif (( secs >= 3600 ))
    then
    printf %s "$((secs/3600))h"
  elif (( secs >= 60 ))
    then
    printf %s "$((secs/60))m"
  else
    printf %s "${secs}s"
  fi
}

function log_write()
{
  # log_write <string> <severity>
  local string="$1"
  local severity="$2"
  (( severity <= LOG_LEVEL )) && echo "$(date +"%Y-%m-%d %H:%M:%S") $MODULENAME/$SUBNAME[$BASHPID]: $string" >> $debugLog
}

function RandomString()
{
  printf %s "$(openssl rand -base64 13 | tr -cd "[0-9A-Za-z]")"
}

function get_lcount()
{
  printf %s "$(wc -l $1 | cut -d " " -f1)"
}

READABLECTM="$(sec2days "$CACHETIME")"

# ====================================================================

ver="4"
cachefile="${CACHEDIR}/${MODULENAME}_v${ver}_asdata.cache"

log_write ">>>> Purging file $cachefile ; CACHETIME=${READABLECTM}" "1"

randstr="$(RandomString)"
TEMPA="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_A.tmp"
TEMPB="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_B.tmp"

if [ -s $cachefile ]
then
  lines="$(get_lcount $cachefile)"
  log_write " File has $lines lines" "2"
fi

# ====================================================================
# Remove outdated lines
if [ -s $cachefile ]
then
  deleted="0"
  tstamp="$(head -n 1 $cachefile | cut -d "|" -f1)"
  age="$(($(date +"%s")-tstamp))"
  if (( age > CACHETIME ))
  then
    cp $cachefile $TEMPA
    while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
    do
      if (( f1 > TLIMIT ))
      then
        echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPB
      else
        deleted="$((deleted+1))"
      fi
    done < $TEMPA
    chown www-data:www-data $TEMPB && chmod 0644 $TEMPB
    mv $TEMPB $cachefile
    rm $TEMPA
    (( deleted > 0 )) && log_write "-STALE: Deleted $deleted lines" "1"
  else
    log_write " STALE: Oldest record is $(sec2days "$age") old" "2"
  fi
else
  log_write " STALE: File not found or empty" "2"
fi
# ====================================================================

ver="6"
cachefile="${CACHEDIR}/${MODULENAME}_v${ver}_asdata.cache"

log_write ">>>> Purging file $cachefile ; CACHETIME=${READABLECTM}" "1"

randstr="$(RandomString)"
TEMPA="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_A.tmp"
TEMPB="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_B.tmp"

if [ -s $cachefile ]
then
  lines="$(get_lcount $cachefile)"
  log_write " File has $lines lines" "2"
fi

# ====================================================================
# Remove outdated lines
if [ -s $cachefile ]
then
  deleted="0"
  tstamp="$(head -n 1 $cachefile | cut -d "|" -f1)"
  age="$(($(date +"%s")-tstamp))"
  if (( age > CACHETIME ))
  then
    cp $cachefile $TEMPA
    while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
    do
      if (( f1 > TLIMIT ))
      then
        echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPB
      else
        deleted="$((deleted+1))"
      fi
    done < $TEMPA
    chown www-data:www-data $TEMPB && chmod 0644 $TEMPB
    mv $TEMPB $cachefile
    rm $TEMPA
    (( deleted > 0 )) && log_write "-STALE: Deleted $deleted lines" "1"
  else
    log_write " STALE: Oldest record is $(sec2days "$age") old" "2"
  fi
else
  log_write " STALE: File not found or empty" "2"
fi
# ====================================================================

log_write ">>>> Purging files ${CACHEDIR}/${MODULENAME}_prefixes_v*.json ; CACHETIME=${READABLECTM}" "1"

if (( LOG_LEVEL == 2 ))
then
  totalcnt="$(find $CACHEDIR -name "${MODULENAME}_prefixes_v*.json" -type f | wc -l)"
  log_write " PREFIXES: Found $totalcnt files" "2"
fi

stale_count="$(find $CACHEDIR -name "${MODULENAME}_prefixes_v*.json" -mmin +"$((CACHETIME/60))" -type f | wc -l)"

if (( stale_count > 0 ))
then
  find $CACHEDIR -name "${MODULENAME}_prefixes_v*.json" -mmin +"$((CACHETIME/60))" -type f -delete && \
    log_write "-PREFIXES: Deleted $stale_count files" "1"
else
  log_write " PREFIXES: Nothing to delete" "2"
fi

# ====================================================================

log_write "Process completed in $(milliStopwatch "$TSTARTM")" "2"

exit 0
