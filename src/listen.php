<?php
//set_error_handler ( 'customerror' );
//set_exception_handler ( 'customexception' );

include('func.php');
include('video_ffmpeg.php');

set_php_memory();
//程序返回值
$arr = array();
//解析请求路由
$request_url = $_SERVER['REQUEST_URI'];
log_message("request url:$request_url", $request_url);

if (strlen($request_url) < 2) {
    $arr['code'] = 404;
    $arr['msg'] = '请求的路由不存在';
    exit(json_encode($arr));
} else {
    //去掉路由中的'/'
    $request_url = substr($request_url, 1);
    //去掉'?'及后面的参数
    $request_urls = explode('?', $request_url);
    $request_url = $request_urls[0];
}
$task_start = 0;
$task_end = 0;

//解析请求参数
$params = params_filter($_REQUEST, array('cmd', 'url'));
$cmd = $params['cmd'];
$file_url = $params['url'];
//视频横竖屏
$screen_type = 'vertical';
$cmds = array();
$segment = '';
$segment_json = array();
$duration = 3;
$audio_type = '';
$filter_type = '';
$overlay_type = '';
$src_type = '';
$file_name = '';
$script_src_url = '';
$task_name = '';
$thumb_params = '';
$src_width = 0;
$src_height = 0;
$rotate = false;
$ss = 0;
$t = 0;

function fix_src_url($src) {
    if ((strpos($src, 'http://') !== false) || (strpos($src, 'https://') !== false)) {
        return $src;
    } else {
        return "http://video.miwuyy.com/" . $src;
    }
}

