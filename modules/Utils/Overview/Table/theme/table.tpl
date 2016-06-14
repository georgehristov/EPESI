{if $left_area || $right_area}
<div id="Utils_Overview__layout" style="width: 98%; height: 780px; display: none;">
	<div id="Utils_Overview__table" class="ui-layout-center">{$table}</div>
	{if $right_area} <div id="Utils_Overview__right_area" class="ui-layout-east">{$right_area}</div>{/if}
	{if $left_area} <div id="Utils_Overview__left_area" class="ui-layout-west">{$left_area}</div>{/if}
</div>
{else}

{if $scroll_wrapper}
<div class="{$scroll_wrapper}" style="max-height:{$scroll_height}px;">
{/if}
{$header_area}
{$table}
{$footer_area}
{if $scroll_wrapper}
</div>
{/if}

{/if}