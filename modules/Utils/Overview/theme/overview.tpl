<div style="text-align: left;{if $hide_header}display:none;{/if}">
<table class="Overview__title" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			{if isset($caption)}
				<td style="width:100px;">
					<div class="name">
						<img alt=" " class="icon" src="{$icon}" width="32" height="32" border="0">
						<div class="label">	
							{if isset($form_data)}
								{$form_open}
							{/if}
							{$caption}
							{if isset($form_data)}
								{$form_data.__mode__.html}
								{$form_close}
							{/if}						
						</div>						
					</div>
				</td>
				<td>
				</td>
			{/if}
		</tr>
		<tr>
			<td style="height:10px" colspan="2">
                &nbsp;
            </td>    		
        </tr>
		<tr {if $hide_form}style="display:none;"{/if}>
			<td style="height:40px" colspan="2">
                {$settings}
            </td>    		
        </tr>
	</tbody>
</table>
</div>
{$contents}