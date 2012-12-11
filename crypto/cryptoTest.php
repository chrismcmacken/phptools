<?PHP
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

require_once('Crypto.php');

class CryptoTest extends PHPUnit_Framework_TestCase {
	/**
	 * Make sure the IV generation appears to work
	 *
	 * @dataProvider dataMakeIv
	 */
	public function testMakeIv($mode, $length) {
		$crypto = new Crypto('boo moo blah', $mode);
		$iv1 = $crypto->makeIv();
		$this->assertEquals($length, strlen($iv1), 'First IV had wrong length');
		$iv2 = $crypto->makeIv();
		$this->assertEquals($length, strlen($iv2), 'Second IV had wrong length');
		$this->assertTrue($iv1 != $iv2, 'Generated IVs should never match');
	}

	public function dataMakeIv() {
		return array(
			'RIJNDAEL_256' => array(
				MCRYPT_RIJNDAEL_256,
				32,
			),
			'TWOFISH' => array(
				MCRYPT_TWOFISH,
				16,
			),
		);
	}

	/**
	 * Encryption with known IVs should match
	 *
	 * @dataProvider dataEncrypt
	 */
	public function testEncrypt($expected, $iv, $plaintext) {
		$crypto = new Crypto('Your crypto key would normally go here.');
		$encrypted = $crypto->encrypt($plaintext, $iv);
		$this->assertEquals($expected, $encrypted);
	}

	public function dataEncrypt() {
		return array(
			'k1m1' => array(
				'1:a2tkZGVlZmY4ODc3bm4gIA==:9nfoCxxT4I+jZg1okSQX9pOiMAnalR8hLZ+U3G97gKTIGAlvzePGYp/pr63/3JBxMyQSiQ==',
				'kkddeeff8877nn  ',
				'Mary had a little lamb, its fleece was white as snow',
			),
			'k1m2' => array(
				'1:a2tkZGVlZmY4ODc3bm4gIA==:38yOdQi7rQ==',
				'kkddeeff8877nn  ',
				'dweezel',
			),
			'k2m1' => array(
				'1:MDAwMDk5OTk4ODg4Nzc3Nw==:m//8y2dTsG2+v14p9Iq0Vq2I8IpVROtlklwgSfrWmepZJR+vJZIrpUxfWlsj6o4WhU9CSQ==',
				'0000999988887777',
				'Mary had a little lamb, its fleece was white as snow',
			),
		);
	}


	/**
	 * Decryption of encoded data for all of the versions
	 *
	 * @dataProvider dataDecrypt
	 */
	public function testDecrypt($expected, $encrypted, $key, $cipher = null, $mode = null) {
		$crypto = new Crypto($key, $cipher, $mode);
		$decrypted = $crypto->decrypt($encrypted);
		$this->assertEquals($expected, $decrypted);
	}

	public function dataDecrypt() {
		return array(
			array(
				'Mary had a little lamb, its fleece was white as snow',
				'1:a2tkZGVlZmY4ODc3bm4gIA==:9nfoCxxT4I+jZg1okSQX9pOiMAnalR8hLZ+U3G97gKTIGAlvzePGYp/pr63/3JBxMyQSiQ==',
				'Your crypto key would normally go here.',
			),
		);
	}
}
