<?php
/**
 *  LazyFunc.php
 *
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2014-06-02 21:13
 */
/**
 * 块开始
 *
 * @return void
 */
function ob_block_start() {
    global $lazy_tmp_content;
    $lazy_tmp_content = ob_get_contents();
    ob_clean();
    ob_start();
}

/**
 * get ob content for tag
 *
 * @param string $tag
 * @param string $join
 * @return array|null
 */
function ob_get_content($tag, $join = "\r\n") {
    global $lazy_ob_contents;
    if (isset($lazy_ob_contents[$tag])) {
        array_multisort($lazy_ob_contents[$tag]['order'], SORT_DESC, $lazy_ob_contents[$tag]['content']);
        return implode($join, $lazy_ob_contents[$tag]['content']);
    }
    return null;
}

/**
 * set ob content for tag
 *
 * @param string $tag
 * @param int $order
 * @return array|null
 */
function ob_block_end($tag, $order = 0) {
    global $lazy_ob_contents, $lazy_tmp_content;
    $content = ob_get_contents();
    ob_clean();
    if (!isset($lazy_ob_contents[$tag])) $lazy_ob_contents[$tag] = array();
    $lazy_ob_contents[$tag]['content'][] = $content;
    $lazy_ob_contents[$tag]['order'][] = $order;
    if ($lazy_tmp_content) {
        echo $lazy_tmp_content;
        $lazy_tmp_content = '';
    }
    return ob_get_content($tag);
}

/**
 * 添加过滤器
 *
 * @param string $tag
 * @param string $function
 * @param int $priority
 * @param int $accepted_args
 * @return bool
 */
function add_filter($tag, $function, $priority = 10, $accepted_args = 1) {
    global $lazy_filter;
    static $filter_id_count = 0;
    if (is_string($function)) {
        $idx = $function;
    } else {
        if (is_object($function)) {
            // Closures are currently implemented as objects
            $function = array($function, '');
        } else {
            $function = (array)$function;
        }
        if (is_object($function[0])) {
            // Object Class Calling
            if (function_exists('spl_object_hash')) {
                $idx = spl_object_hash($function[0]) . $function[1];
            } else {
                $idx = get_class($function[0]) . $function[1];
                if (!isset($function[0]->lwp_filter_id)) {
                    $idx .= isset($lazy_filter[$tag][$priority]) ? count((array)$lazy_filter[$tag][$priority])
                        : $filter_id_count;
                    $function[0]->lwp_filter_id = $filter_id_count;
                    ++$filter_id_count;
                } else {
                    $idx .= $function[0]->lwp_filter_id;
                }
            }
        } else if (is_string($function[0])) {
            // Static Calling
            $idx = $function[0] . $function[1];
        }
    }
    $lazy_filter[$tag][$priority][$idx] = array('function' => $function, 'accepted_args' => $accepted_args);
    return true;
}

/**
 * Call the functions added to a filter hook.
 *
 * @param string $tag
 * @param mixed $value
 * @return mixed
 */
function apply_filters($tag, $value) {
    global $lazy_filter;

    if (!isset($lazy_filter[$tag])) {
        return $value;
    }

    ksort($lazy_filter[$tag]);

    reset($lazy_filter[$tag]);

    $args = func_get_args();

    do {
        foreach ((array)current($lazy_filter[$tag]) as $self)
            if (!is_null($self['function'])) {
                $args[1] = $value;
                $value = call_user_func_array($self['function'], array_slice($args, 1, (int)$self['accepted_args']));
            }

    } while (next($lazy_filter[$tag]) !== false);

    return $value;
}

/**
 * printf as e
 *
 * @return bool|mixed
 */
function e() {
    $args = func_get_args();
    if (count($args) == 1) {
        echo $args[0];
    } else {
        return call_user_func_array('printf', $args);
    }
    return true;
}

/**
 * 转换特殊字符为HTML实体
 *
 * @param   string $str
 * @return  string
 */
function esc_html($str) {
    if (empty($str)) {
        return $str;
    } elseif (is_array($str)) {
        $str = array_map('esc_html', $str);
    } elseif (is_object($str)) {
        $vars = get_object_vars($str);
        foreach ($vars as $key => $data) {
            $str->{$key} = esc_html($data);
        }
    } else {
        $str = htmlspecialchars($str);
    }
    return $str;
}

/**
 * Escapes strings to be included in javascript
 *
 * @param string $str
 * @return mixed
 */
