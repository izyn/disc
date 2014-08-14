<?php

/**
 *	tinyPHP
 *
 *	An open source application development framework for PHP5
 *
 *	@package	tinyPHP
 *	@author		izyn
 *	@email		515189259@qq.com
 *	@copyright  Copyright (c) 2014, izyn, Inc.
 *	@link		https://github.com/izyn/disc
 *	@since		Version 0.0.1
 *	@createdtime	2014-06-18 12:04:32
 */

/*
 *-----------------------------------------------------------
 * 配置
 *-----------------------------------------------------------
 *
 * @param array $_config
 * 项目配置参数 
 *
 */

$_config = array();

/*-- 应用目录 --*/
$_config['app_path'] = './application';
$_config['uri_model'] = 2;

/*
 *-----------------------------------------------------------
 * 详细配置示例
 *-----------------------------------------------------------
 *
 * 系统错误提示级别：0 -不提示，1 -提示重要错误，2 -提示所有警告，default: 1
 *
 * $_config['debug'] = 1;
 *
 *-----------------------------------------------------------
 * uri模式:
 * -- 1. 目录模式(example: xxx.com/controller/action)
 * -- 2. query模式(example: xxx.com?c=controller&m=action)
 * -- default: 1
 *-----------------------------------------------------------
 *
 * $_config['uri_model'] = 1;
 *
 *-----------------------------------------------------------
 * 路由配置:
 *  -- controller_identifier 	 控制器标志，defaul: c
 *  -- action_identifier	 方法标志，defaul: m
 *  -- controller_default	 默认控制器，defaul: index
 *  -- action_default	 默认控制器-方法，defaul: index
 *-----------------------------------------------------------
 *
 * $_config['router']['controller_identifier'] = "c";
 * $_config['router']['action_identifier'] = "m";
 * $_config['router']['controller_default'] = "index";
 * $_config['router']['action_default'] = "index";
 *
 *-----------------------------------------------------------
 * 数据库配置:
 *  -- host		服务器
 *  -- user		用户名
 *  -- password		密码
 *  -- dbname		数据库名
 *  -- charset		字符集
 *  -- tablepre		表前缀
 *-----------------------------------------------------------
 *
 * $_config['db']['host'] = 'localhost';
 * $_config['db']['user'] = 'root';
 * $_config['db']['password'] = '';
 * $_config['db']['charset'] = 'utf8';
 * $_config['db']['dbname'] = 'test';
 * $_config['db']['tablepre'] = 'tiny_'; 表前缀
 *
 */

/*
 *-----------------------------------------------------------
 * 导入框架核心文件
 *-----------------------------------------------------------
 *
 * 
 */

$_config['db']['host'] = 'localhost';
$_config['db']['user'] = 'root';
$_config['db']['password'] = '';
$_config['db']['charset'] = 'utf8';
$_config['db']['dbname'] = 'test';

include './tinyPHP.class.php';

tinyPHP::creatapp()->run();