<?php

/**
 * 剪切板
 * @author 方淞(fang@woiec.com)
 * @see https://www.zeroecx.com
 * @version 2.1.2 2020-05-09
 */

set_time_limit(30);
error_reporting(0);
date_default_timezone_set('PRC');
header('Content-type:text/html;charset=utf-8');
$version = 'V2.1.2 2020-05-09';

define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));

/**
 * 获取IP
 * @return string
 */
function getIP() : string
{
    // Apache
    return $_SERVER["REMOTE_ADDR"] ?? '';
}

/**
 * 随机字符串
 * @param $length
 * @param string $chars = 'xxx'
 * @return string
 */
function randString($length, $chars = 'abcdefghijklmnopqrstuvwxyz123456789') : string
{
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= $chars[mt_rand(0, strlen($chars))];
    }
    return $string;
}

/**
 * 下载文件
 * @param string $file
 * @param string $file_name
 * @return void
 */
function downloadFile(string $file, string $file_name)
{
    $file = ROOT_PATH . '/Static/' . $file . '_' . pathinfo($file_name, PATHINFO_EXTENSION);
    if (!is_file($file)) {
        exit('Download Error.');
    }
    header('Content-Type: application/octet-stream');
    header('Accept-Ranges: bytes');
    $file_size = filesize($file);
    header('Content-Length: ' . $file_size);
    header('Content-Disposition: attachment;filename=' . $file_name);
    $handle = fopen($file, 'rb');
    while (!feof($handle)) {
        echo fread($handle, 1024);
    }
    fclose($handle);
    exit;
}

/**
 * 读取文件内容
 * @param string $file
 * @return array
 */
function getFileContent(string $file) : array
{
    return json_decode(file_get_contents($file), true);
}

/**
 * 写入文件内容
 * @param string $file
 * @param array $data
 * @return bool
 */
function putFileContent(string $file, array $data) : bool
{
    file_put_contents($file, json_encode($data));
    return true;
}

/**
 * 读取Cookie
 * @param string $name
 * @return string|array
 */
function getCookieValue(string $name = '')
{
    $values = $_COOKIE['auth'] ?? '';
    if ('' === $values) {
        return 'error';
    }
    $values = json_decode(base64_decode(str_replace(['_', '-'], ['/', '+'], $values)), true);
    if (false === $values) {
        return 'error';
    }
    foreach ($values as $k => $v) {
        if ($v[0] < time()) {
            unset($values[$k]);
        }
    }
    if ($name !== '' && isset($values[$name])) {
        return $values[$name][1];
    }
    return $values;
}

/**
 * 更新Cookie
 * @param string $name
 * @param string $value
 * @param int $time
 * @return bool
 */
function setCookieValue(string $name, string $value, int $time) : bool
{
    $data = getCookieValue();
    if ($data === 'error') {
        $data = [];
    }
    $data[$name] = [$time + 86400, $value];
    $values = str_replace(['/', '+'], ['_', '-'], base64_encode(json_encode($data)));
    setcookie('auth', $values, time() + 86400);
    return true;
}

/**
 * 删除过期文件
 * @return void
 */
function removeFile()
{
    $files = [];
    $dirs = [
        ROOT_PATH . '/Data',
        ROOT_PATH . '/Static'
    ];
    foreach ($dirs as $dir) {
        if ($handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file !== '.' && $file !== '..') {
                    if (is_file($dir . '/' . $file)) {
                        if (filemtime($dir . '/' . $file) + 86400 < time()) {
                            $files[] = $dir . '/' . $file;
                        }
                    }
                }
            }
        }
    }
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

/**
 * 删除过期文件请求
 * @return void
 */