function esc_js($str) {
    return str_replace(
        array("\r", "\n"),
        array('', ''),
        addcslashes(esc_html($str), "'")
    );
}

/**
 * ajax request
 *
 * @return bool
 */
function is_xhr_request() {
    return ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : null)
        || (isset($_POST['X-Requested-With']) ? $_POST['X-Requested-With'] : null)) == 'XMLHttpRequest';
}

/**
 * ipad request
 *
 * @return bool
 */
function is_ipad_request() {
    return strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== false;
}

/**
 * mobile request
 *
 * @return bool
 */
function is_mobile_request() {
    return strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false
    || strpos($_SERVER['HTTP_USER_AGENT'], 'iPod') !== false
    || strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false
    || strpos($_SERVER['HTTP_USER_AGENT'], 'webOS') !== false;
}

/**
 * accept json
 *
 * @return bool
 */
function is_accept_json() {
    return strpos(strtolower((isset($_POST['X-Http-Accept']) ? $_POST['X-Http-Accept'] . ',' : '') . $_SERVER['HTTP_ACCEPT']), 'application/json') !== false;
}

/**
 * 检查数组类型
 *
 * @param array $array
 * @return bool
 */
function is_assoc($array) {
    return (is_array($array) && (0 !== count(array_diff_key($array, array_keys(array_keys($array)))) || count($array) == 0));
}

/**
 * 判断是否为json格式
 *
 * @param $text
 * @return bool
 */
function is_jsoned($text) {
    return preg_match('/^("(\\\.|[^"\\\n\r])*?"|[,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t])+?$/', $text);
}

/**
 * 检查值是否已经序列化
 *
 * @param mixed $data Value to check to see if was serialized.
 * @return bool
 */
function is_serialized($data) {
    // if it isn't a string, it isn't serialized
    if (!is_string($data))
        return false;
    $data = trim($data);
    if ('N;' == $data)
        return true;
    if (!preg_match('/^([adObis]):/', $data, $badions))
        return false;
    switch ($badions[1]) {
        case 'a' :
        case 'O' :
        case 's' :
            if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data))
                return true;
            break;
        case 'b' :
        case 'i' :
        case 'd' :
            if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data))
                return true;
            break;
    }
    return false;
}

/**
 * 根据概率判定结果
 *
 * @param float $probability
 * @return bool
 */
function is_happened($probability) {
    return (mt_rand(1, 100000) / 100000) <= $probability;
}

/**
 * 判断是否汉字
 *
 * @param string $str
 * @return int
 */
function is_hanzi($str) {
    return preg_match('%^(?:
          [\xC2-\xDF][\x80-\xBF]            # non-overlong 2-byte
        | \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
        | \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        | \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}         # planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
        )*$%xs', $str);
}

/**
 * 判断是否搜索蜘蛛
 *
 * @static
 * @return bool
 */
function is_spider() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    if (stripos($user_agent, 'Googlebot') !== false
        || stripos($user_agent, 'Sosospider') !== false
        || stripos($user_agent, 'Baiduspider') !== false
        || stripos($user_agent, 'Baidu-Transcoder') !== false
        || stripos($user_agent, 'Yahoo! Slurp') !== false
        || stripos($user_agent, 'iaskspider') !== false
        || stripos($user_agent, 'Sogou') !== false
        || stripos($user_agent, 'YodaoBot') !== false
        || stripos($user_agent, 'msnbot') !== false
        || stripos($user_agent, 'Sosoimagespider') !== false
    ) {
        return true;
    }
    return false;
}

/**
 * 全角转半角
 *
 * @param string $str
 * @return string
 */
function semiangle($str) {
    $arr = array(
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
        '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
        'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
        'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
        'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
        'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
        'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
        'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
        'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
        'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
        'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
        'ｙ' => 'y', 'ｚ' => 'z',

        'ɑ' => 'a', 'ā' => 'a', 'á' => 'a', 'ǎ' => 'a', 'à' => 'a',
        'ō' => 'o', 'ó' => 'o', 'ǒ' => 'o', 'ò' => 'o',
        'ē' => 'e', 'é' => 'e', 'ê' => 'e', 'ě' => 'e', 'è' => 'e',
        'ī' => 'i', 'í' => 'i', 'ǐ' => 'i', 'ì' => 'i',
        'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u',
        'ü' => 'v', 'ǖ' => 'v', 'ǘ' => 'v', 'ǚ' => 'v', 'ǜ' => 'v',
        'ǹ' => 'n', 'ň' => 'n', 'ḿ' => 'm', 'ɡ' => 'g',


        '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[', '】' => ']',
        '〖' => '[', '〗' => ']', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
        '｛' => '{', '｝' => '}', '《' => '<', '》' => '>',

        '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
        '：' => ':', '。' => '.', '、' => ',', '，' => ',',
        '；' => ';', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
        '｜' => '|', '〃' => '"', '　' => ' ',

    );
    return strtr($str, $arr);
}

