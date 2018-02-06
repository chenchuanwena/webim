<?php
namespace App\Controller;

use Swoole\Client\CURL;
use WebIM\Storage;

class Page extends \Swoole\Controller
{
    function __construct(\Swoole $swoole)
    {
        parent::__construct($swoole);
        if(!empty($_GET['sess'])){
            if(isset($_SESSION['sess'])){
                if($_GET['sess']!=$_SESSION['sess']){
                    session_destroy();
                }
            }
        }
    }

    function index()
    {
        $this->session->start();
//        if (!empty($_SESSION['isLogin']))
//        {
//            chatroom:
//            $this->http->redirect('/page/chatroom/');
//            return;
//        }
        if (!empty($_GET['sess']))
        {
            $curl = new CURL();
            $user = $curl->get($this->config['login']['get_user_info'] . '&sess=' . $_GET['sess']);
            if (empty($user))
            {
                login:
                echo '你登录过期或者未登陆，请登录后重新请求';
                exit;
                //$this->http->redirect($this->config['login']['passport'] . '?return_token=1&refer=' . urlencode($this->config['webim']['server']['origin']));
            }
            else
            {
                $user=json_decode($user, true);
                $responseContent=$user['repsoneContent'];
                if($user['status']==200){
                    $_SESSION['isLogin'] = 1;
                    $_SESSION['user'] = $responseContent;
                    $_SESSION['sess']=$_GET['sess'];
                    if(isset($_GET['tag'])&&$_GET['tag']=='ajax'){
                        // header("Content-type: application/json");
                        // 指定允许其他域名访问
                        header('Access-Control-Allow-Origin:*');
// 响应类型
                        header('Access-Control-Allow-Methods:GET');
// 响应头设置
                        header('Access-Control-Allow-Headers:x-requested-with,content-type');
                        header("Content-type: application/json");
                        echo json_encode($_SESSION['user']);
                        exit;
                    }else{
                        //chatroom:
                        $this->http->redirect('/page/chatroom/');
                        return;
                        // goto chatroom;
                    }
                }else{

                    $this->assign('user', $user);
                    //$this->assign('debug', 'true');
                    $this->display('page/error.php');
                }
            }
        }
        else
        {
            goto login;
        }
    }

    function chatroom()
    {


        $this->session->start();
        if (empty($_SESSION['isLogin']))
        {
            $this->http->redirect('/page/index/');
            return;
        }
        $user = $_SESSION['user'];
        $this->assign('user', $user);
        $this->assign('debug', 'true');
        $this->display('page/chatroom.php');
    }
    private function get_url() {
        $sys_protocal = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        //$sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
    }
    /**
     * 用flash添加照片
     */
    function upload()
    {
        if ($_FILES)
        {
            // header("Content-type: application/json");
            // 指定允许其他域名访问
            header('Access-Control-Allow-Origin:*');
// 响应类型
            header('Access-Control-Allow-Methods:GET');
// 响应头设置
            header('Access-Control-Allow-Headers:x-requested-with,content-type');
            header("Content-type: application/json");
            global $php;
            $php->upload->thumb_width = 136;
            $php->upload->thumb_height = 136;
            $php->upload->thumb_qulitity = 100;
            $up_pic = $php->upload->save('Filedata');
            if (empty($up_pic))
            {
                echo '上传失败，请重新上传！ Error:' . $php->upload->error_msg;
            }
            $baseUrl=$this->get_url();
            $up_pic['thumb']=$baseUrl.$up_pic['thumb'];
            $up_pic['url']=$baseUrl.$up_pic['url'];
            echo json_encode($up_pic);
        }
        else
        {
            echo "Bad Request\n";
        }
    }
    function logout(){
        session_destroy();
    }
}