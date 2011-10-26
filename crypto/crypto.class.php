<?php
/*
Copyright (c) 2011 individual committers of the code

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

Except as contained in this notice, the name(s) of the above copyright holders 
shall not be used in advertising or otherwise to promote the sale, use or other
dealings in this Software without prior written authorization.

The end-user documentation included with the redistribution, if any, must 
include the following acknowledgment: "This product includes software developed 
by contributors", in the same place and form as other third-party
acknowledgments. Alternately, this acknowledgment may appear in the software
itself, in the same form and location as other such third-party
acknowledgments.
*/


class Crypto {
	const VERSION = 1;
	protected $cipher = null;  // MCRYPT_* constant
	private $key = null;  // Well, nothing's really private in PHP
	protected $mode = null;  // MCRYPT_* constant

	public function __construct($keySeed, $cipher = null, $mode = null) {
		if (is_null($cipher)) {
			// MCRYPT_RIJNDAEL_256 (AES-256) is nice, but requires 32-byte IVs
			// TWOFISH and AES were both deemed "secure" by US government
			$cipher = MCRYPT_TWOFISH;
		}

		if (is_null($mode)) {
			// Avoid block modes for size, avoid ECB due to problems
			$mode = MCRYPT_MODE_CFB;
		}

		$this->cipher = $cipher;
		$this->mode = $mode;
		$this->key = $this->makeKey($keySeed);
	}

	public function decrypt($str) {
		$parts = explode(':', $str);
		$version = (integer) $parts[0];

		if (0 === $version) {
			throw new ErrorException('Version not set correctly');
		}

		if ($version > Crypto::VERSION) {
			throw new ErrorException('Can not handle data from version ' . $version);
		}

		/* Version 1:
		 * Encoded fields = BASE64(IV):BASE64(CRYPT(plain_string))
		 */
		$iv = base64_decode($parts[1]);
		$encrypted = base64_decode($parts[2]);
		$data = mcrypt_decrypt($this->cipher, $this->key, $encrypted, $this->mode, $iv);
		return $data;
	}

	public function encrypt($plain, $iv = null) {
		if (is_null($iv)) {
			$iv = $this->makeIv();
		}

		$encrypted = mcrypt_encrypt($this->cipher, $this->key, $plain, $this->mode, $iv);
		$out = Crypto::VERSION . ':' . base64_encode($iv) . ':' . base64_encode($encrypted);
		return $out;
	}


	public function makeKey($seed) {
		$size = mcrypt_get_key_size($this->cipher, $this->mode);

		if (strlen($seed) == $size) {
			// No need to hash
			return $seed;
		}

		$hash = hash('sha512', $seed);
		
		while (strlen($hash) < $size * 2) {
			$hash .= hash('sha512', $hash . $seed);
		}

		$hash = substr($hash, 0, $size * 2);
		$hash = pack('H*', $hash);
		return $hash;
	}


	public function makeIv() {
		$size = mcrypt_get_iv_size($this->cipher, $this->mode);
		$iv = mcrypt_create_iv($size, MCRYPT_DEV_URANDOM);  // Use non-blocking random source of data
		return $iv;
	}
}