/**
 * 全概率计算
 *
 * @param array $input array('a'=>0.5,'b'=>0.2,'c'=>0.4)
 * @param int $pow 小数点位数
 * @return array key
 */
function random($input, $pow = 2) {
    $much = pow(10, $pow);
    $max = array_sum($input) * $much;
    $rand = mt_rand(1, $max);
    $base = 0;
    foreach ($input as $k => $v) {
        $min = $base * $much + 1;
        $max = ($base + $v) * $much;
        if ($min <= $rand && $rand <= $max) {
            return $k;
        } else {
            $base += $v;
        }
    }
    return false;
}

/**
 * 随机字符串
 *
 * @param int $length
 * @param string $charlist
 * @return string
 */
function str_rand($length = 6, $charlist = '0123456789abcdefghijklmnopqrstopwxyz') {
    $charcount = strlen($charlist);
    $str = null;
    for ($i = 0; $i < $length; $i++) {
        $str .= $charlist[mt_rand(0, $charcount - 1)];
    }
    return $str;
}

/**
 * converts a UTF8-string into HTML entities
 *
 * @param string $content the UTF8-string to convert
 * @param bool $encodeTags booloean. TRUE will convert "<" to "&lt;"
 * @return string           returns the converted HTML-string
 */
function utf8tohtml($content, $encodeTags = true) {
    $result = '';
    for ($i = 0; $i < strlen($content); $i++) {
        $char = $content[$i];
        $ascii = ord($char);
        if ($ascii < 128) {
            // one-byte character
            $result .= ($encodeTags) ? htmlentities($char) : $char;
        } else if ($ascii < 192) {
            // non-utf8 character or not a start byte
        } else if ($ascii < 224) {
            // two-byte character
            $result .= htmlentities(substr($content, $i, 2), ENT_QUOTES, 'UTF-8');
            $i++;
        } else if ($ascii < 240) {
            // three-byte character
            $ascii1 = ord($content[$i + 1]);
            $ascii2 = ord($content[$i + 2]);
            $unicode = (15 & $ascii) * 4096 +
                (63 & $ascii1) * 64 +
                (63 & $ascii2);
            $result .= "&#$unicode;";
            $i += 2;
        } else if ($ascii < 248) {
            // four-byte character
            $ascii1 = ord($content[$i + 1]);
            $ascii2 = ord($content[$i + 2]);
            $ascii3 = ord($content[$i + 3]);
            $unicode = (15 & $ascii) * 262144 +
                (63 & $ascii1) * 4096 +
                (63 & $ascii2) * 64 +
                (63 & $ascii3);
            $result .= "&#$unicode;";
            $i += 3;
        }
    }
    return $result;
}

/**
 * 格式化为XML
 *
 * @param string $content
 * @return mixed
 */
function xmlencode($content) {
    if (strlen($content) == 0) return $content;
    return str_replace(
        array('&', "'", '"', '>', '<'),
        array('&amp;', '&apos;', '&quot;', '&gt;', '&lt;'),
        $content
    );
}

/**
 * XMLdecode
 *
 * @param string $content
 * @return mixed
 */
function xmldecode($content) {
    if (strlen($content) == 0) return $content;
    return str_replace(
        array('&amp;', '&apos;', '&quot;', '&gt;', '&lt;'),
        array('&', "'", '"', '>', '<'),
        $content
    );
}

/**
 * 格式化大小
 *
 * @param int $bytes
 * @return string
 */
function format_size($bytes) {
    if ($bytes == 0) return '-';
    $bytes = floatval($bytes);
    $units = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB');
    $i = 0;
    while ($bytes >= 1024) {
        $bytes /= 1024;
        $i++;
    }
    $precision = $i == 0 ? 0 : 2;
    return number_format(round($bytes, $precision), $precision) . $units[$i];
}

/**
 * array_splice 保留key
 *
 * @param array &$input
 * @param int $start
 * @param int $length
 * @param mixed $replacement
 * @return array|bool
 */
