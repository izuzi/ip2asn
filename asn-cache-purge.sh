#!/usr/bin/env bash
#
# Cache file purging script.
#
# This script is part of ip2asn PHP library.
#
# @version    2020-09-21 10:38:00 UTC
# @author     Peter Kahl <https://github.com/peterkahl>
# @copyright  2015-2020 Peter Kahl
# @license    Apache License, Version 2.0
#
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

# ============= User configuration (edit as needed) ==================

# Cache directory
CACHEDIR="/srv/bgp"

# Cache time in seconds
CACHETIME="1209600" # 14 days


# ================= Do not edit below this line ======================

# Disable unicode
LC_ALL=C
LANG=C

TSTARTM="$(date +"%s.%N")"

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
  cat "$1" | grep "$2" && \
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

function log_write()
{
  # Usage:
  # $ log_write <string> <severity>
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

# ====================================================================

ver="4"

cachefile="${CACHEDIR}/${MODULENAME}_v${ver}_asdata.cache"

log_write ">>>> Purging file $cachefile" "1"

randstr="$(RandomString)"

TEMPA="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_A.tmp"
TEMPB="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_B.tmp"

# ====================================================================
# Too many lines?

if [ -s $cachefile ]
then
  lines="$(get_lcount $cachefile)"
  log_write "File has $lines lines" "2"
  if (( lines > 500000 ))
  then
    log_write "Reducing file to 200000 lines" "2"
    tail -n 200000 $cachefile > $TEMPA
    chown www-data:www-data $TEMPA && chmod 0644 $TEMPA
    mv -f $TEMPA $cachefile
  fi
fi

# ====================================================================
# Remove outdated lines

if [ -s $cachefile ]
then
  deleted="0"
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
  log_write "STALE: Deleted $deleted lines" "2"
  chown www-data:www-data $TEMPB && chmod 0644 $TEMPB
  mv -f $TEMPB $cachefile
  rm $TEMPA
fi

# ====================================================================
# Remove duplicate lines

if [ -s $cachefile ]
then
  deleted="0"
  cp $cachefile $TEMPA
  touch $TEMPB
  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    if ! lineExists $TEMPB "|$f3|"
    then
      echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPB
    else
      deleted="$((deleted+1))"
    fi
  done < $TEMPA
  log_write "DUPLICATES: Deleted $deleted lines" "2"
  chown www-data:www-data $TEMPB && chmod 0644 $TEMPB
  mv -f $TEMPB $cachefile
  rm $TEMPA
fi

# ====================================================================

ver="6"

cachefile="${CACHEDIR}/${MODULENAME}_v${ver}_asdata.cache"

log_write ">>>> Purging file $cachefile" "1"

randstr="$(RandomString)"

TEMPA="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_A.tmp"
TEMPB="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_B.tmp"

# ====================================================================
# Too many lines?

if [ -s $cachefile ]
then
  lines="$(get_lcount $cachefile)"
  log_write "File has $lines lines" "2"
  if (( lines > 500000 ))
  then
    log_write "Reducing file to 200000 lines" "2"
    tail -n 200000 $cachefile > $TEMPA
    chown www-data:www-data $TEMPA && chmod 0644 $TEMPA
    mv -f $TEMPA $cachefile
  fi
fi

# ====================================================================
# Remove outdated lines

if [ -s $cachefile ]
then
  deleted="0"
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
  log_write "STALE: Deleted $deleted lines" "2"
  chown www-data:www-data $TEMPB && chmod 0644 $TEMPB
  mv -f $TEMPB $cachefile
  rm $TEMPA
fi

# ====================================================================
# Remove duplicate lines

if [ -s $cachefile ]
then
  deleted="0"
  cp $cachefile $TEMPA
  touch $TEMPB
  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    if ! lineExists $TEMPB "|$f3|"
    then
      echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPB
    else
      deleted="$((deleted+1))"
    fi
  done < $TEMPA
  log_write "DUPLICATES: Deleted $deleted lines" "2"
  chown www-data:www-data $TEMPB && chmod 0644 $TEMPB
  mv -f $TEMPB $cachefile
  rm $TEMPA
fi

# ====================================================================

log_write "Process completed in $(milliStopwatch $TSTARTM)" "2"

exit 0
