<?php
namespace WebIM;

class Storage
{
    /**
     * @var \redis
     */
    protected $redis;

    const PREFIX = 'webim';

    function __construct($config)
    {
        $this->redis = \Swoole::getInstance()->redis;
        $this->redis->delete(self::PREFIX.':online');
        $this->config = $config;
    }

    function login($client_id, $info)
    {
        $this->redis->set(self::PREFIX . ':client:' . $client_id, json_encode($info));
        $this->redis->sAdd(self::PREFIX . ':online', $client_id);
       // $this->redis->sAdd(self::PREFIX . ':online:'.$info['userid'], $client_id);
    }

    function logout($client_id)
    {
        $this->redis->del(self::PREFIX.':client:'.$client_id);
        $this->redis->sRemove(self::PREFIX.':online', $client_id);
    }

    /**
     * 用户在线用户列表
     * @return array
     */
    function getOnlineUsers()
    {
        return $this->redis->sMembers(self::PREFIX . ':online');
    }

    /**
     * 批量获取用户信息
     * @param $users
     * @return array
     */
    function getUsers(&$users,$user)
    {
        $keys = array();
        $ret = array();

        foreach ($users as $v)
        {
            $keys[] = self::PREFIX . ':client:' . $v;
        }
        $users=array();
        $info = $this->redis->mget($keys);
        foreach ($info as $v)
        {
            $temp=json_decode($v, true);
            $temp['is_child']=0;
            if($temp['parent_id']==$user['userid']){
                $temp['is_child']=1;
                $ret[] = $temp;
                $users[]=$temp['fd'];
            }
            if($user['parentid']>0 && $user['parentid']==$temp['userid']){
                $temp['is_child']=0;
                $ret[] = $temp;
                $users[]=$temp['fd'];
            }


        }

        return $ret;
    }

    /**
     * 获取单个用户信息
     * @param $userid
     * @return bool|mixed
     */
    function getUser($userid)
    {
        $ret = $this->redis->get(self::PREFIX . ':client:' . $userid);
        $info = json_decode($ret, true);

        return $info;
    }

    function exists($userid)
    {
        return $this->redis->exists(self::PREFIX . ':client:' . $userid);
    }
    function updateMessage($client_id, $msg){
        $data['not_read_number']=0;
        $where['from_userid']=$msg['to_userid'];
        $where['userid']=$msg['from_userid'];
        table(self::PREFIX.'_message_status')->sets($data,$where);
    }
    function addHistory($userid, $msg)
    {
        //$info = $this->getUser($userid);
      //  $log['user'] = $info;
        $log['msg'] = $msg;
        $log['time'] = time();
        $log['type'] = empty($msg['type']) ? '' : $msg['type'];
        $log['to_userid']=$msg['to_userid'];
        $log['from_userid']=$msg['userid'];
        table(self::PREFIX.'_history')->put(array(
            'name' => $msg['username'],
            'avatar' => $msg['avatar'],
            'msg' => json_encode($msg),
            'type' => empty($msg['type']) ? '' : $msg['type'],
            'from_userid'=>$log['from_userid'],
            'to_userid'=>$log['to_userid'],
            'to_username'=>$msg['to_username'],
        ));
        table(self::PREFIX.'_message_status')->insertOrReplace($log);
//        $info = $this->getUser($userid);
//
//        $log['user'] = $info;
//        $log['msg'] = $msg;
//        $log['time'] = time();
//        $log['type'] = empty($msg['type']) ? '' : $msg['type'];
//        $log['to_userid']=$msg['to_userid'];
//        $log['from_userid']=$info['userid'];
//
//        table(self::PREFIX.'_history')->put(array(
//            'name' => $info['name'],
//            'avatar' => $info['avatar'],
//            'msg' => json_encode($msg),
//            'type' => empty($msg['type']) ? '' : $msg['type'],
//        ));
    }

    function getHistory($offset = 0, $num = 100)
    {
        $data = array();
        $list = table(self::PREFIX.'_history')->gets(array('limit' => $num,));
        foreach ($list as $li)
        {
            $result['type'] = $li['type'];
            $result['user'] = array('name' => $li['name'], 'avatar' => $li['avatar']);
            $result['time'] = strtotime($li['addtime']);
            $result['msg'] = json_decode($li['msg'], true);
            $data[] = $result;
        }

        return array_reverse($data);
    }

    function getMyHistory($userid = 0, $num = 100)
    {
        $data = array();
        $where=" from_userid=".$userid." or to_userid=".$userid;
        $list = table(self::PREFIX.'_history')->gets(array('limit' => $num,'where'=>$where));
        foreach ($list as $li)
        {
            $result['type'] = $li['type'];
            $result['user'] = array('name' => $li['name'], 'avatar' => $li['avatar']);
            $result['time'] = strtotime($li['addtime']);
            $result['msg'] = json_decode($li['msg'], true);
            $result['from_userid']=$li['from_userid'];
            $result['to_userid']=$li['to_userid'];
            $result['to_username']=$li['to_username'];
            $result['username']=$li['name'];
            $data[] = $result;
        }

        return array_reverse($data);
    }
    function getUnreadUser($userids=array(),$field=false){
        $data = array();
        if(is_array($userids)){
            $userids=implode(',',$userids);
        }
        $where=" userid in=({$userids})";
        $list = table(self::PREFIX.'_history')->gets(array('where'=>$where));
        if($field!==false){
            $convertList=array();
            foreach($list as &$value){
                $convertList[$value['userid']]=$value;
            }
            $list=$convertList;
        }
        return $list;
    }
}