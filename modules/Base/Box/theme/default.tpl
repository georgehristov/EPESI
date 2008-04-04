{if !$logged}

<div id="Base_Box__login">
	<div class="status">{$status}</div>
	<div class="entry">{$login}</div>
	<div class="starting">{$about}</div>
</div>

{else}

{php}
	load_js('data/Base_Theme/templates/default/Base_Box__default.js');
	eval_js_once('document.body.id=null');
{/php}

	<div id="top_bar">
		<div id="MenuBar">
		<table id="top_bar_1" cellspacing="0" cellpadding="0" border="0">
		<tbody>
			<tr>
				<td class="roll-left"><img id="roll" src="{$theme_dir}/Base_Box__roll-up.png" onClick="var x='{$theme_dir}/Base_Box__roll-';if(this.src.indexOf(x+'down.png')>=0)this.src=x+'up.png';else this.src=x+'down.png'; base_box_roll_topbar();" width="14" height="14" alt="=" border="0"></td>
				<td class="menu-bar">{$menu}</td>
				<td class="powered"><b>epesi</b> powered</td>
				<td class="version">{$version_no}</td>
				<td id="clock_td"><div id="digitalclock" style="display: none;"></div></td>
				<td class="clock_icon" onclick="show_hide_clock();"><img border="0" src="{$theme_dir}/Base_Box__clock.png" width="16" height="16"></td>
				<td class="roll-right"><img id="roll" src="{$theme_dir}/Base_Box__roll-right.png" onClick="var x='{$theme_dir}/Base_Box__roll-';if(this.src.indexOf(x+'left.png')>=0)this.src=x+'right.png';else this.src=x+'left.png'; base_box_roll_search_login_bar();" width="14" height="14" alt="=" border="0"></td>
				<td class="module-indicator"><div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div><div id="quick-logout" style="display: none;"></div></td>
			</tr>
		</tbody>
		</table>
		</div>
		<div id="ShadowBar"></div>
		<div id="ActionBar">
			<table id="top_bar_2" cellspacing="0" cellpadding="0" border="0">
			<tbody>
				<tr>
					<td class="logo"><a href="#"><img border="0" src="{$theme_dir}/images/logo-small.png" width="193" height="68"></a></td>
					<td class="icons">{$actionbar}</td>
					<td id="login-search-td">
						<div id="search-login-bar">
							<div class="login"><br>{$login}</div>
							<div class="search"><center>{$search}</center></div>
						</div>
					</td>
				</tr>
			</tbody>
			</table>
		</div>
		<div id="gray-transparent"></div>
	</div>
	<!-- -->
	<div id="content">
		<div id="EmptyDivMenu"></div>
		<div id="EmptyDiv"></div>
		<div id="content_body" style="padding: 0px; text-align: center;">
			<center>{$main}</center>
		</div>
	</div>

{$status}

{literal}
<style type="text/css">
div > div#top_bar { position: fixed;}
div > div#bottom_bar { position: fixed;}
</style>

<!--[if gte IE 5.5]><![if lt IE 7]>

<style type="text/css">
#top_bar {
	position: absolute;
	width: expression( (body.offsetWidth-20)+'px');
}
#content_body {
	width: expression( (body.offsetWidth-20)+'px');
}

#body_content {
	display: block;
	height: 100%;
	max-height: 100%;
	overflow-x: hidden;
	overflow-y: auto;
	position: relative;
	z-index: 0;
	width:100%;
}

html { height: 100%; max-height: 100%; padding: 0; margin: 0; border: 0; overflow:hidden; /*get rid of scroll bars in IE */ }
body { height: 100%; max-height: 100%; border: 0; }




.layer .left,
.layer .right,
.layer .center {
	background: none !important;
}

.layer .shadow-middle div {
	height: expression(
		x = this.parentNode.parentNode.offsetHeight,
		y = parseInt(this.currentStyle.top),
		(x - ((x % 2) ? 1 : 0) - (y * 2)) + 'px'
	)
}

.layer .shadow-top .center,
.layer .shadow-bottom .center {
	width: expression(
		x = this.parentNode.parentNode.offsetWidth,
		y = parseInt(this.currentStyle.left),
		(x - ((x % 2) ? 1 : 0) - (y * 2)) + 'px'
	)
}
																								/* POPRAWIC SCIEZKE ! */
.layer .shadow-top .left		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/tl.png", sizingMethod="crop");  }
.layer .shadow-top .right		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/tr.png", sizingMethod="crop");  }
.layer .shadow-bottom .left		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/bl.png", sizingMethod="crop");  }
.layer .shadow-bottom .right	{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/br.png", sizingMethod="crop");  }
.layer .shadow-top .center		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/t.png",  sizingMethod="scale"); }
.layer .shadow-bottom .center	{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/b.png",  sizingMethod="scale"); }
.layer .shadow-middle .left		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/l.png",  sizingMethod="scale"); }
.layer .shadow-middle .right	{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/r.png",  sizingMethod="scale"); }

.layer .shadow-bottom div.center {
	bottom: -3px;
}

.layer .shadow-top div.center {
	top: -2px;
}

</style>

<![endif]><![endif]-->

{/literal}

{/if}
