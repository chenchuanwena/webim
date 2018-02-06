<?php
namespace WebIM;
use Swoole;
use Swoole\Filter;

class Server extends Swoole\Protocol\CometServer
{
    /**
     * @var Store\File;
     */
    protected $storage;
    protected $users;
    /**
     * 上一次发送消息的时间
     * @var array
     */
    protected $lastSentTime = array();

    const MESSAGE_MAX_LEN     = 1024; //单条消息不得超过1K
    const WORKER_HISTORY_ID   = 0;

    function __construct($config = array())
    {
        //将配置写入config.js
        $config_js = <<<HTML
var avataroffline='{$config['img']['avataroffline']}';
var avatarleadermessage='{$config['img']['avatarleadermessage']}';
var avatarleaderonline='{$config['img']['avatarleaderonline']}';
var avatarworkermessage='{$config['img']['avatarworkermessage']}';
var avatarworkeronline='{$config['img']['avatarworkeronline']}';
var webim = {
    'server' : '{$config['server']['url']}'
    }
HTML;
        file_put_contents(WEBPATH . '/config.js', $config_js);

        //检测日志目录是否存在
        $log_dir = dirname($config['webim']['log_file']);
        if (!is_dir($log_dir))
        {
            mkdir($log_dir, 0777, true);
        }
        if (!empty($config['webim']['log_file']))
        {
            $logger = new Swoole\Log\FileLog($config['webim']['log_file']);
        }
        else
        {
            $logger = new Swoole\Log\EchoLog(true);
        }
        $this->setLogger($logger);   //Logger

        /**
         * 使用文件或redis存储聊天信息
         */
        $this->storage = new Storage($config['storage']);
        $this->origin = $config['server']['origin'];
        parent::__construct($config);
    }

    /**
     * 下线时，通知所有人
     */
    function onExit($client_id)
    {
        $userInfo = $this->storage->getUser($client_id);
        if ($userInfo)
        {
            $resMsg = array(
                'cmd' => 'offline',
                'fd' => $client_id,
                'from' => 0,
                'channal' => 0,
                'data' => $userInfo['name'] . "下线了",
                'userid'=>$userInfo['userid'],
            );
            $this->storage->logout($client_id);
            unset($this->users[$client_id]);
            //将下线消息发送给所有人
            $this->broadcastJson($client_id, $resMsg);
        }
        $this->log("onOffline: " . $client_id);
    }

    function onTask($serv, $task_id, $from_id, $data)
    {
        $req = unserialize($data);
        if ($req)
        {
            switch($req['cmd'])
            {
                case 'getHistory':
                    $history = array('cmd'=> 'getHistory', 'history' => $this->storage->getMyHistory($req['userid']));
                    if ($this->isCometClient($req['fd']))
                    {
                        return $req['fd'].json_encode($history);
                    }
                    //WebSocket客户端可以task中直接发送
                    else
                    {
                        $this->sendJson(intval($req['fd']), $history);
                    }
                    break;
                case 'addHistory':
                    if (empty($req['msg']))
                    {
                        $req['msg'] = '';
                    }
                    $this->storage->addHistory($req['fd'], $req['msg']);
                    break;
                default:
                    break;
            }
        }
    }

    function onFinish($serv, $task_id, $data)
    {
        $this->send(substr($data, 0, 32), substr($data, 32));
    }
//    function cmd_getUnreadUser($client_id,$msg){
//        $resMsg = array(
//            'cmd' => 'getUnreadUser',
//        );
//        $users = $this->storage->getOnlineUsers();
//        $info = $this->storage->getUsers($users,$msg);
//        $resMsg['users'] = $users;
//        $resMsg['list'] = $info;
//        $this->sendJson($client_id, $resMsg);
//    }
    /**
     * 获取在线列表
     */
    function cmd_getOnline($client_id, $msg)
    {
        $resMsg = array(
            'cmd' => 'getOnline',
        );
        $users = $this->storage->getOnlineUsers();
        $info = $this->storage->getUsers($users,$msg);
        $resMsg['users'] = $users;
        $resMsg['list'] = $info;
        $this->sendJson($client_id, $resMsg);
    }

    function cmd_updateMessage($client_id, $msg){
        $this->storage->updateMessage($client_id,$msg);
    }

    /**
     * 获取历史聊天记录
     */
    function cmd_getHistory($client_id, $msg)
    {
        $task['fd'] = $client_id;
        $task['cmd'] = 'getHistory';
        $task['offset'] = '0,100';
        $task['userid']=$msg['userid'];
        //在task worker中会直接发送给客户端
        $this->getSwooleServer()->task(serialize($task), self::WORKER_HISTORY_ID);
    }

