<?php

/*
Plugin Name: JustClick Subscriber
Plugin URI: http://www.alekseykostin.ru/193/
Description: Subscribes user on JustClick mailing list after registration
Version: 0.1
Author: Kostin Aleksey
Author URI: http://www.alekseykostin.ru/
License: GNU General Public License v2.0
License URI: http://www.gnu.org/licenses/gpl-2.0.html

	Copyright (c) 2011 Kostin Aleksey

	Permission is hereby granted, free of charge, to any person obtaining a
	copy of this software and associated documentation files (the "Software"),
	to deal in the Software without restriction, including without limitation
	the rights to use, copy, modify, merge, publish, distribute, sublicense,
	and/or sell copies of the Software, and to permit persons to whom the
	Software is furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
	FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
	DEALINGS IN THE SOFTWARE.
*/


if (!function_exists('http_build_query')) {
	function http_build_query($formdata, $numeric_prefix = null)
	{
		// If $formdata is an object, convert it to an array
		if (is_object($formdata)) {
			$formdata = get_object_vars($formdata);
		}

		// Check we have an array to work with
		if (!is_array($formdata)) {
			user_error('http_build_query() Parameter 1 expected to be Array or Object. Incorrect value given.',
				E_USER_WARNING);
			return false;
		}

		// If the array is empty, return null
		if (empty($formdata)) {
			return;
		}

		// Argument seperator
		$separator = ini_get('arg_separator.output');

		// Start building the query
		$tmp = array ();
		foreach ($formdata as $key => $val) {
			if (is_integer($key) && $numeric_prefix != null) {
				$key = $numeric_prefix . $key;
			}

			if (is_scalar($val)) {
				array_push($tmp, urlencode($key).'='.urlencode($val));
				continue;
			}

			// If the value is an array, recursively parse it
			if (is_array($val)) {
				array_push($tmp, __http_build_query($val, urlencode($key)));
				continue;
			}
		}

		return implode($separator, $tmp);
	}

	// Helper function
	function __http_build_query ($array, $name)
	{
		$tmp = array ();
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				array_push($tmp, __http_build_query($value, sprintf('%s[%s]', $name, $key)));
			} elseif (is_scalar($value)) {
				array_push($tmp, sprintf('%s[%s]=%s', $name, urlencode($key), urlencode($value)));
			} elseif (is_object($value)) {
				array_push($tmp, __http_build_query(get_object_vars($value), sprintf('%s[%s]', $name, $key)));
			}
		}

		// Argument seperator
		$separator = ini_get('arg_separator.output');

		return implode($separator, $tmp);
	}
}

// WP HOOKS
add_action('init', 'justclick_init');
add_action('admin_menu', 'justclick_menu');
if (function_exists('stream_socket_client'))
{
	add_action('register_form','justclick_plugin_form');
	add_filter('registration_errors','justclick_post');
}
// END WP HOOKS
	
function justclick_init()
{
	load_plugin_textdomain('justclick', 'wp-content/plugins/'.basename(dirname(plugin_basename(__FILE__))), dirname(plugin_basename(__FILE__)));
}

function justclick_plugin_form()
{
	// Get options.
	$plugin_justclick_enabled = @intval(get_option('plugin_justclick_enabled'));
	$plugin_justclick_formcode = stripslashes(get_option('plugin_justclick_formcode'));
			
	if ($plugin_justclick_enabled>0 && !empty($plugin_justclick_formcode)):?>
		<style type="text/css">
			#lead_name {background:#FBFBFB;border:1px solid #E5E5E5;font-size:24px;margin: 2px 6px 12px 0;padding:3px;width:97%;}
			</style>
			<div width="100%">
				<p><label style="display: block; margin-bottom: 5px;"><?php echo __('Full name', 'justclick'); ?>
					<? /* Полное имя */ ?>
					<input type="text" name="lead_name" id="lead_name" class="input" value="<?php echo stripslashes($_POST['lead_name']); ?>" size="20" tabindex="26" />
				</label></p>
		</div><?php
		if (preg_match('/<input\s+style="[^"<>]+"\s+name="lead_phone"\s+class="input"\s+type="text"\s+\/>/is', $plugin_justclick_formcode)):?>
			<style type="text/css">
			#lead_phone {background:#FBFBFB;border:1px solid #E5E5E5;font-size:24px;margin: 2px 6px 12px 0;padding:3px;width:97%;}
			</style>
			<div width="100%">
				<p><label style="display: block; margin-bottom: 5px;"><?php echo __('Phone number', 'justclick'); ?>
					<? /* Номер телефона */ ?>
					<input type="text" name="lead_phone" id="lead_phone" class="input" value="<?php echo stripslashes($_POST['lead_phone']); ?>" size="20" tabindex="27" />
				</label></p>
		</div><?php endif; ?>

		<label style="cursor:pointer;">
			<table border="0">
				<tr>
					<td style="padding-right:8px;padding-bottom:8px;"><input type="checkbox" name="lead_subscribe" value="1"<?if (!empty($_POST['lead_subscribe'])):?> checked<?endif?> tabindex="28" /></td>
					<td style="padding-bottom:8px;"><?php echo __('Subscribe to mailing list', 'justclick'); ?></td>
					<? /* Подписаться на рассылки */ ?>
				</tr>
			</table>
		</label>
	<?php endif;	
}

