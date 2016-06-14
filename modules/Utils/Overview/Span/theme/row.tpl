<table class="Utils_Overview_Span__row">
	<tbody>
		<tr>{foreach key=k item=td from=$tds}
		<td class="{$td.class}" width="{$td.width}%">{$td.html}</td>
		{/foreach}
		</tr>		
	</tbody>
</table>