function removeFileRequest()
{
    $url = '/data.json?action=remove_file';
    $host = isset($_SERVER['HTTP_X_REAL_HOST']) ?
        $_SERVER['HTTP_X_REAL_HOST'] : $_SERVER['HTTP_HOST'];
    $fp = fsockopen($host, 80, $errno, $errstr, 20);
    if (!$fp) {
        return;
    }
    stream_set_blocking($fp,true);
    stream_set_timeout($fp,20);
    $out = 'GET ' . $url . ' HTTP/1.1' . "\r\n";
    $out .= 'Host: ' . $host . "\r\n";
    $out .= 'Content-type: application/x-www-form-urlencoded' . "\r\n";
    $out .= 'Connection: close' . "\r\n\r\n";
    fwrite($fp, $out);
    usleep(10000);
    fclose($fp);
}

/**
 * 输出JSON
 * @param bool $result
 * @param array $data = []
 * @return void
 */
function showJson(bool $result, array $data = [])
{
    $json_data = [
        'result' => $result,
        'resource' => $data
    ];
    header('Content-type:application/json;charset=utf-8');
    echo json_encode($json_data);
    exit;
}

// 删除过期文件
if (isset($_GET['action']) && $_GET['action'] === 'remove_file') {
    removeFile();
    exit;
}
removeFileRequest();

// 下载文件（公开，不需要权限）
if (isset($_GET['action']) && $_GET['action'] === 'file_download') {
    $file_id = $_GET['file_id'] ?? '';
    $file_name = $_GET['file_name'] ?? '';
    if ($file_id === '' || $file_name === '') {
        exit('Download Error.');
    }
    downloadFile($file_id, $file_name);
}

// 获取任务ID
$task = 'e';
if (isset($_GET['task_id']) && strlen($_GET['task_id']) >= 4 && strlen($_GET['task_id']) <= 12) {
    if (preg_match("/^[a-zA-Z\d]+$/i", $_GET['task_id']) > 0) {
        $task = $_GET['task_id'];
    }
}

// 任务ID白名单
if ($task !== 'e') {
    if (strlen($task) !== 4 || substr($task, 0, 1) !== 'u' || !is_numeric(substr($task, 1))) {
        $return_data = [
            'auth' => false,
            'lock' => true,
            'type' => 'warning',
            'time' => '0000-00-00 00:00:00',
            'content' => '⚠ 为规避风险，本工具暂不向外大规模的推广使用，仅供在白名单中的任务ID使用，谢谢理解；'
                . "\r\n" . '　 如需要该工具，请到【Github】https://github.com/xpzfs/clipboard 下载源码；'
                . "\r\n" . '　 感谢你的支持，本工具由@方淞(WOIEC.COM)开发。'
        ];
        showJson(true, $return_data);
    }
}

// 创建任务数据文件
$file = ROOT_PATH . '/Data/' . $task . '.json';
if (!is_file($file)) {
    putFileContent($file, []);
}