function array_ksplice(&$input, $start, $length = 0, $replacement = null) {
    if (!is_array($replacement)) {
        return array_splice($input, $start, $length, $replacement);
    }
    $keys = array_keys($input);
    $values = array_values($input);
    $replacement = (array)$replacement;
    $rkeys = array_keys($replacement);
    $rvalues = array_values($replacement);
    array_splice($keys, $start, $length, $rkeys);
    array_splice($values, $start, $length, $rvalues);
    $input = array_combine($keys, $values);
    return $input;
}

/**
 * 递归地合并一个或多个数组
 *
 *
 * Merges any number of arrays / parameters recursively, replacing
 * entries with string keys with values from latter arrays.
 * If the entry or the next value to be assigned is an array, then it
 * automagically treats both arguments as an array.
 * Numeric entries are appended, not replaced, but only if they are
 * unique.
 *
 * @example:
 *  $result = array_merge_recursive_distinct($a1, $a2, ... $aN)
 *
 * @return array
 */
function array_merge_recursive_distinct() {
    $arrays = func_get_args();
    $base = array_shift($arrays);
    if (!is_array($base)) $base = empty($base) ? array() : array($base);
    foreach ($arrays as $append) {
        if (!is_array($append)) $append = array($append);
        foreach ($append as $key => $value) {
            if (!array_key_exists($key, $base) and !is_numeric($key)) {
                $base[$key] = $append[$key];
                continue;
            }
            if (is_array($value) or is_array($base[$key])) {
                $base[$key] = array_merge_recursive_distinct($base[$key], $append[$key]);
            } else if (is_numeric($key)) {
                if (!in_array($value, $base)) $base[] = $value;
            } else {
                $base[$key] = $value;
            }
        }
    }
    return $base;
}

/**
 * 批量创建目录
 *
 * @param string $path 文件夹路径
 * @param int $mode 权限
 * @return bool
 */
function mkdirs($path, $mode = 0700) {
    if (!is_dir($path)) {
        mkdirs(dirname($path), $mode);
        $error_level = error_reporting(0);
        $result = mkdir($path, $mode);
        error_reporting($error_level);
        return $result;
    }
    return true;
}

/**
 * 删除文件夹
 *
 * @param string $path 要删除的文件夹路径
 * @return bool
 */
function rmdirs($path) {
    $error_level = error_reporting(0);
    if ($dh = opendir($path)) {
        while (false !== ($file = readdir($dh))) {
            if ($file != '.' && $file != '..') {
                $file_path = $path . '/' . $file;
                is_dir($file_path) ? rmdirs($file_path) : unlink($file_path);
            }
        }
        closedir($dh);
    }
    $result = rmdir($path);
    error_reporting($error_level);
    return $result;
}

/**
 * 自动转换字符集 支持数组转换
 *
 * @param string $from
 * @param string $to
 * @param mixed $data
 * @return mixed
 */
function iconvs($from, $to, $data) {
    $from = strtoupper($from) == 'UTF8' ? 'UTF-8' : $from;
    $to = strtoupper($to) == 'UTF8' ? 'UTF-8' : $to;
    if (strtoupper($from) === strtoupper($to) || empty($data) || (is_scalar($data) && !is_string($data))) {
        //如果编码相同或者非字符串标量则不转换
        return $data;
    }
    if (is_string($data)) {
        if (function_exists('iconv')) {
            $to = substr($to, -8) == '//IGNORE' ? $to : $to . '//IGNORE';
            return iconv($from, $to, $data);
        } elseif (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($data, $to, $from);
        } else {
            return $data;
        }
    } elseif (is_array($data)) {
        foreach ($data as $key => $val) {
            $_key = iconvs($from, $to, $key);
            $data[$_key] = iconvs($from, $to, $val);
            if ($key != $_key) {
                unset($data[$key]);
            }
        }
        return $data;
    } else {
        return $data;
    }
}

/**
 * 清除空白
 *
 * @param string $content
 * @return string
 */
function clear_space($content) {
    if (strlen($content) == 0) return $content;
    $r = $content;
    $r = str_replace(array(chr(9), chr(10), chr(13)), '', $r);
    while (strpos($r, chr(32) . chr(32)) !== false || strpos($r, '&nbsp;') !== false) {
        $r = str_replace(array(
                '&nbsp;',
                chr(32) . chr(32),
            ),
            chr(32),
            $r
        );
    }
    return $r;
}

/**
 * 生成guid
 *
 * @param string $mix
 * @param string $hyphen
 * @return string
 */
