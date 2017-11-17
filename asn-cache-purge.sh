#!/bin/bash
#
# IP 2 ASN
# Maps IP address to ASN.
#
# @version    0.6 (2017-11-17 19:36:19 GMT)
# @author     Peter Kahl <peter.kahl@colossalmind.com>
# @copyright  2015-2017 Peter Kahl
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


# ====== User configuration (edit as needed) ======
#
# Cache time in seconds
# 180 days
CACHETIME="15552000"

# Cache directory
CACHEDIR="/srv/bgp"

# ========== Do not edit below this line ==========



###################################################
# lineExists filename string

lineExists()
{
  RESULT=0
  TEST=$(cat "$1" | grep "$2")
  if [ -n "$TEST" ]; then
    RESULT=1
  fi
  return $RESULT
}


###################################################
# Too many lines

ASNCACHE="$CACHEDIR/ASN4-CACHE.db"
TEMPFILEA="$CACHEDIR/ASN4-A.tmp"
TEMPFILEB="$CACHEDIR/ASN4-B.tmp"

if [[ -e $ASNCACHE && -s $ASNCACHE ]]; then
  ACTUALLINES=$(cat $ASNCACHE | wc -l)
  if [[ $ACTUALLINES > "500000" ]]; then
    tail -n 200000 $ASNCACHE > $TEMPFILEA
    mv -f $TEMPFILEA $ASNCACHE
    chmod 0664 $ASNCACHE
  fi
fi

###################################################
# Purge outdated

if [[ -e $ASNCACHE && -s $ASNCACHE ]]; then

  cp $ASNCACHE $TEMPFILEA
  touch $TEMPFILEB

  TIMELIM=$(expr $(date +%s) - $CACHETIME)

  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    if [[ $f1 > $TIMELIM ]]; then
      # Re-create the cache file
      echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPFILEB
    fi
  done < $TEMPFILEA

  mv -f $TEMPFILEB $ASNCACHE
  chmod 0664 $ASNCACHE
  rm $TEMPFILEA
fi

###################################################
# Purge duplicate lines

if [[ -e $ASNCACHE && -s $ASNCACHE ]]; then

  cp $ASNCACHE $TEMPFILEA
  touch $TEMPFILEB

  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    lineExists $TEMPFILEB "|$f3|"
    if [[ $? == "0" ]]; then
      # Re-create the cache file
      echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPFILEB
    fi
  done < $TEMPFILEA

  mv -f $TEMPFILEB $ASNCACHE
  chmod 0664 $ASNCACHE
  rm $TEMPFILEA
fi

################################################### ###################################################
# Too many lines

ASNCACHE="$CACHEDIR/ASN6-CACHE.db"
TEMPFILEA="$CACHEDIR/ASN6-A.tmp"
TEMPFILEB="$CACHEDIR/ASN6-B.tmp"

if [[ -e $ASNCACHE && -s $ASNCACHE ]]; then
  ACTUALLINES=$(cat $ASNCACHE | wc -l)
  if [[ $ACTUALLINES > "500000" ]]; then
    tail -n 200000 $ASNCACHE > $TEMPFILEA
    mv -f $TEMPFILEA $ASNCACHE
    chmod 0664 $ASNCACHE
  fi
fi

###################################################
# Purge outdated

if [[ -e $ASNCACHE && -s $ASNCACHE ]]; then

  cp $ASNCACHE $TEMPFILEA
  touch $TEMPFILEB

  TIMELIM=$(expr $(date +%s) - $CACHETIME)

  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    if [[ $f1 > $TIMELIM ]]; then
      # Re-create the cache file
      echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPFILEB
    fi
  done < $TEMPFILEA

  mv -f $TEMPFILEB $ASNCACHE
  chmod 0664 $ASNCACHE
  rm $TEMPFILEA
fi

###################################################
# Purge duplicate lines

if [[ -e $ASNCACHE && -s $ASNCACHE ]]; then

  cp $ASNCACHE $TEMPFILEA
  touch $TEMPFILEB

  while IFS='|' read -r f1 f2 f3 f4 f5 f6 f7 f8
  do
    lineExists $TEMPFILEB "|$f3|"
    if [[ $? == "0" ]]; then
      # Re-create the cache file
      echo "$f1|$f2|$f3|$f4|$f5|$f6|$f7|$f8" >> $TEMPFILEB
    fi
  done < $TEMPFILEA

  mv -f $TEMPFILEB $ASNCACHE
  chmod 0664 $ASNCACHE
  rm $TEMPFILEA
fi

###################################################

exit 0
