<?php
function log_message($log, $request_url)
{
    if (!is_string($log)) {
        $log = var_export($log, true);
    }

    $encode_log = urlencode($log);
    if ($request_url && strpos($request_url, 'handler') !== false) {
        exec("curl http://zjadmin.miwuyy.com/facility/utils/log_message?log=$encode_log");
    }
    error_log($log);
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

//http_get('http://www.baidu.com');

//$src = "http://video.miwuyy.com/avfilter/grayscale/avthumb/video/official/1547102130959784925.mp4";
//$filter_type = "grayscale";
//$src1 = str_replace("avfilter/$filter_type/", "", $src);
//
//echo $src1;

//$ss = 0;
//$t = 3.0;
//
//if ($ss || $t) {
//    echo '1';
//} else {
//    echo 2;
//}

//echo str_replace('-', '/', 'fksa-fjai;l-jfaslfj-fhewi-');


function get_current_timestamp() {
    list($s1, $s2) = explode(' ', microtime());
    echo round((floatval($s2) + floatval($s1)) * 1000);
}

get_current_timestamp();