function guid($mix = null, $hyphen = '-') {
    if (is_null($mix)) {
        $randid = uniqid(mt_rand(), true);
    } else {
        if (is_object($mix) && function_exists('spl_object_hash')) {
            $randid = spl_object_hash($mix);
        } elseif (is_resource($mix)) {
            $randid = get_resource_type($mix) . strval($mix);
        } else {
            $randid = serialize($mix);
        }
    }
    $randid = strtoupper(md5($randid));
    $result = array();
    $result[] = substr($randid, 0, 8);
    $result[] = substr($randid, 8, 4);
    $result[] = substr($randid, 12, 4);
    $result[] = substr($randid, 16, 4);
    $result[] = substr($randid, 20, 12);
    return implode($hyphen, $result);
}

/**
 * 取得客户端的IP
 *
 * @return string
 */
function get_client_ip() {
    $ip = null;
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (!empty($_SERVER['HTTP_VIA '])) {
            $ip = $_SERVER['HTTP_VIA '];
        } else {
            $ip = 'Unknown';
        }
    }
    return $ip;
}

/**
 * 页面跳转
 *
 * @param string $url
 * @param int $status
 * @return array | string
 */
function redirect($url, $status = 302) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        return array('status' => $status, 'location' => $url);
    } else {
        if (!headers_sent()) header("Location: {$url}", true, $status);
        $html = '<!DOCTYPE html>';
        $html .= '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        $html .= '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
        $html .= '<title>' . $url . '</title>';
        $html .= '<script type="text/javascript" charset="utf-8">';
        $html .= 'self.location.replace("' . addcslashes($url, "'") . '");';
        $html .= '</script>';
        $html .= '</head><body></body></html>';
        return $html;
    }
}

/**
 * 内容截取，支持正则
 *
 * $start,$end,$clear 支持正则表达式，“/”斜杠开头为正则模式
 * $clear 支持数组
 *
 * @param string $content 内容
 * @param string $start 开始代码
 * @param string $end 结束代码
 * @param string|array $clear 清除内容
 * @return string
 */
function mid($content, $start, $end = null, $clear = null) {
    if (empty($content) || empty($start)) return null;
    if (strncmp($start, '/', 1) === 0) {
        if (preg_match($start, $content, $args)) {
            $start = $args[0];
        }
    }
    if ($end && strncmp($end, '/', 1) === 0) {
        if (preg_match($end, $content, $args)) {
            $end = $args[0];
        }
    }
    $start_len = strlen($start);
    $result = null;
    $start_pos = stripos($content, $start);
    if ($start_pos === false) return null;
    $length = $end === null ? null : stripos(substr($content, -(strlen($content) - $start_pos - $start_len)), $end);
    if ($start_pos !== false) {
        if ($length === null) {
            $result = trim(substr($content, $start_pos + $start_len));
        } else {
            $result = trim(substr($content, $start_pos + $start_len, $length));
        }
    }
    if ($result && $clear) {
        if (is_array($clear)) {
            foreach ($clear as $v) {
                if (strncmp($v, '/', 1) === 0) {
                    $result = preg_replace($v, '', $result);
                } else {
                    if (strpos($result, $v) !== false) {
                        $result = str_replace($v, '', $result);
                    }
                }
            }
        } else {
            if (strncmp($clear, '/', 1) === 0) {
                $result = preg_replace($clear, '', $result);
            } else {
                if (strpos($result, $clear) !== false) {
                    $result = str_replace($clear, '', $result);
                }
            }
        }
    }
    return $result;
}

/**
 * 格式化URL地址
 *
 * 补全url地址，方便采集
 *
 * @param string $base 页面地址
 * @param string $html html代码
 * @return string
 */
