<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Swoole网页即时聊天</title>
    <link href="/static/css/bootstrap.css?timespan=1124" rel="stylesheet">
    <link href="/static/css/chat.css?timespan=1256789" rel="stylesheet">

    <script src="/static/js/jquery.js"></script>
    <script src="/static/js/jquery.json.js"></script>
    <script src="/static/js/console.js"></script>
    <script src="/config.js?timespan=12346" charset="utf-8"></script>
    <script src="/static/js/comet.js?timespan=123" charset="utf-8"></script>
    <script src="/static/js/chat.js?timespan=12456791171067" charset="utf-8"></script>
    <script type="text/javascript" src="/static/js/swfupload.js"></script>
    <script type="text/javascript" src="/static/js/swfupload.queue.js"></script>
    <script type="text/javascript" src="/static/js/fileprogress.js"></script>
    <script type="text/javascript" src="/static/js/handlers.js"></script>
    <script type="text/javascript" src="/static/js/facebox.js"></script>
    <script src="/static/layer/layer.js"></script>
    <script type="text/javascript">
        $.facebox.settings.closeImage = '/static/img/closelabel.png';
        $.facebox.settings.loadingImage = '/static/img/loading.gif';
        $(document).ready(function ($) {
            $('a[rel=facebox]').facebox();
        });
        var user = <?php echo json_encode($user);?>;
        var debug = <?php echo $debug;?>;
    </script>
    <link type="text/css" rel="stylesheet" href="/static/css/facebox.css"/>

    <script type="text/javascript">
        var swfu;
        window.onload = function () {
            var settings = {
                flash_url: "/static/swf/swfupload.swf",
                upload_url: "/page/upload/",
                post_params: {"uid": '0', 'post': 1, 'PHPSESSID': "0"},
                file_size_limit: "2MB",
                file_types: "*.jpg;*.png;*.gif",
                file_types_description: "图片文件",
                file_upload_limit: 100,
                file_queue_limit: 0,

                custom_settings: {
                    progressTarget: "fsUploadProgress",
                    cancelButtonId: "btnCancel"
                },
                debug: false,

                //Button settings
                button_image_url: "/static/img/button.png",
                button_width: "65",
                button_height: "29",
                button_placeholder_id: "upload_button",
                button_text: "<span>发送图片</span>",
                button_text_left_padding: 8,
                button_text_top_padding: 2,

                // The event handler functions are defined in handlers.js
                file_queued_handler: fileQueued,
                file_queue_error_handler: fileQueueError,
                file_dialog_complete_handler: fileDialogComplete,
                upload_start_handler: uploadStart,
                upload_progress_handler: uploadProgress,
                upload_error_handler: uploadError,
                upload_success_handler: uploadSuccess,
                upload_complete_handler: uploadComplete,
                queue_complete_handler: queueComplete	// Queue plugin event
            };
            swfu = new SWFUpload(settings);
        };
    </script>
    <style>
        body {
            padding-top: 60px;
        }
    </style>
    <!-- <link href="/static/css/bootstrap-responsive.css" rel="stylesheet"> -->
    <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
    <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
</head>
<body>


<div class="navbar navbar-fixed-top">
    <div class="navbar-inner">
        <div class="container-fluid">
            <div class="nav-collapse">
                <!--             <ul class="nav">
      <li class="active"><a href="/">Lobby</a></li>
    </ul> -->
                <ul class="nav pull-right">

                </ul>
            </div>
            <!--/.nav-collapse -->
        </div>
    </div>
</div>

