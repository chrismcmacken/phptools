<?php

// Class from PHP's own documentation
// http://www.php.net/manual/en/stream.streamwrapper.example-1.php
// Added code to avoid warnings

class VariableStream {
    var $position;
    var $varname;

    function stream_open($path, $mode, $options, &$opened_path) {
        $url = parse_url($path);
        $this->varname = $url["host"];
        $this->position = 0;
        return true;
    }

    function stream_read($count) {
        $ret = substr($GLOBALS[$this->varname], $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    function stream_write($data) {
        $left = substr($GLOBALS[$this->varname], 0, $this->position);
        $right = substr($GLOBALS[$this->varname], $this->position + strlen($data));
        $GLOBALS[$this->varname] = $left . $data . $right;
        $this->position += strlen($data);
        return strlen($data);
    }

    function stream_tell() {
        return $this->position;
    }

    function stream_eof() {
        return $this->position >= strlen($GLOBALS[$this->varname]);
    }

    function stream_seek($offset, $whence) {
		if ($whence === SEEK_SET && $offset < strlen($GLOBALS[$this->varname]) && $offset >= 0) {
			 $this->position = $offset;
			 return true;
		}

		if ($whence === SEEK_CUR && $offset >= 0) {
			 $this->position += $offset;
			 return true;
		}

		if ($whence === SEEK_END && strlen($GLOBALS[$this->varname]) + $offset >= 0) {
			 $this->position = strlen($GLOBALS[$this->varname]) + $offset;
			 return true;
		}

		return false;
    }

    function stream_metadata($path, $option, $var) {
        if ($option === STREAM_META_TOUCH) {
            $url = parse_url($path);
            $varname = $url["host"];

            if (! isset($GLOBALS[$varname])) {
                $GLOBALS[$varname] = '';
            }

            return true;
        }

        return false;
    }

	function stream_stat() {
		return array(
			0, // device number
			0, // inode number
			0, // inode protection mode
			0, // number of links
			0, // userid of owner
			0, // groupid of owner
			0, // device type, if inode device
			strlen($GLOBALS[$this->varname]), // size in bytes
			time(), // atime - time of last access
			time(), // mtime - time of last modification
			time(), // ctime - time of creation
			-1, // block size of filesystem I/O
			-1 // number of 512-byte blocks allocated
		);
	}
}

stream_wrapper_register("var", "VariableStream")
    or die("Failed to register protocol");
