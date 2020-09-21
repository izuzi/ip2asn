#!/usr/bin/env bash
#
# Cache file purging script.
#
# This script is part of ip2asn PHP library.
#
# @version    2020-09-21 07:51:00 UTC
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

# ====================================================================

function lineExists()
{
  # lineExists filename string
  cat "$2" | grep "$1" && \
    return 1 || \
    return 0
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

# ====================================================================

ver="4"

# ====================================================================
# Too many lines?

cachefile="${CACHEDIR}/${MODULENAME}_v${ver}_asdata.cache"

log_write "Purging file $cachefile" "1"

randstr="$(RandomString)"

TEMPFILEA="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_A.tmp"
TEMPFILEB="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_B.tmp"

if [ -s $cachefile ]
then
  lines="$(wc -l $cachefile | cut -d " " -f1)"

  if (( lines > 500000 ))
  then
    tail -n 200000 $cachefile > $TEMPFILEA
    mv -f $TEMPFILEA $cachefile
    chmod 0664 $cachefile
  fi
fi

# ====================================================================
# Remove outdated lines

if [ -s $cachefile ]
then
  cp $cachefile $TEMPFILEA
  tlimit="$(($(date +"%s")-CACHETIME))"
  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    if (( f1 > $tlimit ))
    then
      echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPFILEB
    fi
  done < $TEMPFILEA

  mv -f $TEMPFILEB $cachefile
  chmod 0664 $cachefile
  rm $TEMPFILEA
fi

# ====================================================================
# Remove duplicate lines

if [ -s $cachefile ]
then
  cp $cachefile $TEMPFILEA
  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    lineExists $TEMPFILEB "|$f3|" && "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPFILEB
  done < $TEMPFILEA

  mv -f $TEMPFILEB $cachefile
  chmod 0664 $cachefile
  rm $TEMPFILEA
fi

# ====================================================================

ver="6"

# ====================================================================
# Too many lines?

cachefile="${CACHEDIR}/${MODULENAME}_v${ver}_asdata.cache"

log_write "Purging file $cachefile" "1"

randstr="$(RandomString)"

TEMPFILEA="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_A.tmp"
TEMPFILEB="${CACHEDIR}/${MODULENAME}_tmp_${randstr}_B.tmp"

if [ -s $cachefile ]
then
  lines="$(wc -l $cachefile | cut -d " " -f1)"

  if (( lines > 500000 ))
  then
    tail -n 200000 $cachefile > $TEMPFILEA
    mv -f $TEMPFILEA $cachefile
    chmod 0664 $cachefile
  fi
fi

# ====================================================================
# Remove outdated lines

if [ -s $cachefile ]
then
  cp $cachefile $TEMPFILEA
  tlimit="$(($(date +"%s")-CACHETIME))"
  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    if (( f1 > $tlimit ))
    then
      echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPFILEB
    fi
  done < $TEMPFILEA

  mv -f $TEMPFILEB $cachefile
  chmod 0664 $cachefile
  rm $TEMPFILEA
fi

# ====================================================================
# Remove duplicate lines

if [ -s $cachefile ]
then
  cp $cachefile $TEMPFILEA
  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    lineExists $TEMPFILEB "|$f3|" && "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPFILEB
  done < $TEMPFILEA

  mv -f $TEMPFILEB $cachefile
  chmod 0664 $cachefile
  rm $TEMPFILEA
fi

# ====================================================================

log_write "Process completed in $(milliStopwatch $TSTARTM)" "2"

exit 0
