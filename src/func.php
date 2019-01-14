<?php
function get_current_timestamp() {
    list($s1, $s2) = explode(' ', microtime());
    return round((floatval($s2) + floatval($s1)) * 1000);
}

function log_message($log, $request_url)
{
    if (!is_string($log)) {
        $log = var_export($log, true);
    }
    if ($request_url && strpos($request_url, 'handler') !== false) {
        $log = "---handler---:$log";
    }

    $encode_log = urlencode($log);
    if ($request_url && strpos($request_url, 'log_message') !== false) {
        exec("curl http://zjadmin.miwuyy.com/facility/utils/log_message?log=$encode_log");
    }
    error_log($log);
}

function params_filter($params, $keys)
{
    $return = array();

    foreach ($keys as $key) {
        if (isset($params[$key]) && ($params[$key] != null) && ($params[$key] != '')) {
            $return[$key] = $params[$key];
        } else {
            $return[$key] = '';
        }
    }

    return $return;
}

function delDirAndFile($path, $delDir = FALSE) {
    $handle = opendir($path);
    if ($handle) {
        while (false !== ( $item = readdir($handle) )) {
            if ($item != "." && $item != "..") {
                is_dir("$path/$item") ? $this->delDirAndFile("$path/$item", $delDir) : unlink("$path/$item");
            }
        }
        closedir($handle);
        if ($delDir) {
            return rmdir($path);
        }
    }else {
        if (file_exists($path)) {
            return unlink($path);
        } else {
            return FALSE;
        }
    }
    return FALSE;
}

function clear_temp_file() {
    delDirAndFile('temp/');
}

function set_php_memory() {
    //设置php可以使用的最大内存
    $before = ini_set('memory_limit', '2048M');
    $after = ini_get('memory_limit');
    log_message('memory limit before:' . $before . ', after:' . $after, '');
}

//get请求
function http_get($url)
{
    $ch = curl_init();
    $timeout = 10;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $contents = curl_exec($ch);
    log_message($url, 'handler');

    $c1 = strip_tags($contents);
    if ($c1 == $contents) {
        log_message($contents, 'handler');
    }

    return $contents;
}