function format_url($base, $html) {
    if (preg_match_all('/<(img|script)[^>]+src=([^\s]+)[^>]*>|<(a|link)[^>]+href=([^\s]+)[^>]*>/iU', $html, $matchs)) {
        $pase_url = parse_url($base);
        $base_host = sprintf('%s://%s', $pase_url['scheme'], $pase_url['host']);
        if (($pos = strpos($pase_url['path'], '#')) !== false) {
            $base_path = rtrim(dirname(substr($pase_url['path'], 0, $pos)), '\\/');
        } else {
            $base_path = rtrim(dirname($pase_url['path']), '\\/');
        }
        $base_url = $base_host . $base_path;
        foreach ($matchs[0] as $match) {
            if (preg_match('/^(.+(href|src)=)([^ >]+)(.+?)$/i', $match, $args)) {
                $url = trim(trim($args[3], '"'), "'");
                // http 开头，跳过
                if (preg_match('/^(http|https|ftp)\:\/\//i', $url)) continue;
                // 邮件地址和javascript
                if (strncasecmp($url, 'mailto:', 7) === 0 || strncasecmp($url, 'javascript:', 11) === 0) continue;
                // 绝对路径
                if (strncmp($url, '/', 1) === 0) {
                    $url = $base_host . $url;
                } // 相对路径
                elseif (strncmp($url, '../', 3) === 0) {
                    while (strncmp($url, '../', 3) === 0) {
                        $url = substr($url, -(strlen($url) - 3));
                        if (strlen($base_path) > 0) {
                            $base_path = dirname($base_path);
                            if ($base_path == '/') $base_path = '';
                        }
                        if ($url == '../') {
                            $url = '';
                            break;
                        }
                    }
                    $url = $base_host . $base_path . '/' . $url;
                } // 当前路径
                elseif (strncmp($url, './', 2) === 0) {
                    $url = $base_url . '/' . substr($url, 2);
                } // 其他
                else {
                    $url = $base_url . '/' . $url;
                }
                // 替换标签
                $html = str_replace($match, sprintf('%s"%s"%s', $args[1], $url, $args[4]), $html);
            }
        }
    }
    return $html;
}

/**
 *
 * Compat 兼容函数
 *
 *******************************************************/
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = 'UTF-8') {
        if (!in_array($encoding, array('utf8', 'utf-8', 'UTF8', 'UTF-8'))) {
            return is_null($length) ? substr($str, $start) : substr($str, $start, $length);
        }
        if (function_exists('iconv_substr')) {
            return iconv_substr($str, $start, $length, $encoding);
        }
        // use the regex unicode support to separate the UTF-8 characters into an array
        preg_match_all('/./us', $str, $match);
        $chars = is_null($length) ? array_slice($match[0], $start) : array_slice($match[0], $start, $length);
        return implode('', $chars);
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = 'UTF-8') {
        if (!in_array($encoding, array('utf8', 'utf-8', 'UTF8', 'UTF-8'))) {
            return strlen($str);
        }
        if (function_exists('iconv_strlen')) {
            return iconv_strlen($str, $encoding);
        }
        // use the regex unicode support to separate the UTF-8 characters into an array
        preg_match_all('/./us', $str, $match);
        return count($match);
    }
}