function task_before_filter() {
    global $cmd;
    global $file_url;
    global $cmds;
    global $segment;
    global $segment_json;
    global $duration;
    global $audio_type;
    global $filter_type;
    global $overlay_type;
    global $screen_type;
    global $src_type;
    global $file_name;
    global $request_url;
    global $script_src_url;
    global $task_name;
    global $thumb_params;
    global $task_start;
    global $task_end;
    global $src_width;
    global $src_height;
    global $rotate;
    global $ss;
    global $t;

    if (!$cmd || !$file_url) {
        $arr['code'] = '400';
        $arr['msg'] = '请求参数错误';
        exit(json_encode($arr));
    }
    log_message("cmd:$cmd", $request_url);
    $cmds = explode('/', $cmd);

    //至少包含两个参数
    if (count($cmds) < 2) {
        $arr['code'] = '400';
        $arr['msg'] = "请求参数错误:$cmd";
        exit(json_encode($arr));
    }

    $task_name = $cmds[1];
    log_message($cmds, $request_url);
    clear_temp_file();

    if ($task_name == 'gop') {
        $task_start = get_current_timestamp();
        log_message("开始下载文件：$file_url", $request_url);
        $file = file_get_contents($file_url);
        log_message('task start, cmd:' . $cmd, $request_url);
        $file_name = 'video.mp4';
        file_put_contents("temp/$file_name", $file);
        $keyint = calculate_video_keyint($file_name);

        if ($keyint > 24) {
            $res = gop_video($file_name);
        } else {
            //抛出错误
            throw new Error("视频无需添加关键帧");
        }

        if ($res) {
            $arr['code'] = 200;
            $arr['msg'] = '添加关键帧处理成功';
            log_message($arr, $request_url);
            header("Content-Type: video/mp4");
            //避免filesize函数使用缓存
            clearstatcache();
            header("Content-Length: " . filesize("temp/$res"));
            header("Content-Disposition: attachment; filename=" . time() . ".mp4");
            $task_end = get_current_timestamp();
            log_message("gop任务总用时：" . ($task_end - $task_start), 'log_message');

            return readfile("temp/$res");
        } else {
            $arr['code'] = 500;
            $arr['msg'] = '添加关键帧处理失败';
            log_message($arr, $request_url);
            exit(json_encode($arr));
        }
    } else if ($task_name == 'avfilter') {
        if (count($cmds) < 5) {
            $arr['code'] = '400';
            $arr['msg'] = "请求参数错误:$cmd";
            exit(json_encode($arr));
        }

        //视频对应的剧本信息
        $segment = base64_decode(str_replace('-', '/', $cmds[2]));
        log_message($segment, $request_url);
        $segment_json = json_decode($segment);
        log_message($segment_json, $request_url);
        if (property_exists($segment_json, 'videoDuration')) {
            $duration = $segment_json->videoDuration;
        }
        if (property_exists($segment_json, 'audioType')) {
            $audio_type = $segment_json->audioType;
        }
        if (property_exists($segment_json, 'filterType')) {
            $filter_type = $segment_json->filterType;
        }
        if (property_exists($segment_json, 'overlayType')) {
            $filter_type = $segment_json->overlayType;
        }
        $script_src_url = fix_src_url($segment_json->src);
        $src_name = $cmds[4];
        $file_url_arr = explode('.', $src_name);
        $src_type = array_pop($file_url_arr);
        log_message("源文件类型：$src_type", $request_url);

        //视频裁剪参数
        if ($src_type == 'mp4') {
            $thumb_params = base64_decode(str_replace('-', '/', $cmds[5]));
            $thumb_json = json_decode($thumb_params);
            log_message($thumb_json, $request_url);

            if (property_exists($thumb_json, 'ss')) {
                $ss = $thumb_json->ss;
            }
            if (property_exists($thumb_json, 't')) {
                $t = $thumb_json->t;
            }
            if (property_exists($thumb_json, 'videoname')) {
                $src = $thumb_json->videoname;
            }
        }

        //请求网络资源
        $task_start = get_current_timestamp();
        log_message("开始下载文件：$file_url", $request_url);
        $file = file_get_contents($file_url);
        log_message('task start, cmd:' . $cmd, $request_url);

        //统一命名下载到本地的资源文件名
        if ($src_type != 'mp4') {
            $file_name = 'image.' . $src_type;
        } else {
            $file_name = 'video.mp4';
            $src_url = fix_src_url($src);
            //获取资源宽高
            obtain_video_info($src_url);
            log_message("视频宽高：$src_width, $src_height", $request_url);

            $screen_type = ($cmds[3] == "1"? 'vertical' : 'landscape');
        }

        //写入到临时文件
        file_put_contents("temp/$file_name", $file);
    } else if ($task_name == 'avthumb') {
        //视频剪切转码
        if (count($cmds) < 3) {
            $arr['code'] = '400';
            $arr['msg'] = "请求参数错误:$cmd";
            exit(json_encode($arr));
        }

        $thumb_params = base64_decode(str_replace('-', '/', $cmds[2]));
        log_message($thumb_params, $request_url);
        $thumb_json = json_decode($thumb_params);
        log_message($thumb_json, $request_url);
        $src = '';
        $src_url = '';

        if (property_exists($thumb_json, 'ss')) {
            $ss = $thumb_json->ss;
        }
        if (property_exists($thumb_json, 't')) {
            $t = $thumb_json->t;
        }
        if (property_exists($thumb_json, 'videoname')) {
            $src = $thumb_json->videoname;
        }

        $src_url = fix_src_url($src);
        //请求网络资源
        $task_start = get_current_timestamp();
        log_message("开始下载文件：$file_url", $request_url);
        $file = file_get_contents($file_url);
        log_message('task start, cmd:' . $cmd, $request_url);
        $file_url_arr = explode('.', $src_url);
        $src_type = array_pop($file_url_arr);

        //统一命名下载到本地的资源文件名
        if ($src_type != 'mp4') {
            $file_name = 'image.' . $src_type;
        } else {
            $file_name = 'video.mp4';
            //获取资源宽高
            obtain_video_info($src_url);
            log_message("视频宽高：$src_width, $src_height", $request_url);
        }

        //写入到临时文件
        file_put_contents("temp/$file_name", $file);
        $res = thumb_video($file_name, $src_width, $src_height, $ss, $t, $rotate);

        if ($res) {
            $arr['code'] = 200;
            $arr['msg'] = '转码处理成功';
            log_message($arr, $request_url);
            header("Content-Type: video/mp4");
            //避免filesize函数使用缓存
            clearstatcache();
            header("Content-Length: " . filesize("temp/$res"));
            header("Content-Disposition: attachment; filename=" . time() . ".mp4");
            $task_end = get_current_timestamp();
            log_message("avthumb任务总用时：" . ($task_end - $task_start), 'log_message');

            return readfile("temp/$res");
        } else {
            $arr['code'] = 500;
            $arr['msg'] = '转码处理失败';
            log_message($arr, $request_url);
            exit(json_encode($arr));
        }
    } else {
        $arr['code'] = '400';
        $arr['msg'] = "请求参数错误:$cmd";
        exit(json_encode($arr));
    }
}

