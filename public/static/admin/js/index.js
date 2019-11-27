/*
* @Author: YeMiao
* @Date:   2017-07-29 15:36:18
* @Last Modified by:   YeMiao
* @Last Modified time: 2018-09-28 10:38:25
*/
layui.use(['element', 'layer'], function () {
    layer = layui.layer;

    $(document).off('pjax:start').on('pjax:start', function () {
        loadIndex = layer.load(0);
    });

    $(document).off('pjax:end').on('pjax:end', function () {
        layer.close(loadIndex);
    });
});

$(function () {

    // 菜单栏事件
    $('a').on('click', function () {
        let url = $(this).attr('href');
        if (url.indexOf('out.html') > 0) {
            return true;
        }
        if (url != 'javascript:;') {
            $.each($('.layui-nav').find('li'), function () {
                $(this).removeClass('layui-this');
            });
            $.each($('.layui-nav').find('dd'), function () {
                $(this).removeClass('layui-this');
            });
            $(this).parent().addClass('layui-this');
            reload(url);
        }
        return false;
    });

    $('#home').click();


    // 按键监听
    $(document).keydown(function (event) {
        // 翻页快捷键绑定 ,','左翻页, '。'右翻页
        // if($('.pagination').length != 0){
        //   if(event.keyCode == 190){
        //     if($("span:contains('»')").length != 0){
        //       layer.msg('已经是最后一页');
        //     }
        //     if($(".pagination a:contains('»')").length != 0){
        //       let url = $(".pagination a:contains('»')").attr('href');
        //       reload(url);
        //     }
        //   }
        //   if(event.keyCode == 188){
        //     if($("span:contains('«')").length != 0){
        //       layer.msg('已经是第一页');
        //     }
        //     if($(".pagination a:contains('«')").length != 0){
        //       let url = $(".pagination a:contains('«')").attr('href');
        //       reload(url);
        //     }
        //   }
        // }
        // f5 刷新事件
        if (event.keyCode == 116) {
            refresh();
            return false;
        }
    });

    // pjax终了
    // user事件
    $('.user,#user_div').hover(function () {
        $('#user_div').show();
    }, function () {
        $('#user_div').hide();
    });
    $('.UserConter').click(function () {

    });


});
// 定义全局属性
// 弹出层的索引
var index;
var loadIndex;
var object;
var back;
var layer;
// 自定义全局方法
// 刷新
var refresh = function () {
    let url = window.location.pathname + window.location.search;
    $.pjax({
        url,
        container: '#main',
        type: 'pjax',
        dataType: 'html',
        timeout: 300000
    });
}
// 加载
var reload = function (url) {
    $.pjax({
        url,
        container: '#main',
        type: 'pjax',
        dataType: 'html',
        timeout: 300000
    });
}
// 记录url
var record = function () {
    back = window.location.pathname + window.location.search;
}
// 分页加载
var page_load = function () {
    $('.pagination a').off('click').on('click', function () {
        url = $(this).attr('href');
        reload(url);
        return false;
    });
}
// 弹出层
var open_page = function(url,title, obj = {}){
    $.post(url,obj,function(data){
        index = layer.open({
            title: title,
            type: 1,
            area: ['800px', 'auto'],
            fixed: true,
            content: data,
            skin: 'pop-up',
            maxmin: true,
            resizing:function(layro){
                let h = layro.height();
                layro.find('.layui-layer-content').css('height',h-42+'px');
            }
        });
    });
}

// 添加按钮事件 -- 弹出添加层
var add = function (url, title) {
    $.post(url, function (data) {
        index = layer.open({
            title: title,
            type: 1,
            area: ['800px', 'auto'],
            fixed: true,
            content: data,
            skin: 'pop-up',
            maxmin: true,
            resizing: function (layro) {
                let h = layro.height();
                layro.find('.layui-layer-content').css('height', h - 42 + 'px');
            }
        });
    });
}
// 修改按钮事件 -- 弹出修改层
var edit = function (url, id, title) {
    console.log(title);
    $.post(url, {
        id: id
    }, function (data) {
        if (data) {
            index = layer.open({
                title: title,
                type: 1,
                area: ['800px', 'auto'],
                content: data,
                skin: 'pop-up',
                maxmin: true,
                resizing: function (layro) {
                    let h = layro.height();
                    layro.find('.layui-layer-content').css('height', h - 42 + 'px');
                }
            });
        } else {
            layer.msg('未找到指定数据！');
        }
    });
}
// 删除按钮事件 -- 弹出删除层
var del = function (url, id) {
    layer.confirm('确定删除！', {
        btn: ['确定', '取消'] //按钮
    }, function () {
        $.post(url, {
            id: id
        }, function (data) {
            if (data.code == 1) {
                layer.msg('删除成功！');
                refresh();
            } else {
                layer.msg('未找到指定数据！');
            }
        });
    });
}
// 表单的提交事件
var post = function (obj) {
    $(obj).ajaxSubmit({
        success: function (data) {
            if (data.code == 1) {
                layer.close(index);
                layer.closeAll();
                layer.msg(data.msg);
                refresh();
            } else {
                layer.msg(data.msg);
            }
        }
    });
    return false;
}
// 状态的点击事件--state可多参数。
// state数据格式json对象。
var state = function (url, id, state) {
    let data = {
        id: id
    }
    for (let j in state) {
        data[j] = state[j];
    }
    $.post(url, data, function (data) {
        if (data.code == 1) {
            layer.msg(data.msg);
        } else {
            layer.msg(data.msg);
        }
        refresh();
    });
}
// tips层
var tips = function (str, object) {
    layer.tips(str, object, {
        tips: 1
    });
}


// 删除按钮事件 -- 弹出删除层
var config = function (url, id, state, title) {
    layer.confirm('确定更改(' + title + ')！', {
        btn: ['确定', '取消'] //按钮
    }, function () {
        $.post(url, {
            id: id, state: state
        }, function (data) {
            if (data.code == 1) {
                layer.msg('更改成功！');
                refresh();
            } else {
                layer.msg(data['msg']);
            }
        });
    });
}