    /**
     * 登录
     * @param $client_id
     * @param $msg
     */
    function cmd_login($client_id, $msg)
    {
       // var_dump($msg);
        $info['name'] = Filter::escape(strip_tags($msg['name']));
        $info['avatar'] = Filter::escape($msg['avatar']);
        $info['parent']=$msg['parent'];
        $info['childrens']=Filter::escape($msg['childrens']);
        $info['userid']=Filter::escape($msg['userid']);
        //var_dump($info);
        //回复给登录用户
        $resMsg = array(
            'cmd' => 'login',
            'fd' => $client_id,
            'name' => $info['name'],
            'avatar' => $info['avatar'],
            'userid'=>$info['userid'],
        );
        $resRedis=array(
            'cmd' => 'login',
            'fd' => $client_id,
            'name' => $info['name'],
            'userid'=>$info['userid'],
            'avatar' => $info['avatar'],
            'parent_id' => $info['parent']['userid']?$info['parent']['userid']:0,
            'parent_name' => $info['parent']['username']?$info['parent']['username']:'',
        );

        //把会话存起来
        $this->users[$client_id] = $resMsg;

        $this->storage->login($client_id, $resRedis);
        $this->sendJson($client_id, $resMsg);

        //广播给其它在线用户
        $resMsg['cmd'] = 'newUser';
        //将上线消息发送给所有人
        $this->broadcastJson($client_id, $resMsg);
        //用户登录消息
        $loginMsg = array(
            'cmd' => 'fromMsg',
            'from' => 0,
            'channal' => 0,
            'data' => $info['name'] . "上线了",
        );
        $this->broadcastJson($client_id, $loginMsg);
    }

    /**
     * 发送信息请求
     */
    function cmd_message($client_id, $msg)
    {
        $resMsg = $msg;
        $resMsg['cmd'] = 'fromMsg';

        if (strlen($msg['data']) > self::MESSAGE_MAX_LEN)
        {
            $this->sendErrorMessage($client_id, 102, 'message max length is '.self::MESSAGE_MAX_LEN);
            return;
        }

        $now = time();
        //上一次发送的时间超过了允许的值，每N秒可以发送一次
        if ($this->lastSentTime[$client_id] > $now - $this->config['webim']['send_interval_limit'])
        {
            $this->sendErrorMessage($client_id, 104, $msg['data'].'这个消息发送不成功！每秒限发一条信息');
            return;
        }
        //记录本次消息发送的时间
        $this->lastSentTime[$client_id] = $now;
        //表示群发
        if ($msg['channal'] == 0)
        {
            $this->broadcastJson($client_id, $resMsg);
            $this->getSwooleServer()->task(serialize(array(
                'cmd' => 'addHistory',
                'msg' => $msg,
                'fd'  => $client_id,
            )), self::WORKER_HISTORY_ID);
        }
        //表示私聊
        elseif ($msg['channal'] == 1)
        {
            foreach($resMsg['users'] as $user){
                if($user['fd']>-1){
                    $resMsg['from']=$resMsg['userid'];
                    $this->sendJson($user['fd'], $resMsg);
                }
                $msg['to_userid']=$user['userid'];
                $msg['to_username']=$user['username'];
                $this->storage->addHistory($client_id, $msg);
            }


        }
     /*   //表示群发
        if ($msg['channal'] == 0)
        {
            $this->broadcastJson($client_id, $resMsg);
            $this->getSwooleServer()->task(serialize(array(
                'cmd' => 'addHistory',
                'msg' => $msg,
                'fd'  => $client_id,
            )), self::WORKER_HISTORY_ID);
        }
        //表示私聊
        elseif ($msg['channal'] == 1)
        {

                    $this->sendJson($msg['to'], $resMsg);


            $this->storage->addHistory($client_id, $msg);
        }*/
    }

    /**
     * 接收到消息时
     * @see WSProtocol::onMessage()
     */
    function onMessage($client_id, $ws)
    {
        $this->log("onMessage #$client_id: " . $ws['message']);
        $msg = json_decode($ws['message'], true);
        if (empty($msg['cmd']))
        {
            $this->sendErrorMessage($client_id, 101, "invalid command");
            return;
        }
        $func = 'cmd_'.$msg['cmd'];
        if (method_exists($this, $func))
        {
            $this->$func($client_id, $msg);
        }
        else
        {
            $this->sendErrorMessage($client_id, 102, "command $func no support.");
            return;
        }
    }

    /**
     * 发送错误信息
    * @param $client_id
    * @param $code
    * @param $msg
     */
    function sendErrorMessage($client_id, $code, $msg)
    {
        $this->sendJson($client_id, array('cmd' => 'error', 'code' => $code, 'msg' => $msg));
    }

    /**
     * 发送JSON数据
     * @param $client_id
     * @param $array
     */
    function sendJson($client_id, $array)
    {
        $msg = json_encode($array);
        if ($this->send($client_id, $msg) === false)
        {
            $this->close($client_id);
        }
    }

    /**
     * 广播JSON数据
     * @param $client_id
     * @param $array
     */
    function broadcastJson($sesion_id, $array)
    {
        $msg = json_encode($array);
        $this->broadcast($sesion_id, $msg);
    }

    function broadcast($current_session_id, $msg)
    {
        foreach ($this->users as $client_id => $name)
        {
            if ($current_session_id != $client_id)
            {
                $this->send($client_id, $msg);
            }
        }
    }
}

