<?php
/**
 * ip2asn
 * Maps IP address to as number; prefixes for given AS and other
 * related methods.
 *
 * @version    2020-11-11 07:51:00 UTC
 * @author     Peter Kahl <https://github.com/peterkahl>
 * @copyright  2015-2020 Peter Kahl
 * @license    Apache License, Version 2.0
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      <http://www.apache.org/licenses/LICENSE-2.0>
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace peterkahl\ip2asn;

use \SplFileObject;
use \Exception;


class ip2asn
{
  /**
   * Name of this library as first part of file name
   * @var string
   */
  const NAME_PREFIX='ip2asn_';


  /**
   * Filename prefix incl. full path to cache directory.
   * @var string
   */
  private $_file_prefix;


  /**
   * Filename suffix of ASN to name lookup.
   * @var string
   */
  const ASN2NAME_FILESUFFIX='asn2name.db';


  /**
   * Filename of ASN to name lookup.
   * (The full name will be cachdir+prefix+suffix.)
   * @var string
   */
  private $_asn2name_filename;


  /**
   * File object for reading asn2name database.
   * @var object
   */
  private $_nameObj;


  /**
   * User-specific maximum age of cache files.
   * @var integer
   */
  public $cache_time;


  /**
   * Maximum age of cache files.
   * @var integer
   */
  private $_caching_time;


  /**
   * Cache directory.
   * @var string
   */
  private $_cachedir;


  /**
   * Default value of maximum age of cache files.
   * @var integer
   */
  const CACHING_TIME_DEFAULT=604800;


  /**
   * Constructor
   * @param  string
   * @throws \Exception
   */
  public function __construct($dir)
  {
    $this->_check_cachedir($dir);
    $this->_file_prefix=$this->_get_cachedir().'/'.self::NAME_PREFIX;
    $this->_asn2name_filename=$this->_get_cachedir().'/'.self::NAME_PREFIX.self::ASN2NAME_FILESUFFIX;
    $this->_nameObj=new SplFileObject($this->_asn2name_filename, 'r');
  }


  /**
   * Checks whether directory is defined & exists.
   * @param  string
   * @throws \Exception
   */
  private function _check_cachedir($dir)
  {
    $dir=rtrim($dir, '/');
    if (empty($dir) || strpos($dir, "\0")!==false) {
      throw new Exception("Illegal value argument dir");
    }
    if (!file_exists($dir) || !is_dir($dir)) {
      throw new Exception("Valid cache directory must be specified");
    }
    $this->_cachedir=$dir;
  }


  /**
   * Returns cache directory.
   * @return string
   */
  private function _get_cachedir()
  {
    return $this->_cachedir;
  }


  /**
   * Returns file name prefix.
   * @param  string
   * @return string
   * @throws \Exception
   */
  private function _get_file_prefix()
  {
    if (!empty($this->_file_prefix)) {
      return $this->_file_prefix;
    }
    throw new Exception("Property _file_prefix cannot be empty");
  }


  /**
   * Returns AS number data for given IP address.
   * @param  string
   * @return array
   * @throws \Exception
   */
  public function getAsn($addr)
  {
    if (!is_string($addr) || !$this->_valid_ip($addr)) throw new Exception("Illegal type/value argument addr");
    $ver=$this->_get_ipv($addr);
    $bin=$this->_ip2binstr($addr, $ver);
    $file=$this->_get_file_prefix()."v${ver}_asdata.cache";
    $arr=array();
    if (!file_exists($file)) touch($file);
    $fileObj=new SplFileObject($file, 'r+');
    while (!$fileObj->flock(LOCK_EX)) {
      usleep(1);
    }
    while (!$fileObj->eof())
    {
      $line=$fileObj->fgets();
      if (strpos($line, '|')!==false) {
        $found=array();
        $found=explode('|', trim($line));
        if ($this->_match_bin_strings($bin, $found[1])) {
          $fileObj->flock(LOCK_UN);
          $found[]='ip2asn';
          return $this->_make_result_array($found);
        }
      }
    }

    $arr=$this->_get_cymru_asn($addr, $ver);

    if (empty($arr)) {
      $values=array(
        0=>time(),
        1=>'',
        2=>'',
        3=>'',
        4=>'',
        5=>'',
        6=>'',
        7=>'',
        8=>'ip2asn',
      );
      $fileObj->flock(LOCK_UN);
      return $this->_make_result_array($values);
    }

    if (empty($arr[1])) {
      throw new Exception("Undefined key 1. Address $addr");
    }

    $values=array(
      0=>time(),
      1=>$this->_cidr2binprefix($arr[1]),
      2=>$arr[1],
      3=>$arr[2],
      4=>$arr[0],
      5=>$this->Asn2description($arr[0]),
      6=>strtoupper($arr[3]),
      7=>$arr[4],
    );

    $str=implode('|', $values);
    $fileObj->fwrite("$str\n");
    $fileObj->flock(LOCK_UN);
    $values[8]='ip2asn';
    return $this->_make_result_array($values);
  }


  /**
   * Returns AS number and associated data.
   * @param  string
   * @param  integer
   * @return array
   * @throws \Exception
   */
  private function _get_cymru_asn($addr, $ver)
  {
    $arr=array();
    switch ($ver) {
    case 4:
      exec('dig +short '.$this->_get_rev_addr_four($addr).'.origin.asn.cymru.com TXT', $arr); break;
    case 6:
      exec('dig +short '.$this->_get_rev_addr_six($addr).'.origin6.asn.cymru.com TXT', $arr); break;
    default:
      throw new Exception("Illegal value ver");
    }
    if (empty($arr)) return array();
    # The array may have more than one result, but we use the first one.
    $new=explode('|', trim($arr[0], " \"\t\n\r\0\x0B"));
    return array_map('trim', $new);
  }


  /**
   * Returns organisation/isp of given AS number.
   * @param  integer (omit the AS part)
   * @return string
   */
  public function Asn2description($num)
  {
    $num=(integer) $num;
    $needle="AS${num} ";
    $len=strlen($needle);
    while (!$this->_nameObj->flock(LOCK_SH)) {
      usleep(1);
    }
    while (!$this->_nameObj->eof())
    {
      $line=$this->_nameObj->fgets();
      if (substr($line, 0, $len)===$needle) {
        $this->_nameObj->flock(LOCK_UN);
        return trim(substr($line, $len));
      }
    }
    $this->_nameObj->flock(LOCK_UN);
    return "";
  }


  /**
   * Returns array of prefixes for given AS number.
   * @param  integer
   * @param  integer
   * @return array
   */
  public function Asn2prefix($num, $ver)
  {
    $num=(integer) $num;
    if ($num<1) throw new Exception("Illegal value argument num");

    if (!is_integer($ver) || ($ver!==4 && $ver!==6)) throw new Exception("Illegal type/value argument ver");

    $file=$this->_get_file_prefix()."prefixes_v${ver}_${num}.json";
    if (file_exists($file)) {
      if ((filemtime($file)+$this->_get_caching_time())>time()) {
        return json_decode($this->_get_file_contents($file), true);
      }
      @unlink($file);
    }

    switch ($ver) {
    case 4:
      exec("whois -h whois.radb.net '!gas$num'", $arr); break;
    case 6:
      exec("whois -h whois.radb.net '!6as$num'", $arr); break;
    }

    if (empty($arr) || count($arr)===1) return array();
    array_shift($arr); # Remove the first element
    array_pop($arr);   # Remove the last element
    $new=array();
    foreach ($arr as $line) {
      if (strpos($line, ' ')!==false) {
        $tmparr=explode(' ', $line);
        foreach ($tmparr as $cidr) {
          if ($this->_valid_AnyCidr($cidr)) $new[]=$cidr;
        }
      }
    }
    unset($arr);
    $new=array_unique($new);
    switch ($ver) {
    case 4:
      natsort($new); break;
    case 6:
      $new=$this->_sort_cidr_array($new); break;
    }
    $new=array_values($new);
    $this->_put_file_contents($file, json_encode($new, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    return $new;
  }


  /**
   * Populates the result array with values.
   * @param  array
   * @return array
   */
  private static function _make_result_array($values)
  {
    $keys=array(
      'as_timestamp',
      'as_prefix_bin',
      'as_prefix',
      'as_country_code',
      'as_number',
      'as_isp',
      'as_nic',
      'as_alloc',
      'as_source',
    );
    return array_combine($keys, $values);
  }


  /**
   * Reverses order of chars of IPv4.
   * @param  string
   * @return string
   */
  private function _get_rev_addr_four($addr)
  {
    $tok=explode('.', $addr);
    $tok=array_reverse($tok);
    return implode('.', $tok);
  }


  /**
   * Reverses order of chars of IPv6.
   * @param  string
   * @return string
   */
  private function _get_rev_addr_six($addr)
  {
    $addr=$this->_expand_addr_six($addr);
    $tok=str_replace(':', '', $addr);
    $tok=str_split($tok);
    $tok=array_reverse($tok);
    return implode('.', $tok);
  }


  /**
   * Sorts array of cidrs.
   * @param  array
   * @return array
   */
  private function _sort_cidr_array($arr)
  {
    $new=array();
    foreach ($arr as $key=>$cidr) {
      list($addr, $bits)=explode("/", $cidr);
      $new[inet_pton($addr)]=$bits;
    }
    unset($arr);
    ksort($new);
    $out=array();
    foreach($new as $binstr=>$bits) {
      $addr=inet_ntop($binstr);
      $out[]="$addr/$bits";
    }
    return $out;
  }


  /**
   * Validates any cidr, ie, v4 or v6.
   * @param  string
   * @return boolean
   * @throws \Exception
   */
  private function _valid_AnyCidr($cidr)
  {
    return (preg_match('/^([1-9]|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.){2}(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\/([6-9]|[1-2]\d|3[12])$|^2[0-9a-f]{3}:(:|:?[0-9a-f]{1,4}){1,7}\/([1-9][0-9]|1[01][0-9]|12[0-8])$/', $cidr)) ? true: false;
  }


  /**
   * Address validation (both v4 and v6).
   * @param  string
   * @return boolean
   */
  private function _valid_ip($addr)
  {
    return (preg_match('/^([1-9]|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.){2}(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])$|^2[0-9a-f]{3}:(:|:?[0-9a-f]{1,4}){1,7}$/i', $addr)) ? true: false;
  }


  /**
   * Returns array of prefixes for given array of AS numbers.
   * @param  array
   * @param  integer
   * @return array
   */
  public function ArrayAsn2prefix($arr, $ver)
  {
    $new=array();
    foreach ($arr as $num) {
      if ($arr=$this->Asn2prefix($num, $ver)) {
        $new=array_merge($new, $arr);
      }
    }
    if (!empty($new)) {
      $new=array_unique($new);
      $new=array_values($new);
      natsort($new);
      return $new;
    }
    return array();
  }


  /**
   * Returns version of IP.
   * @param  string
   * @return integer
   */
  private function _get_ipv($addr)
  {
    return (strpos($addr, ':')===false) ? 4: 6;
  }


  /**
   * Converts IP address into binary string.
   * @param  string
   * @return string
   */
  private function _ip2binstr($addr)
  {
    if (strpos($addr, ':')===false) {
      $addr=inet_pton($addr);
      $value=unpack('H*', $addr);
      $addr=base_convert($value[1], 16, 2);
      return str_pad($addr, 32, '0', STR_PAD_LEFT);
    }
    $addr_n=inet_pton($addr);
    $bits=15;
    $new=0;
    while ($bits>=0) {
      $bin=sprintf("%08b", ord($addr_n[$bits]));
      if ($new) {
        $new=$bin.$new;
      }
      else {
        $new=$bin;
      }
      $bits--;
    }
    return $new;
  }


  /**
   * Converts cidr prefix into binary string.
   * @param  string
   * @return string
   */
  private function _cidr2binprefix($cidr)
  {
    list($addr, $mask)=explode('/', $cidr);
    return substr($this->_ip2binstr($addr), 0, $mask);
  }


  /**
   * Matches two binary strings, to be used for determining whether
   * an address matches a cidr prefix.
   * @param  string
   * @param  string
   * @return boolean
   */
  private function _match_bin_strings($needle, $haystack)
  {
    return substr($needle, 0, strlen($haystack))===$haystack;
  }


  /**
   * Expands compressed IPv6.
   * @param  string
   * @return string
   */
  private function _expand_addr_six($addr)
  {
    if (strpos($addr, '::')!==false) {
      $part=explode('::', $addr);
      $part[0]=explode(':', $part[0]);
      $part[1]=explode(':', $part[1]);
      $missing=array();
      for ($i=0; $i<(8-(count($part[0])+count($part[1]))); $i++) {
        array_push($missing, '0000');
      }
      $missing=array_merge($part[0], $missing);
      $part=array_merge($missing, $part[1]);
    }
    else {
      $part=explode(":", $addr);
    }
    foreach ($part as &$p) {
      $p=str_pad($p, 4, '0', STR_PAD_LEFT);
    }
    unset($p);
    $result=implode(':', $part);
    if (strlen($result)===39) {
      return $result;
    }
    return false;
  }


  /**
   * Returns the first element before a glue.
   * Eg, "first-second-last" > "first"
   * @param  string
   * @param  string
   * @return string
   */
  private function _first_before_glue($glue, $str)
  {
    return (strpos($str, $glue)===false) ? $str: strstr($str, $glue, true);
  }


  /**
   * Saves string inside a file
   * @param  string  $file
   * @param  string  $str
   * @return mixed
   */
  private function _put_file_contents($file, $str)
  {
    $fileObj=new SplFileObject($file, 'w');
    while (!$fileObj->flock(LOCK_EX)) {
      usleep(1);
    }
    $bytes=$fileObj->fwrite($str);
    $fileObj->flock(LOCK_UN);
    return $bytes;
  }


  /**
   * Retrieves contents of a file
   * @param  string  $file
   * @return string
   */
  private function _get_file_contents($file)
  {
    $fileObj=new SplFileObject($file, 'r');
    while (!$fileObj->flock(LOCK_SH)) {
      usleep(1);
    }
    $str=$fileObj->fread($fileObj->getSize());
    $fileObj->flock(LOCK_UN);
    return $str;
  }


  /**
   * Returns caching time in seconds.
   * @return integer
   */
  private function _get_caching_time()
  {
    if (!empty($this->_caching_time)) {
      return $this->_caching_time;
    }
    $this->_caching_time=(!empty($this->cache_time) && is_integer($this->cache_time)) ? $this->cache_time: self::CACHING_TIME_DEFAULT;
    return $this->_caching_time;
  }


}