<div class="container">
    <div class="container">
        <div class="row">

            <!--主聊天区-->
            <div id="chat-column" class="span8 well">

                <!--
                <div id="chat-tool" style="height:100px;border:0px solid #ccc;">
                    个人资料区
                </div>
                -->

                <!--消息显示区-->
                <div id="chat-messages" >
                    <div class="message-container">
                    </div>
                </div>


                <!--工具栏区-->
                <div id="chat-tool"
                     style="padding-left:10px;height:30px;border:0px solid #ccc;background-color:#F5F5F5;">
                    <div style="float: left; width: 140px;">
                        <input id="userlist" type="hidden" value="0"/>
                        <!--                        <select id="userlist" style="float: left; width: 90px;">-->
                        <!--                            <option value=0>所有人</option>-->
                        <!--                        </select>-->
                        <!-- 聊天表情 -->
                        <a onclick="toggleFace()" id="chat_face" class="chat_face">
                            <img src="/static/img/face/15.gif"/>
                        </a>
                    </div>
                    <div style="float: left; width: 200px;height: 25px;">
                        <span id="upload_button" style="background-color: #f5f5f5;"></span>
                        <input id="tc" type="button" value="群发"/>
                        <span style="display: none"><input id="btnCancel" style="height: 25px;" type="button"
                                                           value="取消上传" onclick="swfu.cancelQueue();"
                                                           disabled="disabled"/></span>
                    </div>
                </div>
                <!--工具栏结束-->

                <!--聊天表情弹出层-->
                <div id="show_face" class="show_face">
                </div>
                <!--聊天表情弹出层结束-->

                <!--发送消息区-->
                <div id="input-msg" style="height:110px;border:0px solid #ccc;">
                    <form id="msgform" class="form-horizontal post-form">
                        <div class="input-append">
                            <textarea id="msg_content" style="width:480px; height:80px;" rows="3" cols="500"
                                      contentEditable="true"></textarea>
                            <img style="width:80px;height:90px;" onclick="sendMsg($('#msg_content').val(), 'text');"
                                 src="/static/img/button.gif"/>
                        </div>
                    </form>
                </div>
            </div>
            <!--主聊天区结束-->


            <!--左边栏-->

            <div id="left-column" class="span3">
                <div class="well c-sidebar-nav">
                    <ul class="nav nav-list">
                        <li class="nav-header active">我的上级</li>
                        <li id="inroom_0"><a href="javascript:(0)">我的下级:</a>
                        </li>
                    </ul>
                    <div style="height: 572px; overflow-y: scroll">
                        <ul id="left-userlist">
                        </ul>
                    </div>

                    <div style="clear: both"></div>
                </div>
            </div>

        </div>
    </div>
    <!-- /container -->
    <div id="msg-template" style="display: none">
        <div class="message-container">
            <div class="userpic"></div>
            <div class="message">
                <span class="user"></span>

                <div class="cloud cloudText">
                    <div style="" class="cloudPannel">
                        <div class="sendStatus"></div>
                        <div class="cloudBody">
                            <div class="content"></div>
                        </div>
                        <div class="cloudArrow "></div>
                    </div>
                </div>
            </div>
            <div class="msg-time"></div>
        </div>
    </div>
    <!-- / -->
</div>
<style>
    #yaoyao .pagein {
        width: 500px;
        text-align: center;
    }

    manysend {
        border: 1px solid #ccc;
        padding: 7px 0px;
        border-radius: 3px;
        padding-left: 5px;
        -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075);
        box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075);
        -webkit-transition: border-color ease-in-out .15s, -webkit-box-shadow ease-in-out .15s;
        -o-transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
        transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
        width: 400px;
    }

    manysend:focus {
        border-color: #66afe9;
        outline: 0;
        -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075), 0 0 8px rgba(102, 175, 233, .6);
        box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075), 0 0 8px rgba(102, 175, 233, .6)
    }

    #yaoyao .pagein .divItem {
        margin-left: 10px;
        width: 90px;
        overflow-x: hidden;
        float: left;
    }

</style>
<div id="yaoyao" style="display: none">
    <div style="text-align: center;margin-top: 20px;">
        <div class="pagein">
            <textarea id="manysend" style="width: 450px;height: 210px;"></textarea>
        </div>
        <div class="pagein">
            <div style="width: 450px;text-align: left;margin: auto auto;clear: both;font-weight: bold;">我的下级</div>
            <div id="divContent"
                 style="width: 450px; height:130px;overflow-y:scroll;text-align: left;margin: auto auto;clear: both; border: 1px solid #eaeaea;padding: 10px;">
                <div class="divItem"><input id="allChecked" type="checkbox" value="-1">全选</div>


            </div>
        </div>


    </div>

</div>
</body>

<script>

    //页面层-自定义
    //页面层
    $('#tc').click(function () {
        //信息框-例1
//        layer.alert('见到你真的很高兴', {icon: 6});
//        layer.alert('内容');
        layer.open({
            type: 1,
            title: '群发消息',
            skin: 'layui-layer-rim', //加上边框
            area: ['500px', '550px'], //宽高
            content: $('#yaoyao'),
            btn: ['发送', '取消'],
            yes: function (index) {
                send_user_list.splice(0, send_user_list.length);
                var i = 0;
                $('.classCheckbox').each(function (index, ele) {
                    if ($(ele).is(':checked')) {
                        var val = $(ele).val();
                        addSend_user_list(val);
                        i++;
                    }
                });
                if (i == 0) {
                    layer.msg('请选择要发送的用户');
                } else {
                    var manySendContent = $('#manysend').val();
                    if (manySendContent.length > 0) {
                        sendMsg(manySendContent, 'text');
                        layer.msg('发送成功！！！');
                        layer.close(index);
                    } else {
                        layer.msg('不能发送空消息,请您输入内容,谢谢！！！');
                    }
                }



            }
        });
    })
    $('#allChecked').click(function () {
        if ($('#allChecked').is(':checked')) {
            $("[name = itemChecks]:checkbox").attr("checked", true);
        } else {
            $("[name = itemChecks]:checkbox").removeAttr('checked');
        }
    });

</script>

</html>


