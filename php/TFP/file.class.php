<?php
class VipFileIterator implements Iterator {
    protected $file = null;
    protected $key = 0;
    protected $current;

    public function __construct($filePath = '' ) {
        if( !isset( $filePath['0'] ) ){
            echo "filePath is empty string";
            return;
        }

        if( file_exists($filePath) )
            $this->file = fopen($filePath, 'r');
        else
            echo "file doesn't exists.Path:{$filePath}";
    }

    public function __destruct() {
        if( is_resource($this->file) )
            fclose($this->file);
    }

    public function rewind() {
        if( is_resource($this->file) ){
            rewind($this->file);
            $this->current = fgets($this->file);
            $this->key = 0;
        }
    }

    public function valid() {
        if( $this->file == null )
            return false;

        return !feof($this->file);
    }

    public function key() {
        return $this->key;
    }

    public function current() {
        return $this->current;
    }

    public function next() {
        $this->current = fgets($this->file);
        $this->key++;
    }
}
?>