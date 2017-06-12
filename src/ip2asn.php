<?php
/**
 * IP 2 ASN
 * Maps IP address to ASN.
 *
 * @version    0.3 (2017-06-12 06:03:00 GMT)
 * @author     Peter Kahl <peter.kahl@colossalmind.com>
 * @copyright  2015-2017 Peter Kahl
 * @license    Apache License, Version 2.0
 *
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

class ip2asn {

  /**
   * Cache directory
   *
   */
  public $cacheDir;

  #===================================================================

  public function getAsn($ip, $bin = '', $ver = '') {
    $ip = trim($ip);
    if (empty($ver)) {
      $ver = $this->GetIPversion($ip);
    }
    if (empty($bin)) {
      $bin = $this->AnyIPtoBinary($ip, $ver);
    }
    #----
    if ($ver == 4) {
      $readFile = $this->cacheDir .'/ASN4-CACHE.db';
    }
    else {
      $readFile = $this->cacheDir .'/ASN6-CACHE.db';
    }
    #----
    if (file_exists($readFile)) {
      $file_obj_gtn = new SplFileObject($readFile);
      while (!$file_obj_gtn->eof()) {
        $line = $file_obj_gtn->fgets();
        $line = trim($line);
        if (strpos($line, '|') !== false) {
          list($epoch, $prefixBin, $prefix, $code, $num, $isp, $nic, $alloc) = explode('|', $line);
          if ($this->MatchBinaryStrings($bin, $prefixBin)) {
            return array(
              'as_number'       => $num,
              'as_prefix'       => $prefix,
              'as_prefix_bin'   => $prefixBin,
              'as_country_code' => $code,
              'as_isp'          => $isp,
              'as_nic'          => $nic,
              'as_alloc'        => $alloc,
            );
          }
        }
      }
    }
    #----
    for ($k = 0; $k < 4; $k++) {
      $arr = $this->getCymruAsn($ip);
      if (!empty($arr)) {
        break;
      }
    }
    #----
    if (empty($arr)) {
      return array(
        'as_number'       => '',
        'as_prefix'       => '',
        'as_prefix_bin'   => '',
        'as_country_code' => '',
        'as_isp'          => '',
        'as_nic'          => '',
        'as_alloc'        => '',
      );
    }
    #----
    if (empty($arr['as_prefix_bin']) || $arr['as_prefix_bin'] == 'NA') {
      $arr['as_prefix_bin'] = $bin;
    }
    #----
    $str = time().'|'.$arr['as_prefix_bin'].'|'.$arr['as_prefix'].'|'.$arr['as_country_code'].'|'.$arr['as_number'].'|'.$arr['as_isp'].'|'.$arr['as_nic'].'|'.$arr['as_alloc'];
    file_put_contents($readFile, $str . PHP_EOL, FILE_APPEND | LOCK_EX);
    return $arr;
  }

  #===================================================================
/*
AS      | IP               | BGP Prefix          | CC | Registry | Allocated  | AS Name
4760    | 219.76.0.1       | 219.76.0.0/19       | HK | apnic    | 2002-03-19 | HKTIMS-AP PCCW Limited, HK
*/
  public function getCymruAsn($ip) {
    #----
    exec('whois -h whois.cymru.com " -v '.$ip.'"', $arr);
    #----
    if (empty($arr)) {
      return false;
    }
    list($num, $ip, $prefix, $code, $nic, $alloc, $isp) = explode('|', $arr[1]);
    #----
    $prefix = trim($prefix);
    if ($prefix == 'NA') {
      $prefixBin = 'NA';
    }
    else {
      $prefixBin = $this->AnyCIDRtoBinaryPrefix($prefix);
    }
    $alloc = trim($alloc);
    if (empty($alloc)) {
      $alloc = 'NA';
    }
    #----
    return array(
      'as_number'       => trim($num),
      'as_prefix'       => trim($prefix),
      'as_prefix_bin'   => $prefixBin,
      'as_country_code' => trim($code),
      'as_isp'          => trim($isp),
      'as_nic'          => preg_replace('/NCC$/', '', strtoupper(trim($nic))),
      'as_alloc'        => $alloc,
    );
  }

  #=====================================================================

  public function GetIPversion($ip) {
    if (strpos($ip, ':') !== false) {
      return 6;
    }
    if (strpos($ip, '.') !== false) {
      return 4;
    }
    throw new Exception('Invalid IP address notation');
  }

  #===================================================================

  public function AnyIPtoBinary($ip, $ver = '') {
    if (empty($ver)) {
      $ver = $this->GetIPversion($ip);
    }
    if ($ver == 4) {
      return $this->IPv4toBinary($ip);
    }
    if ($ver == 6) {
      return $this->IPv6toBinary($ip);
    }
    throw new Exception('Illegal value argument ver');
  }

  #===================================================================

  public function IPv4toBinary($ip) {
    $ip = trim($ip);
    $ip = $this->bstr2bin(inet_pton($ip));
    return str_pad($ip, 32, '0', STR_PAD_LEFT);
  }

  #===================================================================

  private function bstr2bin($input) {
    $value = unpack('H*', $input);
    return base_convert($value[1], 16, 2);
  }

  #===================================================================

  public function IPv6toBinary($ipv6) {
    $ip_n = inet_pton($ipv6);
    $bits = 15;
    $new = 0;
    while ($bits >= 0) {
      $bin = sprintf("%08b", ord($ip_n[$bits]));
      if ($new) {
        $new = $bin . $new;
      }
      else {
        $new = $bin;
      }
      $bits--;
    }
    return $new;
  }

  #===================================================================

  public function AnyCIDRtoBinaryPrefix($cidr, $ver = '') {
    list($ip, $mask) = explode('/', $cidr);
    #----
    if (empty($ver)) {
      $ver = $this->GetIPversion($ip);
    }
    #----
    if ($ver == 4) {
      return substr($this->IPv4toBinary($ip), 0, $mask);
    }
    if ($ver == 6) {
      return substr($this->IPv6toBinary($ip), 0, $mask);
    }
    throw new Exception('Illegal value argument ver');
  }

  #===================================================================

  /**
   * Matches 2 binary strings
   *
   */
  public function MatchBinaryStrings($needle, $haystack) {
    return substr($needle, 0, strlen($haystack)) == $haystack;
  }

  #===================================================================
}