// 操作数据请求
if (isset($_GET['action'])) {

    // 默认返回前端的数据
    $return_data = [
        'auth' => true, // 是否有操作的权限，在锁定的状态下生效
        'lock' => false, // 锁定的状态
        'type' => '', // 内容的类型：string/image/file/warning
        'time' => '', // 时间 YYYY-mm-dd H:i:s
        'content' => '' // 内容
    ];

    // 权限判断
    $data = getFileContent($file);
    $auth_string = '';
    if (isset($data['auth_string']) && $data['auth_string'] !== '') {
        $auth_string = $data['auth_string'];
    }
    // 如果是锁定状态则判断权限
    if (isset($data['lock']) && $data['lock']) {
        $return_data['lock'] = true;
        $cookie_value = getCookieValue($task);
        // 锁定状态且权限不等，直接返回没有权限信息
        if ($auth_string !== $cookie_value) {
            if ($_GET['action'] === 'get_content') {
                $return_data['auth'] = false;
                $return_data['type'] = 'warning';
                $return_data['time'] = $data['time'] ?? '0000-00-00 00:00:00';
                $return_data['content'] = '⚠ 该内容已锁定，你没有权限操作。';
                showJson(true, $return_data);
            } else {
                showJson(false);
            }
        }
    } else {
        // 设置当前客户端授权
        if ($auth_string === '') {
            $auth_string = randString(20, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-=_+,?.;');
        }
        // 该Cookie的过期时间理应随着数据过期而过期
        $time = time();
        if (isset($data['time'])) {
            $time = strtotime($data['time']);
        }
        setCookieValue($task, $auth_string, $time);
    }

    // 前端请求操作
    switch ($_GET['action'])
    {

        // 读取内容
        case 'get_content':
            if ('e' === $task) {
                $url = '/' . randString(4);
                $max_execution_time = ini_get('max_execution_time');
                $file_update_max_size = ini_get('upload_max_filesize');
                $post_max_size = ini_get('post_max_size');
                $data = [
                    'type' => 'string',
                    'content' => '<h2>Hello, Clipboard.</h2>'
                        . '<p style="font-weight: bold;">' . $version . '<br>一个在线剪切板的工具，支持截图、文件、文字数据。</p>'
                        . '<p style="margin-bottom: 20px; font-size: 12px;">MAX_EXECUTION_TIME：' . $max_execution_time . 's；POST_MAX_SIZE：' . $post_max_size . '；FILE_UPDATE_MAX_SIZE：' . $file_update_max_size . '</p>'
                        . '<p>请在URL后面输入任务ID（4-12个长度的字母或数字），如：/abc123</p>'
                        . '<p>或点击这里创建新的会话任务：<a href="' . $url . '" style="text-decoration: underline;">' . $url . '</a></p>'
                ];
                showJson(true, $data);
            }
            if (!isset($data['type']) || '' === $data['type']
                || !isset($data['time']) || '' === $data['time']
                || !isset($data['content']) || '' === $data['content']) {
                showJson(false);
            }
            $return_data['type'] = $data['type'] ?? '';
            $return_data['time'] = $data['time'] ?? '';
            $return_data['content'] = $data['content'] ?? '';
            showJson(true, $return_data);
            break;

        // 更新内容
        case 'update_content':
            if ('e' === $task || $data['type'] === 'warning') {
                showJson(false);
            }
            $post_data = [
                'ip' => getIP(),
                'auth_string' => $auth_string,
                'lock' => $data['lock'] ?? false,
                'type' => $_POST['type'] ?? '',
                'time' => $_POST['time'] ?? '',
                'content' => $_POST['content'] ?? ''
            ];
            if ($post_data['type'] === '' || $post_data['time'] === '') {
                showJson(false);
            }
            if ($post_data['type'] === 'file') {
                if (isset($_FILES['file']) && $_FILES['file']['error'] > 0) {
                    showJson(false);
                } else {
                    $filename = randString(40);
                    $file_name = ROOT_PATH . '/Static/' . $filename . '_' . pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $file_name)) {
                        $post_data['content'] = '/file-download/' . $filename . '/' . $_FILES['file']['name'];
                    } else {
                        $post_data['content'] = '';
                    }
                }
            }
            putFileContent($file, $post_data);
            showJson(true, ['result' => true]);
            break;

        // 更新锁定状态
        case 'update_lock':
            if ('e' === $task || $data['type'] === 'warning') {
                showJson(false);
            }
            $lock = ($_POST['lock'] ?? '') === 'true';
            $post_data = [
                'ip' => $data['ip'] ?? '',
                'auth_string' => $auth_string,
                'lock' => $lock,
                'type' => $data['type'] ?? '',
                'time' => $data['time'] ?? '',
                'content' => $data['content'] ?? ''
            ];
            putFileContent($file, $post_data);
            showJson(true, ['result' => true]);
            break;

        // 清空内容
        case 'remove_content':
            if ('e' === $task || $data['type'] === 'warning') {
                showJson(false);
            }
            $post_data = [
                'ip' => $data['ip'] ?? '',
                'auth_string' => $auth_string,
                'lock' => false,
                'type' => 'warning',
                'time' => date('Y-m-d H:i:s'),
                'content' => '⚠ 该内容被举报，数据已清空，过期前不可操作。'
            ];
            putFileContent($file, $post_data);
            showJson(true, ['result' => true]);
            break;

        default:
    }

}
