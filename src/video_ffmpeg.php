<?php
//php执行终端命令
function exec_cmd_custom($cmd)
{
    $out = array();
    log_message($cmd, 'handler');
    exec($cmd, $out, $return);
    log_message($out, 'handler');

    if ($return != 0) {
        $arr['code'] = '401';
        $arr['msg'] = "执行命令失败：$cmd, 返回值：$return";
        log_message("执行命令失败：$cmd, 返回值：$return", 'handler');
        return false;
    }
    return true;
}

function find_ffmpeg()
{
    $cmd = "which ffmpeg";
    exec($cmd, $out, $return);
    log_message($out, 'handler');

    if ($return == 0) {
        return $out[0];
    } else {
        log_message("没有找到ffmpeg命令:$return", 'handler');
    }
}

function isVideoExists($src) {
    return file_exists("temp/$src");
}

function filter_video($src, $filter_type, $src_type) {
    switch ($filter_type){
        case 'bright':
            $res = bright_video($src, $src_type);
            break;
        case 'contrast':
            $res = contrast_video($src, $src_type);
            break;
        case 'grayscale':
            $res = grayscale_video($src, $src_type);
            break;
        case 'bright_cold':
            $res = bright_cold_video($src, $src_type);
            break;
        case 'bright_warm':
            $res = bright_warm_video($src, $src_type);
            break;
        case 'contrast_warm':
            $res = contrast_warm_video($src, $src_type);
            break;
        case 'contrast_cold':
            $res = contrast_cold_video($src, $src_type);
            break;
        default:
            $res = $src;
            break;
    }
    return $res;
}

function filter_audio($src, $audio_type, $script_src_url, $src_type) {
    switch ($audio_type){
        case 'script'://使用剧本音频
            $res = script_audio($src, $script_src_url);
            break;
        case 'origin'://使用用户视频中的音频
            $res = $src;
            break;
        case 'mixed'://音频混合
            if ($src_type == 'mp4') {
                $res = mixed_audio($src, $script_src_url);
            } else {
                $res = script_audio($src, $script_src_url);
            }
            break;
        default:
            $res = $src;
            break;
    }
    return $res;
}

function get_public_params($src_type) {
    if ($src_type == 'mp4') {
        return "-pix_fmt yuv420p";
    } else {
        return '';
    }
}

//下载音频文件
function download_audio($file_url) {
    $file = file_get_contents($file_url);
    $download_audio = "download_audio.mp3";
    file_put_contents("temp/$download_audio", $file);
    return $download_audio;
}


//更换视频中的音频
function script_audio($video, $script_src_url) {
    $target_name = "audio_$video";
    $ffmpeg = find_ffmpeg();
    $audio = download_audio(substr($script_src_url, 0, strlen($script_src_url) - 1) . '3');

    if ($ffmpeg && isVideoExists($audio)) {
        $cmd = "$ffmpeg -i temp/$audio -i temp/$video temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("更换视频中的音频失败：$target_name", 'handler');
        return $res;
    }
}

//混合视频中的音频
function mixed_audio($video, $script_src_url) {
    $target_name = "audio_$video";
    $ffmpeg = find_ffmpeg();
    $audio = download_audio(substr($script_src_url, 0, strlen($script_src_url) - 1) . '3');

    if ($ffmpeg && isVideoExists($audio)) {
        //分离视频中的音频
        $cmd = "$ffmpeg -i temp/$video -f mp3 temp/video.mp3";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            //混合音频
            $cmd = "$ffmpeg -i temp/video.mp3 -i temp/$audio -filter_complex amix=inputs=2:duration=first:dropout_transition=2 -f mp3 temp/mixed.mp3";
            $res = exec_cmd_custom($cmd);

            if ($res) {
                //混合音频和视频
                $cmd = "$ffmpeg -i temp/mixed.mp3 -i temp/$video temp/$target_name";
                $res = exec_cmd_custom($cmd);


                if ($res) {
                    return $target_name;
                }
            }
        }

        log_message("更换视频中的音频失败：$target_name", 'handler');
        return $res;
    }
}