function obtain_video_info($src_url) {
    global $src_width;
    global $src_height;
    global $rotate;
    global $ss;
    global $t;

    $content = http_get($src_url . "?avinfo");
    $content_json = json_decode($content);
    $streams = $content_json->streams;

    if (count($streams)) {
        foreach ($streams as $stream) {
            if (property_exists($stream, 'codec_type') && $stream->codec_type == 'video') {
                $src_width = $stream->width;
                $src_height = $stream->height;

                if (!$t) {
                    $t = $stream->duration;
                }

                if (property_exists($stream, 'side_data_list')) {
                    $side_data_list = $stream->side_data_list;
                    if (count($side_data_list)) {
                        foreach ($side_data_list as $item) {
                            if (property_exists($item, 'rotation') && abs($item->rotation) == 90) {
                                $rotate = true;
                            }
                        }
                    }
                }
                break;
            }
        }
    } else {
        $arr['code'] = '500';
        $arr['msg'] = "获取视频参数错误:$content";
        exit(json_encode($arr));
    }
}

//处理请求
if ($request_url == 'health') {
    exit('ok');
} else if ($request_url == 'handler') {
    //解析参数及下载文件
    task_before_filter();
    //处理自定义任务
    //进行滤镜处理
    if ($task_name == 'avfilter') {
        //判断是否需要更新覆盖效果的视频
        if ($overlay_type) {
            if (strpos($cmd, "update_overlay_video")) {
                if (file_exists("video/overlay/$overlay_type.mov")) {
                    unlink("video/overlay/$overlay_type.mov");
                }
            }

            if (!file_exists("video/overlay/$overlay_type.mov")) {
                $overlay_file = file_get_contents(fix_src_url("video/overlay/$overlay_type.mov"));
                file_put_contents("video/overlay/$overlay_type.mov", $overlay_file);
            }
        }

        //先裁剪
        if ($src_type == 'mp4') {
            $res = thumb_video($file_name, $src_width, $src_height, $ss, $t, $rotate);

            if ($overlay_type) {
                if ($res) {
                    $res = overlay_video($res, $overlay_type);
                }
            }

            if ($res) {
                $res = filter_video($res, $filter_type, $src_type);
            }
        } else {
            $res = overlay_video($file_name, $overlay_type);

            if ($res) {
                $res = filter_video($res, $filter_type, $src_type);
            }

        }

        if ($res) {
            //如果是图片，需要用图片生成视频
            if ($src_type != 'mp4') {
                $arr = getimagesize("temp/$res");
                $src_width = $arr[0];
                $src_height = $arr[1];
                $ss = 0;
                $t = $duration;
                $rotate = false;
                $res = image_to_video($res, $duration);
            }

            //需要把横屏视频处理成竖屏
            if ($screen_type == 'landscape') {
                $res = landscape_to_vertical($res);
            }

            //音频处理
            if ($audio_type) {
                $res = filter_audio($res, $audio_type, str_replace("avfilter/$filter_type/", "", $script_src_url), $src_type);
            }

            if ($res) {
                $arr['code'] = 200;
                $arr['msg'] = '滤镜处理成功';
                log_message($arr, $request_url);
                header("Content-Type: video/mp4");
                //避免filesize函数使用缓存
                clearstatcache();
                header("Content-Length: " . filesize("temp/$res"));
                header("Content-Disposition: attachment; filename=" . time() . ".mp4");
                $task_end = get_current_timestamp();
                log_message("avfilter任务总用时：" . ($task_end - $task_start), 'log_message');

                return readfile("temp/$res");
            } else {
                $arr['code'] = 500;
                $arr['msg'] = '滤镜处理失败';
                log_message($arr, $request_url);

                echo json_encode($arr);
                return ;
            }
        }
    }
} else {
    $arr['code'] = 404;
    $arr['msg'] = '请求的路由不存在';
}
echo json_encode($arr);


//处理程序错误
//function customerror($error_level,$error_message,$error_file,$error_line,$error_context) {
//    $arr["code"] = 404;
//    $arr['type'] = 'error';
//    $arr['error'] = $error_message;
//    header("Content-Type: text/html; charset=UTF-8");
//
//    log_message(json_encode($arr), 'handler') ;
//    echo json_encode($arr);
//    die();
//}
////处理程序异常
//function customexception($exception) {
//    $arr["code"] = 404;
//    $arr['type'] = 'exception';
//    $arr['error'] = $exception;
//    header("Content-Type: text/html; charset=UTF-8");
//
//    log_message(json_encode($arr), 'handler') ;
//    echo json_encode($arr);
//    die();
//}

?>