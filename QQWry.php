<?php
/**
 *  QQWry.php
 *
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2014-06-02 21:23
 */

class QQWry {
    private $fp = null;
    private $start = 0, $end = 0, $ctype = 0;
    private $fstart = 0, $lstart = 0, $offset = 0;
    // QQWry instance
    private static $instance;
    /**
     * Returns QQWry instance.
     *
     * @static
     * @param string $dat_file
     * @return QQWry
     */
    public static function &instance($dat_file) {
        if (!self::$instance) {
            self::$instance = new QQWry($dat_file);
        }
        return self::$instance;
    }

    /**
     * 翻译
     *
     * @param string $text
     * @return mixed
     */
    private static function __($text) {
        if (function_exists('__')) {
            return __($text);
        } else {
            return $text;
        }
    }

    public function __construct($dat_file) {
        if (is_file($dat_file)) {
            $this->fp = fopen($dat_file,'rb');
        }
    }

    private function init() {
        $this->start = $this->end = $this->ctype = $this->fstart = $this->lstart = $this->offset = 0;
    }

    private function start($number) {
        fseek($this->fp, $this->fstart + $number * 7);
        $buf = fread($this->fp, 7);
        $this->offset = ord($buf[4]) + (ord($buf[5]) * 256) + (ord($buf[6]) * 256 * 256);
        $this->start  = ord($buf[0]) + (ord($buf[1]) * 256) + (ord($buf[2]) * 256 * 256) + (ord($buf[3]) * 256 * 256 * 256);
        return $this->start;
    }

    private function end() {
        fseek($this->fp, $this->offset);
        $buf = fread($this->fp, 5);
        $this->end   = ord($buf[0]) + (ord($buf[1]) * 256) + (ord($buf[2]) * 256 * 256) + (ord($buf[3]) * 256 * 256 * 256);
        $this->ctype = ord($buf[4]);
        return $this->end;
    }

    private function get_addr() {
        $result = array();
        switch ($this->ctype) {
            case 1:
            case 2:
                $result['Country'] = $this->get_str($this->offset + 4);
                $result['Local']   = (1 == $this->ctype) ? '' : $this->get_str($this->offset + 8);
                break;
            default :
                $result['Country'] = $this->get_str($this->offset + 4);
                $result['Local']   = $this->get_str(ftell($this->fp));
        }
        return $result;
    }

    private function get_str($offset) {
        $result = '';
        while (true) {
            fseek($this->fp, $offset);
            $flag = ord(fgetc($this->fp));
            if ($flag == 1 || $flag == 2) {
                $buf = fread($this->fp, 3);
                if ($flag == 2) {
                    $this->ctype  = 2;
                    $this->offset = $offset - 4;
                }
                $offset = ord($buf[0]) + (ord($buf[1]) * 256) + (ord($buf[2]) * 256 * 256);
            } else {
                break;
            }

        }
        if ($offset < 12) return $result;
        fseek($this->fp, $offset);
        while (true) {
            $c = fgetc($this->fp);
            if (ord($c[0]) == 0) break;
            $result.= $c;
        }
        return $result;
    }

    public function ip2addr($ipaddr) {
        if (!$this->fp) return $ipaddr;
        if (strpos($ipaddr, '.') !== false) {
            if (preg_match('/^(127)/', $ipaddr))
                return self::__('本地');
            $ip = sprintf('%u',ip2long($ipaddr));
        } else {
            $ip = $ipaddr;
        }
        $this->init();
        fseek($this->fp, 0);
        $buf = fread($this->fp, 8);
        $this->fstart = ord($buf[0]) + (ord($buf[1])*256) + (ord($buf[2])*256*256) + (ord($buf[3])*256*256*256);
        $this->lstart = ord($buf[4]) + (ord($buf[5])*256) + (ord($buf[6])*256*256) + (ord($buf[7])*256*256*256);

        $count = floor(($this->lstart - $this->fstart) / 7);
        if ($count <= 1) {
            fclose($this->fp);
            return $ipaddr;
        }

        $start = 0;
        $end = $count;
        while ($start < $end - 1)
        {
            $number = floor(($start + $end) / 2);
            $this->start($number);

            if ($ip == $this->start) {
                $start = $number;
                break;
            }
            if ($ip > $this->start)
                $start = $number;
            else
                $end = $number;
        }
        $this->start($start);
        $this->end();

        if (($this->start <= $ip) && ($this->end >= $ip)) {
            $result = $this->get_addr();
        } else {
            $result = array(
                'Country' => self::__('未知'),
                'Local'   => '',
            );
        }
        $result = trim(implode(' ', $result));
        $result = iconv('GBK', 'UTF-8', $result);
        return $result;
    }

    public function __destruct() {
        if ($this->fp) fclose($this->fp);
    }
} 