if (!function_exists('hash_hmac')) {
    function hash_hmac($algo, $data, $key, $raw_output = false) {
        $packs = array('md5' => 'H32', 'sha1' => 'H40');

        if (!isset($packs[$algo]))
            return false;

        $pack = $packs[$algo];

        if (strlen($key) > 64)
            $key = pack($pack, $algo($key));

        $key = str_pad($key, 64, chr(0));

        $ipad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
        $opad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));

        $hmac = $algo($opad . pack($pack, $algo($ipad . $data)));

        if ($raw_output)
            return pack($pack, $hmac);
        return $hmac;
    }
}
if (!function_exists('gzinflate')) {
    /**
     * Decompression of deflated string while staying compatible with the majority of servers.
     *
     * Certain Servers will return deflated data with headers which PHP's gziniflate()
     * function cannot handle out of the box. The following function lifted from
     * http://au2.php.net/manual/en/function.gzinflate.php#77336 will attempt to deflate
     * the various return forms used.
     *
     * @param binary $gz_data
     * @return bool|string
     */
    function gzinflate($gz_data) {
        if (!strncmp($gz_data, "\x1f\x8b\x08", 3)) {
            $i = 10;
            $flg = ord(substr($gz_data, 3, 1));
            if ($flg > 0) {
                if ($flg & 4) {
                    list($xlen) = unpack('v', substr($gz_data, $i, 2));
                    $i = $i + 2 + $xlen;
                }
                if ($flg & 8)
                    $i = strpos($gz_data, "\0", $i) + 1;
                if ($flg & 16)
                    $i = strpos($gz_data, "\0", $i) + 1;
                if ($flg & 2)
                    $i = $i + 2;
            }
            return gzinflate(substr($gz_data, $i, -8));
        } else {
            return false;
        }
    }
}
if (!function_exists('gzdecode')) {
    /**
     * Opposite of gzencode. Decodes a gzip'ed file.
     *
     * @param string $data compressed data
     * @return bool|null|string True if the creation was successfully
     */
    function gzdecode($data) {
        $len = strlen($data);
        if ($len < 18 || strncmp($data, "\x1f\x8b", 2)) {
            return false; // Not GZIP format (See RFC 1952)
        }
        $method = ord(substr($data, 2, 1)); // Compression method
        $flags = ord(substr($data, 3, 1)); // Flags
        if ($flags & 31 != $flags) {
            // Reserved bits are set -- NOT ALLOWED by RFC 1952
            return false;
        }
        // NOTE: $mtime may be negative (PHP integer limitations)
        $mtime = unpack("V", substr($data, 4, 4));
        $mtime = $mtime[1];
        $xfl = substr($data, 8, 1);
        $os = substr($data, 8, 1);
        $headerlen = 10;
        $extralen = 0;
        $extra = "";
        if ($flags & 4) {
            // 2-byte length prefixed EXTRA data in header
            if ($len - $headerlen - 2 < 8) {
                return false; // Invalid format
            }
            $extralen = unpack("v", substr($data, 8, 2));
            $extralen = $extralen[1];
            if ($len - $headerlen - 2 - $extralen < 8) {
                return false; // Invalid format
            }
            $extra = substr($data, 10, $extralen);
            $headerlen += 2 + $extralen;
        }

        $filenamelen = 0;
        $filename = "";
        if ($flags & 8) {
            // C-style string file NAME data in header
            if ($len - $headerlen - 1 < 8) {
                return false; // Invalid format
            }
            $filenamelen = strpos(substr($data, 8 + $extralen), chr(0));
            if ($filenamelen === false || $len - $headerlen - $filenamelen - 1 < 8) {
                return false; // Invalid format
            }
            $filename = substr($data, $headerlen, $filenamelen);
            $headerlen += $filenamelen + 1;
        }

        $commentlen = 0;
        $comment = "";
        if ($flags & 16) {
            // C-style string COMMENT data in header
            if ($len - $headerlen - 1 < 8) {
                return false; // Invalid format
            }
            $commentlen = strpos(substr($data, 8 + $extralen + $filenamelen), chr(0));
            if ($commentlen === false || $len - $headerlen - $commentlen - 1 < 8) {
                return false; // Invalid header format
            }
            $comment = substr($data, $headerlen, $commentlen);
            $headerlen += $commentlen + 1;
        }

        $headercrc = "";
        if ($flags & 1) {
            // 2-bytes (lowest order) of CRC32 on header present
            if ($len - $headerlen - 2 < 8) {
                return false; // Invalid format
            }
            $calccrc = crc32(substr($data, 0, $headerlen)) & 0xffff;
            $headercrc = unpack("v", substr($data, $headerlen, 2));
            $headercrc = $headercrc[1];
            if ($headercrc != $calccrc) {
                return false; // Bad header CRC
            }
            $headerlen += 2;
        }

        // GZIP FOOTER - These be negative due to PHP's limitations
        $datacrc = unpack("V", substr($data, -8, 4));
        $datacrc = $datacrc[1];
        $isize = unpack("V", substr($data, -4));
        $isize = $isize[1];

        // Perform the decompression:
        $bodylen = $len - $headerlen - 8;
        if ($bodylen < 1) {
            // This should never happen - IMPLEMENTATION BUG!
            return null;
        }
        $body = substr($data, $headerlen, $bodylen);
        $data = "";
        if ($bodylen > 0) {
            switch ($method) {
                case 8:
                    // Currently the only supported compression method:
                    $data = gzinflate($body);
                    break;
                default:
                    // Unknown compression method
                    return false;
            }
        } else {
            // I'm not sure if zero-byte body content is allowed.
            // Allow it for now...  Do nothing...
        }

        // Verifiy decompressed size and CRC32:
        // NOTE: This may fail with large data sizes depending on how
        //      PHP's integer limitations affect strlen() since $isize
        //      may be negative for large sizes.
        if ($isize != strlen($data) || crc32($data) != $datacrc) {
            // Bad format!  Length or CRC doesn't match!
            return false;
        }
        return $data;
    }
}