function justclick_manage_rassilki()
{
	// Check form submission and update options...
	if(isset($_POST['submit']))
	{
		update_option('plugin_justclick_enabled', @intval($_POST['enabled']));
		update_option('plugin_justclick_formcode', $_POST['formcode']);
		// Output any action message (note, can only be from a POST or GET not both).
		echo "<div id='message' class='updated fade'><p>", __('Changes saved', 'justclick'), "</p></div>";
		/* Изменения сохранены */
	}

	// Get options.
	$plugin_justclick_enabled = @intval(get_option('plugin_justclick_enabled'));
	$plugin_justclick_formcode = stripslashes(get_option('plugin_justclick_formcode'));

	if(!function_exists('stream_socket_client')) echo "<div id='message' class='error'><p>", __('<b>ERROR</b>: This plugin requires function <code>stream_socket_client</code> to be defined. You need to upgrade to PHP 5.', 'justclick'), "</p></div>";
	
	?>
	<div class="wrap">
		<h2><?php echo __('Subscribe settings', 'justclick'); ?></h2>
		<? /* Настройки подписки */ ?>
		<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>">
			<table class='form-table'>
				<tr>
					<td><?php echo __('Subscribe user after register', 'justclick'); ?></td>
					<? /* Включить подписку при регистрации */ ?>
					<td colspan="2"><input type="checkbox" name="enabled" value="1"<?if ($plugin_justclick_enabled>0):?> checked<?endif?> /></td>
				</tr>
				<tr>
					<td><?php echo __('JustClick subscribe form code', 'justclick'); ?></td>
					<? /* Код формы подписки */ ?>
					<td><textarea name="formcode" cols="60" rows="10"><?php echo htmlspecialchars($plugin_justclick_formcode); ?></textarea></td>
					<td><small><?php echo __('Get this code in your member area on www.justclick.ru (Mail / Mailing lists)', 'justclick'); ?></small></td>
					<? /* Вставьте сюда полный код формы подписки, которые вы получили в личном кабинете JustClick в разделе Почта/Рассылки */ ?>
				</tr>
			</table>
			<p class="submit"><input type="submit" name="submit" value="<?php echo __('Save', 'justclick'); ?>" /></p>
			<? /* Сохранить */ ?>
		</form>
	</div>
	<?php
}

// Add sub-menus...
function justclick_menu()
{
	add_options_page(__('JustClick Subscriber', 'justclick'), __('JustClick Subscriber', 'justclick'), 1, basename(__FILE__), 'justclick_manage_rassilki');
	/* JustClick */
}

