<?php namespace tanglePHP\Core\Crypto;

use tanglePHP\Core\Exception\Crypto as CryptoException;
use tanglePHP\Core\Helper\Converter;

/**
 * Class Bech32
 *
 * @package      tanglePHP\Core\Crypto
 * @author       Stefan Braun <stefan.braun@tanglePHP.com>
 * @copyright    Copyright (c) 2022, StefanBraun
 * @version      2022.08.31-1910
 */
final class Bech32 {
  /**
   * @var array|int[]
   */
  static public array $_charsetKey = [
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    15,
    -1,
    10,
    17,
    21,
    20,
    26,
    30,
    7,
    5,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    29,
    -1,
    24,
    13,
    25,
    9,
    8,
    23,
    -1,
    18,
    22,
    31,
    27,
    19,
    -1,
    1,
    0,
    3,
    16,
    11,
    28,
    12,
    14,
    6,
    4,
    2,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    29,
    -1,
    24,
    13,
    25,
    9,
    8,
    23,
    -1,
    18,
    22,
    31,
    27,
    19,
    -1,
    1,
    0,
    3,
    16,
    11,
    28,
    12,
    14,
    6,
    4,
    2,
    -1,
    -1,
    -1,
    -1,
    -1,
  ];
  /**
   * @var string
   */
  static public string $_charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
  /**
   * @var array|int[]
   */
  static public array $_generator = [
    0x3b6a57b2,
    0x26508e6d,
    0x1ea119fa,
    0x3d4233dd,
    0x2a1462b3,
  ];

  /**
   * @param array $values
   * @param int   $numValues
   *
   * @return int
   */
  static public function polyMod(array $values, int $numValues): int {
    $_chk = 1;
    for($_i = 0; $_i < $numValues; $_i++) {
      $_t   = $_chk >> 25;
      $_chk = ($_chk & 0x1ffffff) << 5 ^ $values[$_i];
      for($_j = 0; $_j < 5; $_j++) {
        $_v   = (($_t >> $_j) & 1) ? self::$_generator[$_j] : 0;
        $_chk ^= $_v;
      }
    }

    return $_chk;
  }

  /**
   * Creation of a character array for the checksum formation.
   *
   * @param string $hrp
   * @param int    $hrpLen
   *
   * @return array
   */
  static public function hrpExpand(string $hrp, int $hrpLen): array {
    for($i = 0; $i < $hrpLen; $i++) {
      $_ord   = ord($hrp[$i]);
      $_ep1[] = $_ord >> 5;
      $_ep2[] = $_ord & 31;
    }

    return array_merge($_ep1 ?? [], [0], $_ep2 ?? []);
  }

  /**
   * @param string $hrp
   * @param array  $convertedDataChars
   *
   * @return array
   */
  static public function createChecksum(string $hrp, array $convertedDataChars): array {
    $_values  = array_merge(self::hrpExpand($hrp, strlen($hrp)), $convertedDataChars);
    $_polyMod = self::polyMod(array_merge($_values, [
        0,
        0,
        0,
        0,
        0,
        0,
      ]), count($_values) + 6) ^ 1;
    for($_i = 0; $_i < 6; $_i++) {
      $_ret[$_i] = ($_polyMod >> 5 * (5 - $_i)) & 31;
    }

    return $_ret ?? [];
  }

  /**
   * Verifiy checksum
   *
   * @param string $hrp
   * @param array  $convertedDataChars
   *
   * @return bool
   */
  static public function verifyChecksum(string $hrp, array $convertedDataChars): bool {
    $_a = array_merge(self::hrpExpand($hrp, strlen($hrp)), $convertedDataChars);

    return self::polyMod($_a, count($_a)) === 1;
  }

  /**
   * Encode Bech32
   *
   * @param string $hrp
   * @param array  $combinedDataChars
   *
   * @return string
   */
  static public function encode(string $hrp, array $combinedDataChars): string {
    $_char    = array_merge($combinedDataChars, self::createChecksum($hrp, $combinedDataChars));
    for($_i = 0, $_n = count($_char); $_i < $_n; $_i++) {
      $_encoded[$_i] = self::$_charset[$_char[$_i]];
    }

    return "{$hrp}1" . implode('', $_encoded ?? []);
  }

  /**
   * Decode Bech32
   *
   * @param string $string
   *
   * @return array
   * @throws CryptoException
   */
  static public function decode(string $string): array {
    $_len = strlen($string);
    if($_len < 8) {
      throw new CryptoException("Bech32 is too short");
    }
    $_chars  = array_values(Converter::string2ByteArray($string));
    $_hUpper = false;
    $_hLower = false;
    $_pos    = -1;
    for($_i = 0; $_i < $_len; $_i++) {
      $_x = $_chars[$_i];
      if($_x < 33 || $_x > 126) {
        throw new CryptoException('Character is out of range');
      }
      if($_x >= 0x61 && $_x <= 0x7a) {
        $_hLower = true;
      }
      if($_x >= 0x41 && $_x <= 0x5a) {
        $_hUpper = true;
        $_x      = $_chars[$_i] = $_x + 0x20;
      }
      if($_x === 0x31) {
        $_pos = $_i;
      }
    }
    if($_hUpper && $_hLower) {
      throw new CryptoException('higher and lower case mixture not allowed');
    }
    if($_pos === -1) {
      throw new CryptoException("No separator character");
    }
    if($_pos < 1) {
      throw new CryptoException("HRP is empty");
    }
    if(($_pos + 7) > $_len) {
      throw new CryptoException('Checksum is to short');
    }
    $_hrp  = pack("C*", ...array_slice($_chars, 0, $_pos));
    $_data = [];
    for($i = $_pos + 1; $i < $_len; $i++) {
      $_data[] = ($_chars[$i] & 0x80) ? -1 : self::$_charsetKey[$_chars[$i]];
    }
    if(!self::verifyChecksum($_hrp, $_data)) {
      throw new CryptoException('Invalid checksum');
    }

    return [
      $_hrp,
      array_slice($_data, 0, -6),
    ];
  }
}