//单张图片转视频
function image_to_video($src_name, $duration)
{
    $target_name = "image_to.mp4";
    $ffmpeg = find_ffmpeg();

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -f lavfi -i aevalsrc=0:d=3 -r 24 -loop 1 -y -i temp/$src_name -pix_fmt yuv420p -vcodec libx264 -b:v 1024k -r:v 24 -s 540x960 -aspect 9:16 -preset medium -t $duration temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("单张图片转视频失败：$target_name", 'handler');
        return $res;
    }
}

//横屏视频叠加
function landscape_to_vertical($src_name) {
    $target_name = "landscape_to_vertical.mp4";
    $ffmpeg = find_ffmpeg();

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -i temp/$src_name -i temp/$src_name -filter_complex \"[0:v]pad=iw:ih*3[a];[a][1:v]overlay=0:h[b];[b][2:v]overlay=0:2*h\" -pix_fmt yuv420p temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("横屏视频叠加失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，增强视频亮度
 */
function bright_video($src_name, $src_type)
{
    $target_name = "bright_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf colorlevels=rimax=0.88:gimax=0.88:bimax=0.88 $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("增强视频亮度失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，增强视频对比度
 */
function contrast_video($src_name, $src_type)
{
    $target_name = "contrast_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf colorlevels=rimin=0.2:gimin=0.2:bimin=0.2:rimax=0.8:gimax=0.8:bimax=0.8 $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("增强视频对比度失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，添加暖色
 */
function warm_video($src_name, $src_type)
{
    $target_name = "warm_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf colorbalance=rm=0.15:gm=0.05:bm=0.01:rs=0.15:gs=0.05:bs=0.01:rh=0.15:gh=0.05:bh=0.01 $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("添加暖色失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，添加鲜暖色
 */
function bright_warm_video($src_name, $src_type)
{
    $target_name = "bright_warm_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf colorlevels=rimax=0.88:gimax=0.88:bimax=0.88,colorbalance=rm=0.15:gm=0.05:bm=0.01:rs=0.15:gs=0.05:bs=0.01:rh=0.15:gh=0.05:bh=0.01 $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("添加鲜暖色失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，添加反差暖色
 */
function contrast_warm_video($src_name, $src_type)
{
    $target_name = "contrast_warm_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf colorlevels=rimin=0.2:gimin=0.2:bimin=0.2:rimax=0.8:gimax=0.8:bimax=0.8,colorbalance=rm=0.15:gm=0.05:bm=0.01:rs=0.15:gs=0.05:bs=0.01:rh=0.15:gh=0.05:bh=0.01 $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("添加反差暖色失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，灰阶处理
 */
function grayscale_video($src_name, $src_type)
{
    $target_name = "grayscale_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf lutyuv=\"u=128:v=128\" $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("灰阶处理失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，添加冷色
 */
function cold_video($src_name, $src_type)
{
    $target_name = "cold_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf colorbalance=rm=0.01:gm=0.05:bm=0.15:rs=0.01:gs=0.05:bs=0.15:rh=0.01:gh=0.05:bh=0.15 $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("添加冷色失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，添加鲜冷色
 */
function bright_cold_video($src_name, $src_type)
{
    $target_name = "bright_cold_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf colorlevels=rimax=0.88:gimax=0.88:bimax=0.88,colorbalance=rm=0.01:gm=0.05:bm=0.15:rs=0.01:gs=0.05:bs=0.15:rh=0.01:gh=0.05:bh=0.15 $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("添加鲜冷色失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，添加反差冷色
 */
function contrast_cold_video($src_name, $src_type)
{
    $target_name = "contrast_cold_$src_name";
    $ffmpeg = find_ffmpeg();
    $public_params = get_public_params($src_type);

    if ($ffmpeg && isVideoExists($src_name)) {
        $cmd = "$ffmpeg -y -i temp/$src_name -vf colorlevels=rimin=0.2:gimin=0.2:bimin=0.2:rimax=0.8:gimax=0.8:bimax=0.8,colorbalance=rm=0.01:gm=0.05:bm=0.15:rs=0.01:gs=0.05:bs=0.15:rh=0.01:gh=0.05:bh=0.15 $public_params temp/$target_name";
        $res = exec_cmd_custom($cmd);

        if ($res) {
            return $target_name;
        }
        log_message("添加反差冷色失败：$target_name", 'handler');
        return $res;
    }
}

/**
 * 处理视频，裁剪视频尺寸
 */
function crop_video($src_name, $src_width, $src_height, $ss, $t, $rotate)
{
    $target_name = "crop_$src_name";
    $target_name1 = "thumb_$src_name";
    $ffmpeg = find_ffmpeg();
    $target_width = 540;
    $target_height = 960;
    $real_width = 0;
    $real_height = 0;
    $x = 0;
    $y = 0;
    $start = 0;
    $end = 0;

    if ($ffmpeg && isVideoExists($src_name)) {
        //判断视频尺寸是否需要裁剪
        //旋转后的竖屏视频
        if ($rotate) {
            $temp = $src_width;
            $src_width = $src_height;
            $src_height = $temp;
        }

        if ($src_height/$src_width == $target_height/$target_width) {
            $start = time();
            if ($ss || $t) {
                $cmd = "$ffmpeg -y -i temp/$src_name -s $target_width" . "x" . "$target_height -ss $ss -t $t -pix_fmt yuv420p -r 24 -vcodec libx264 -aspect 9:16 temp/$target_name";
            } else {
                $cmd = "$ffmpeg -y -i temp/$src_name -s $target_width" . "x" . "$target_height -pix_fmt yuv420p -r 24 -vcodec libx264 -aspect 9:16 temp/$target_name";
            }
            $res = exec_cmd_custom($cmd);
            $end = time();
            log_message("转码用时1：" . ($end - $start), 'handler');

            if ($res) {
                return $target_name;
            }
        } else if ($src_height/$src_width > $target_height/$target_width) {
            $real_width = $target_width;
            $real_height = round($src_height/$src_width * $real_width);

            if ($real_height % 2) {
                $real_height = $real_height + 1;
            }

            $y = round(($real_height - $target_height) / 2);
        } else if ($src_height/$src_width < $target_height/$target_width) {
            $real_height = $target_height;
            $real_width = round($src_width/$src_height * $real_height);

            if ($real_width % 2) {
                $real_width = $real_width + 1;
            }

            $x = round(($real_width - $target_width) / 2);
        }

        log_message("ss:$ss,t:$t", 'handler');
        if ($ss || $t) {
            $cmd = "$ffmpeg -y -i temp/$src_name -s $real_width" . "x" . "$real_height  -pix_fmt yuv420p -r 24 -vcodec libx264 -ss $ss -t $t temp/$target_name";
        } else {
            $cmd = "$ffmpeg -y -i temp/$src_name -s $real_width" . "x" . "$real_height  -pix_fmt yuv420p -r 24 -vcodec libx264 temp/$target_name";
        }

        $start = time();
        $res = exec_cmd_custom($cmd);
        $end = time();
        log_message("缩放视频用时2：" . ($end - $start), 'handler');

        if ($res) {
            $cmd = "$ffmpeg -y -i temp/$target_name -vf crop=$target_width:$target_height:$x:$y -pix_fmt yuv420p -r 24 -vcodec libx264 -aspect 9:16 temp/$target_name1";
            $start = time();
            $res = exec_cmd_custom($cmd);
            $end = time();
            log_message("裁剪转码用时3：" . ($end - $start), 'handler');

            if ($res) {
                return $target_name1;
            }
        }
        log_message("裁剪失败：$target_name1", 'handler');
        return $res;
    }
}

/**
 * 处理视频，裁剪转码
 */
function thumb_video($src_name, $src_width, $src_height, $ss, $t, $rotate)
{
    $target_name = "thumb_$src_name";
    $ffmpeg = find_ffmpeg();

    if ($ffmpeg && isVideoExists($src_name)) {
        $res = crop_video($src_name, $src_width, $src_height, $ss, $t, $rotate);

        if ($res) {
            return $res;
        }
        log_message("裁剪转码失败：$target_name", 'handler');
        return $res;
    }
}