function justclick_post($errors)
{
	// Get options.
	$plugin_justclick_enabled = @intval(get_option('plugin_justclick_enabled'));
	$plugin_justclick_formcode = stripslashes(get_option('plugin_justclick_formcode'));
			
	if ($plugin_justclick_enabled>0 && !empty($plugin_justclick_formcode) && !empty($_POST['lead_subscribe']))
	{
		if (empty($_POST['lead_name']))
		{
			if (empty($errors) || is_array($errors)) $errors['justclick_error_name'] = __('<b>ERROR</b>: Fill in your full name.', 'justclick');
			else $errors->add('justclick_error_name', __('<b>ERROR</b>: Fill in your full name.', 'justclick'));
		}
		/* <b>ОШИБКА</b>: Пожалуйста, укажите ваше полное имя. */
		else
		{
			$post = array(
				'lead_name'=>stripslashes($_POST['lead_name']),
				'lead_email'=>stripslashes($_POST['user_email']),
				'lead_phone'=>stripslashes($_POST['lead_phone']),
				'rid'=>array(),
			);
			if (preg_match_all('/<input\s+name="rid\[(\d+)\]"\s+type="hidden"\s+value="([^"<>]+)"\s+\/>/is', $plugin_justclick_formcode, $arr, PREG_SET_ORDER))
			{
				foreach ($arr as $k=>$v) $post['rid'][$v[1]] = $v[2];
				
				if (preg_match('/<form\s+action="([^"]+)"/is', $plugin_justclick_formcode, $arr))
				{
					$res = justclick_http_request('POST', $arr[1], $post);
					if (substr($res['headers']['location'], -strlen('/subscribe/error/')) == '/subscribe/error/')
					{
						if (empty($errors) || is_array($errors)) $errors['justclick_error_phone'] = __('<b>ERROR</b>: Fill in your phone number.', 'justclick');
						else $errors->add('justclick_error_phone', __('<b>ERROR</b>: Fill in your phone number.', 'justclick'));
						/* <b>ОШИБКА</b>: Пожалуйста, укажите ваш номер телефона. */
					}
				}
			}
		}
	}
	return $errors;
}

function justclick_http_request($method, $url, $post=array(), $cookie=array())
{
	justclick_parse_url($url, $protocol, $host, $port, $path);
	$socket = stream_socket_client("tcp://{$host}:80", $errno, $errstr, 10, STREAM_CLIENT_CONNECT);

	if (!$socket)
	{
		echo "socket error tcp://{$host}:80 [{$errno}] {$errstr}\n";
		return false;
	}
	else
	{
		if ($method == 'POST') $out = "POST {$path} HTTP/1.0\r\n";
		else $out = "GET {$path} HTTP/1.0\r\n";
		$out .= "Host: {$host}\r\n";
		$out .= "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8\r\n";
		$out .= "Connection: Close\r\n";
		$out .= "Cookie: ";
		foreach ($cookie as $k=>$v)
			$out .= urlencode($k).'='.urlencode($v).' ';
		$out .= "\r\n";
		if ($method == 'POST')
		{
			$post = http_build_query($post);
			$out .= "Content-type: application/x-www-form-urlencoded; charset=utf-8\r\n";
			$out .= "Content-length: ".strlen($post)."\r\n";
		}
		$out .= "\r\n";
		if ($method == 'POST') $out .= $post;

		
		fwrite($socket, $out);
		
		$data = '';
		while (!feof($socket))
			$data .= fgets($socket, 1024);

		fclose($socket);

		$res = array();
		$data = ltrim($data);
		
		@list($headers, $data) = preg_split("/\r?\n\r?\n/", $data, 2);
		$headers = preg_split("/\r?\n/", $headers);
		@list($res['protocol'], $res['status']['code'], $res['status']['text']) = explode(' ', $headers[0]);
		array_shift($headers);
		
		
		$res['headers'] = array();
		foreach ($headers as $header)
		{
			@list($name, $value) = preg_split("/:\s?/", $header, 2);
			if (array_key_exists(strtolower($name), $res['headers']) && !is_array($res['headers'][strtolower($name)]))
			{
				$res['headers'][strtolower($name)] = array($res['headers'][strtolower($name)]);
				array_push($res['headers'][strtolower($name)], $value);
			}
			elseif (array_key_exists(strtolower($name), $res['headers']) && is_array($res['headers'][strtolower($name)]))
				array_push($res['headers'][strtolower($name)], $value);
			else
				$res['headers'][strtolower($name)] = $value;
		}

		$res['data'] = $data;
		
		return $res;
	}
}

function justclick_parse_url($url,&$protocol,&$domain,&$port,&$path)
{
	// устанавливаем переменные
	$protocol=$domain=$path=''; $port=0;
	$url=trim($url);

	// определяем протокол
	
	$_ppos = strpos($url, '://');
	if($_ppos !== false)
	{
		$protocol = substr($url, 0, $_ppos+3);
		$url = substr($url, $_ppos+3);
	}

	$url2=explode('/',$url);
	$domain=$url2[0];
	$domain=explode(':',$domain);

	if(!empty($domain[1])) $port=$domain[1];
	
	$domain=$domain[0];
	
	$url2[0]='';
	$path=implode('/',$url2);
	
	if(!$path) $path='/';
	if(!$protocol) $protocol='http';
	if(!$port) $port=80;
}

?>