if (!function_exists('image_type_to_extension')) {
    /**
     * Get file extension for image type
     *
     * @param int $imagetype
     * @param bool $include_dot
     * @return bool|string
     */
    function image_type_to_extension($imagetype, $include_dot = true) {
        if (empty($imagetype)) return false;
        $dot = $include_dot ? '.' : '';
        switch ($imagetype) {
            case IMAGETYPE_GIF       :
                return $dot . 'gif';
            case IMAGETYPE_JPEG      :
                return $dot . 'jpg';
            case IMAGETYPE_PNG       :
                return $dot . 'png';
            case IMAGETYPE_SWF       :
                return $dot . 'swf';
            case IMAGETYPE_PSD       :
                return $dot . 'psd';
            case IMAGETYPE_BMP       :
                return $dot . 'bmp';
            case IMAGETYPE_TIFF_II   :
                return $dot . 'tiff';
            case IMAGETYPE_TIFF_MM   :
                return $dot . 'tiff';
            case IMAGETYPE_JPC       :
                return $dot . 'jpc';
            case IMAGETYPE_JP2       :
                return $dot . 'jp2';
            case IMAGETYPE_JPX       :
                return $dot . 'jpf';
            case IMAGETYPE_JB2       :
                return $dot . 'jb2';
            case IMAGETYPE_SWC       :
                return $dot . 'swc';
            case IMAGETYPE_IFF       :
                return $dot . 'aiff';
            case IMAGETYPE_WBMP      :
                return $dot . 'wbmp';
            case IMAGETYPE_XBM       :
                return $dot . 'xbm';
            case IMAGETYPE_ICO       :
                return $dot . 'ico';
            default                  :
                return false;
        }
    }
}
/**
 * Detect MIME Content-type for a file (deprecated)
 *
 * @param string $filename
 * @return string
 */
function file_mime_type($filename) {
    if (is_file($filename) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME);
        $mimetype = finfo_file($finfo, $filename);
        finfo_close($finfo);
        return $mimetype;
    } else if (is_file($filename) && function_exists('mime_content_type')) {
        return mime_content_type($filename);
    } else {
        switch (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            case 'txt':
                return 'text/plain';
            case 'htm':
            case 'html':
            case 'php':
                return 'text/html';
            case 'css':
                return 'text/css';
            case 'js':
                return 'application/javascript';
            case 'json':
                return 'application/json';
            case 'xml':
                return 'application/xml';
            case 'swf':
                return 'application/x-shockwave-flash';
            case 'flv':
                return 'video/x-flv';

            // images
            case 'png':
                return 'image/png';
            case 'jpe':
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'gif':
                return 'image/gif';
            case 'bmp':
                return 'image/bmp';
            case 'ico':
                return 'image/x-icon';
            case 'tiff':
            case 'tif':
                return 'image/tiff';
            case 'svg':
            case 'svgz':
                return 'image/svg+xml';

            // archives
            case 'zip':
                return 'application/zip';
            case 'rar':
                return 'application/rar';
            case 'exe':
            case 'cpt':
            case 'bat':
            case 'dll':
                return 'application/x-msdos-program';
            case 'msi':
                return 'application/x-msi';
            case 'cab':
                return 'application/x-cab';
            case 'qtl':
                return 'application/x-quicktimeplayer';

            // audio/video
            case 'mp3':
            case 'mpga':
            case 'mpega':
            case 'mp2':
            case 'm4a':
                return 'audio/mpeg';
            case 'qt':
            case 'mov':
                return 'video/quicktime';
            case 'mpeg':
            case 'mpg':
            case 'mpe':
                return 'video/mpeg';
            case '3gp':
                return 'video/3gpp';
            case 'mp4':
                return 'video/mp4';

            // adobe
            case 'pdf':
                return 'application/pdf';
            case 'psd':
                return 'image/x-photoshop';
            case 'ai':
            case 'ps':
            case 'eps':
            case 'epsi':
            case 'epsf':
            case 'eps2':
            case 'eps3':
                return 'application/postscript';

            // ms office
            case 'doc':
            case 'dot':
                return 'application/msword';
            case 'rtf':
                return 'application/rtf';
            case 'xls':
            case 'xlb':
            case 'xlt':
                return 'application/vnd.ms-excel';
            case 'ppt':
            case 'pps':
                return 'application/vnd.ms-powerpoint';
            case 'xlsx':
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            case 'xltx':
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.template';
            case 'pptx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            case 'ppsx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.slideshow';
            case 'potx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.template';
            case 'docx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            case 'dotx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.template';

            // open office
            case 'odt':
                return 'application/vnd.oasis.opendocument.text';
            case 'ods':
                return 'application/vnd.oasis.opendocument.spreadsheet';
            case 'odp':
                return 'application/vnd.oasis.opendocument.presentation';
            case 'odb':
                return 'application/vnd.oasis.opendocument.database';
            case 'odg':
                return 'application/vnd.oasis.opendocument.graphics';
            case 'odi':
                return 'application/vnd.oasis.opendocument.image';

            default:
                return 'application/octet-stream';
        }
    }
}
